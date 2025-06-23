<?php

declare(strict_types=1); 

namespace Ultra\ErrorManager\Providers;

// Core Laravel contracts & classes
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Mail\Mailer as MailerContract; 
use Illuminate\Contracts\Translation\Translator as TranslatorContract; 
use Illuminate\Http\Request; 
use Illuminate\Routing\Router; 
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

// UEM specific classes & interfaces
use Ultra\ErrorManager\ErrorManager;
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface; 
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface; 
// Handlers
use Ultra\ErrorManager\Handlers\LogHandler; 
use Ultra\ErrorManager\Handlers\EmailNotificationHandler; 
use Ultra\ErrorManager\Handlers\UserInterfaceHandler; 
use Ultra\ErrorManager\Handlers\ErrorSimulationHandler; 
use Ultra\ErrorManager\Handlers\RecoveryActionHandler; 
use Ultra\ErrorManager\Handlers\DatabaseLogHandler; 
use Ultra\ErrorManager\Handlers\SlackNotificationHandler; 
// Services
use Ultra\ErrorManager\Services\TestingConditionsManager;
// Middleware
use Ultra\ErrorManager\Http\Middleware\ErrorHandlingMiddleware;
use Ultra\ErrorManager\Http\Middleware\EnvironmentMiddleware;

// Dependencies from other Ultra packages
use Ultra\UltraLogManager\UltraLogManager; 
use Illuminate\Http\Client\Factory as HttpClientFactory;

final class UltraErrorManagerServiceProvider extends ServiceProvider 
{
    public function register(): void
    {
        $configKey = 'error-manager';
        $this->mergeConfigFrom(__DIR__.'/../../config/error-manager.php', $configKey);

        $this->app->singleton(TestingConditionsManager::class, function (Application $app) {
             return new TestingConditionsManager($app);
        });
        $this->app->alias(TestingConditionsManager::class, 'ultra.testing-conditions');

        // This method now handles ALL handler registrations, including the corrected LogHandler.
        $this->registerHandlers($configKey);

        // --- REMOVED incorrect LogHandler registration from here ---

        $this->app->singleton('ultra.error-manager', function (Application $app) use ($configKey) {
            $ulmLogger = $app->make(UltraLogManager::class);
            $translator = $app->make(TranslatorContract::class);
            $request = $app->make(Request::class);
            $config = $app['config'][$configKey] ?? [];

            $manager = new ErrorManager($ulmLogger, $translator, $request, $config);

            $defaultHandlerClasses = $config['default_handlers'] ?? $this->getDefaultHandlerSet();

            foreach ($defaultHandlerClasses as $handlerClass) {
                try {
                    if (class_exists($handlerClass)) {
                        $handlerInstance = $app->make($handlerClass);
                        if ($handlerInstance instanceof ErrorHandlerInterface) {
                            $manager->registerHandler($handlerInstance);
                        } else {
                             $ulmLogger->warning("UEM Provider: Class {$handlerClass} does not implement ErrorHandlerInterface.", ['provider' => self::class]);
                        }
                    } else {
                         $ulmLogger->warning("UEM Provider: Handler class not found [{$handlerClass}]", ['provider' => self::class]);
                    }
                } catch (\Exception $e) {
                     $ulmLogger->error("UEM Provider: Failed to register handler {$handlerClass}", [
                        'exception' => $e->getMessage(),
                        'provider' => self::class
                     ]);
                }
            }

            return $manager;
        });

        $this->app->bind(ErrorManagerInterface::class, 'ultra.error-manager');

        $this->app->singleton(ErrorHandlingMiddleware::class);
        $this->app->singleton(EnvironmentMiddleware::class);
    }

    /**
     * 🧱 Helper method to register default handlers and their dependencies.
     *
     * @param string $configKey The key for the package's configuration.
     * @return void
     */
    protected function registerHandlers(string $configKey): void
    {
        // --- CORRECTED REGISTRATION FOR THE AUTONOMOUS LOGHANDLER ---
        $this->app->singleton(LogHandler::class, function (Application $app) use ($configKey) {
            // Pass the 'log_handler' config section to the constructor.
            $handlerConfig = $app['config'][$configKey]['log_handler'] ?? [];
            return new LogHandler($handlerConfig);
        });

        // --- Other handler registrations remain the same ---
        $this->app->singleton(EmailNotificationHandler::class, function (Application $app) use ($configKey) {
            return new EmailNotificationHandler(
                $app->make(MailerContract::class),
                $app->make(UltraLogManager::class),
                $app->make(Request::class),
                $app->make(AuthFactory::class),
                $app['config'][$configKey]['email_notification'] ?? [],
                $app['config']['app']['name'] ?? 'Laravel',
                $app->environment()
            );
        });

        $this->app->singleton(UserInterfaceHandler::class, function ($app) {
            $session = $app->make(\Illuminate\Contracts\Session\Session::class);
            $uiConfig = $app['config']->get('error-manager.ui', []);
            return new UserInterfaceHandler($session, $uiConfig);
        });

         $this->app->singleton(DatabaseLogHandler::class, function (Application $app) use ($configKey) {
             $ulmLogger = $app->make(UltraLogManager::class);
             $dbConfig = $app['config'][$configKey]['database_logging'] ?? [];
             return new DatabaseLogHandler($ulmLogger, $dbConfig);
         });

         $this->app->singleton(RecoveryActionHandler::class, function (Application $app) {
             $ulmLogger = $app->make(UltraLogManager::class);
             return new RecoveryActionHandler($ulmLogger);
         });

         $this->app->singleton(SlackNotificationHandler::class, function (Application $app) use ($configKey) {
             $httpClientFactory = $app->make(HttpClientFactory::class);
             $slackConfig = $app['config'][$configKey]['slack_notification'] ?? [];
             $ulmLogger = $app->make(UltraLogManager::class);
             $request = $app->make(Request::class);
             $appName = $app['config']['app']['name'] ?? 'Laravel';
             $environment = $app->environment();
             return new SlackNotificationHandler($httpClientFactory, $ulmLogger, $request, $slackConfig, $appName, $environment);
         });

        $this->app->singleton(ErrorSimulationHandler::class, function (Application $app) {
            $testingManager = $app->make(TestingConditionsManager::class);
            $ulmLogger = $app->make(UltraLogManager::class);
            return new ErrorSimulationHandler($app, $testingManager, $ulmLogger);
        });
    }

    protected function getDefaultHandlerSet(): array
    {
        return [
            LogHandler::class,
            DatabaseLogHandler::class,
            EmailNotificationHandler::class,
            SlackNotificationHandler::class,
            UserInterfaceHandler::class,
            RecoveryActionHandler::class,
            ($this->app->environment() !== 'production' ? ErrorSimulationHandler::class : null)
        ];
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'error-manager');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'error-manager');

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('error-handling', ErrorHandlingMiddleware::class);
        $router->aliasMiddleware('environment', EnvironmentMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    protected function bootForConsole(): void
    {
        $this->publishes([__DIR__.'/../../config/error-manager.php' => $this->app->configPath('error-manager.php')], 'error-manager-config');
        $this->publishes([__DIR__.'/../../resources/views' => $this->app->resourcePath('views/vendor/error-manager')], 'error-manager-views');
        $this->publishes([__DIR__.'/../../resources/lang' => $this->app->langPath('vendor/error-manager')], 'error-manager-language');
        $this->publishes([__DIR__.'/../../database/migrations' => $this->app->databasePath('migrations')], 'error-manager-migrations');
    }
}