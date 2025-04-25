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
use Psr\Log\LoggerInterface as PsrLoggerInterface; 
use Illuminate\Contracts\Auth\Factory as AuthFactory; // Added AuthFactory

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
use Illuminate\Http\Client\Factory as HttpClientFactory; // Added HttpClientFactory

/**
 * 🎯 Service Provider for Ultra Error Manager (UEM) – Oracoded DI Refactored
 *
 * Registers and bootstraps the Ultra Error Manager services within Laravel.
 * Focuses on Dependency Injection for creating ErrorManager and its default handlers,
 * ensuring testability and clear dependency resolution without static facades internally.
 *
 * 🧱 Structure:
 * - Merges package configuration.
 * - Registers TestingConditionsManager singleton.
 * - Registers singleton bindings for default Error Handlers, injecting their specific dependencies (ULM, Mailer, Config, etc.).
 * - Registers the main ErrorManager singleton ('ultra.error-manager'), injecting ULM, Translator, Config, and dynamically registering resolved handlers.
 * - Registers core Middleware.
 * - Binds ErrorManagerInterface to the concrete implementation.
 * - Handles asset publishing and loading routes, migrations, translations, views in boot().
 *
 * 📡 Communicates:
 * - With Laravel's IoC container ($app) to resolve dependencies (ULM, Translator, Mailer, PSR Logger, Config, etc.) and register services.
 * - With the Filesystem during publishing in boot().
 * - With the Router to alias middleware.
 *
 * 🧪 Testable:
 * - Service registration logic testable via Application container.
 * - Dependencies for handlers are explicitly resolved, improving test setup.
 * - Boot logic standard for Laravel packages.
 */
final class UltraErrorManagerServiceProvider extends ServiceProvider 
{
    /**
     * 🎯 Register UEM services using Dependency Injection.
     * Ensures ErrorManager and its Handlers are instantiated correctly via the container.
     *
     * @return void
     */
    public function register(): void
    {
        $configKey = 'error-manager';
        $this->mergeConfigFrom(__DIR__.'/../../config/error-manager.php', $configKey);

        $this->app->singleton(TestingConditionsManager::class, function (Application $app) {
             return new TestingConditionsManager($app); // Use public constructor
        });
        // Alias for facade/helper resolution
        $this->app->alias(TestingConditionsManager::class, 'ultra.testing-conditions');

        $this->registerHandlers($configKey);

        $this->app->singleton('ultra.error-manager', function (Application $app) use ($configKey) {
            $ulmLogger = $app->make(UltraLogManager::class);
            $translator = $app->make(TranslatorContract::class);
            $request = $app->make(Request::class); // Resolve Request
            $config = $app['config'][$configKey] ?? [];

            $manager = new ErrorManager($ulmLogger, $translator, $request, $config); // Pass Request

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
                        // 'trace' => $e->getTraceAsString(), // Consider logging trace only in dev
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
        $this->app->singleton(LogHandler::class, function (Application $app) {
            return new LogHandler($app->make(UltraLogManager::class));
        });

        $this->app->singleton(EmailNotificationHandler::class, function (Application $app) use ($configKey) {
            return new EmailNotificationHandler(
                $app->make(MailerContract::class),
                $app->make(UltraLogManager::class), // Inject Logger for internal logging
                $app->make(Request::class),        // Inject Request
                $app->make(AuthFactory::class),    // Inject AuthFactory
                $app['config'][$configKey]['email_notification'] ?? [],
                $app['config']['app']['name'] ?? 'Laravel',
                $app->environment()
            );
        });

        $this->app->singleton(UserInterfaceHandler::class, function ($app) { // $app è Application
            $session = $app->make(\Illuminate\Contracts\Session\Session::class); // Usa l'interfaccia
            $uiConfig = $app['config']->get('error-manager.ui', []); // Recupera l'array
            return new UserInterfaceHandler($session, $uiConfig);
        });

         $this->app->singleton(DatabaseLogHandler::class, function (Application $app) use ($configKey) {
             $ulmLogger = $app->make(UltraLogManager::class);
             $dbConfig = $app['config'][$configKey]['database_logging'] ?? [];
             return new DatabaseLogHandler($ulmLogger, $dbConfig);
         });

         $this->app->singleton(RecoveryActionHandler::class, function (Application $app) {
             $ulmLogger = $app->make(UltraLogManager::class);
             // Inject other dependencies for recovery actions here if needed
             return new RecoveryActionHandler($ulmLogger);
         });

         $this->app->singleton(SlackNotificationHandler::class, function (Application $app) use ($configKey) {
             $httpClientFactory = $app->make(HttpClientFactory::class);
             $slackConfig = $app['config'][$configKey]['slack_notification'] ?? [];
             $ulmLogger = $app->make(UltraLogManager::class);
             $request = $app->make(Request::class);
             $appName = $app['config']['app']['name'] ?? 'Laravel'; // Get App Name
             $environment = $app->environment(); // Get Environment
             return new SlackNotificationHandler($httpClientFactory, $ulmLogger, $request, $slackConfig, $appName, $environment); // Pass AppName & Env
         });

        $this->app->singleton(ErrorSimulationHandler::class, function (Application $app) {
            $testingManager = $app->make(TestingConditionsManager::class); // Use class name for resolution
            $ulmLogger = $app->make(UltraLogManager::class);
            return new ErrorSimulationHandler($app, $testingManager, $ulmLogger); // Pass $app
        });
    }

    /**
     * 🧱 Provides a default set of handlers if config is empty.
     *
     * @return array<class-string<ErrorHandlerInterface>>
     */
    protected function getDefaultHandlerSet(): array
    {
        $handlers = [
            LogHandler::class,
            DatabaseLogHandler::class,
            EmailNotificationHandler::class,
            SlackNotificationHandler::class,
            UserInterfaceHandler::class,
            RecoveryActionHandler::class,
        ];

        if ($this->app->environment() !== 'production') {
            $handlers[] = ErrorSimulationHandler::class;
        }
        return $handlers;
    }

    /**
     * 🎯 Bootstrap UEM services.
     * Loads routes, migrations, translations, views, and registers middleware.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'error-manager');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'error-manager');

        $this->mergeConfigFrom(__DIR__ . '/../../config/logging.php', 'logging.channels');

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('error-handling', ErrorHandlingMiddleware::class);
        $router->aliasMiddleware('environment', EnvironmentMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * 🧱 Console-specific booting (publishing assets).
     * @return void
     */
    protected function bootForConsole(): void
    {
        $this->publishes([__DIR__.'/../../config/error-manager.php' => $this->app->configPath('error-manager.php')], 'error-manager-config');
        $this->publishes([__DIR__.'/../../resources/views' => $this->app->resourcePath('views/vendor/error-manager')], 'error-manager-views');
        $this->publishes([__DIR__.'/../../resources/lang' => $this->app->langPath('vendor/error-manager')], 'error-manager-language');
        $this->publishes([__DIR__.'/../../database/migrations' => $this->app->databasePath('migrations')], 'error-manager-migrations');
    }
}