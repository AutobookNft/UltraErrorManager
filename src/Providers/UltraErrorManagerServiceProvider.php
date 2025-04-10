<?php

declare(strict_types=1); // Strict types

namespace Ultra\ErrorManager\Providers;

// Core Laravel contracts & classes
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Mail\Mailer as MailerContract; // For Email Handler
use Illuminate\Contracts\Translation\Translator as TranslatorContract; // For ErrorManager
use Illuminate\Http\Request; // Potential dependency for handlers needing request data
use Illuminate\Routing\Router; // For middleware alias
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface as PsrLoggerInterface; // Standard logger

// UEM specific classes & interfaces
use Ultra\ErrorManager\ErrorManager;
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface; // Import interface
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface; // Import interface
// Handlers
use Ultra\ErrorManager\Handlers\LogHandler; // Needs ULM
use Ultra\ErrorManager\Handlers\EmailNotificationHandler; // Needs MailerContract, Config
use Ultra\ErrorManager\Handlers\UserInterfaceHandler; // May need Session or Request? (Minimal deps for now)
use Ultra\ErrorManager\Handlers\ErrorSimulationHandler; // Needs TestingConditionsManager
use Ultra\ErrorManager\Handlers\RecoveryActionHandler; // Might need specific services later
use Ultra\ErrorManager\Handlers\DatabaseLogHandler; // Needs ULM? Or just Model? (Assume model uses default connection)
use Ultra\ErrorManager\Handlers\SlackNotificationHandler; // Needs HttpClient, Config
// Services
use Ultra\ErrorManager\Services\TestingConditionsManager;
// Middleware
use Ultra\ErrorManager\Http\Middleware\ErrorHandlingMiddleware;
use Ultra\ErrorManager\Http\Middleware\EnvironmentMiddleware;

// Dependencies from other Ultra packages
use Ultra\UltraLogManager\UltraLogManager; // Needed by LogHandler, DatabaseLogHandler, maybe others

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
final class UltraErrorManagerServiceProvider extends ServiceProvider // Mark as final
{
    /**
     * 🎯 Register UEM services using Dependency Injection.
     * Ensures ErrorManager and its Handlers are instantiated correctly via the container.
     *
     * @return void
     */
    public function register(): void
    {
        // 1. Merge Configuration (Key for accessing config)
        $configKey = 'error-manager';
        $this->mergeConfigFrom(__DIR__.'/../../config/error-manager.php', $configKey);

        // 2. Register TestingConditionsManager Singleton (Dependency for ErrorSimulationHandler)
        $this->app->singleton('ultra.testing-conditions', function (Application $app) {
            // Assuming getInstance manages singleton logic internally for now
            // A potential improvement could be making its constructor public and removing getInstance
            return TestingConditionsManager::getInstance();
        });

        // 3. Register Default Error Handlers as Singletons (using $app->make for dependencies)
        $this->registerHandlers($configKey);

        // 4. Register Main ErrorManager Singleton ('ultra.error-manager')
        $this->app->singleton('ultra.error-manager', function (Application $app) use ($configKey) {
            // Resolve core dependencies for ErrorManager
            $ulmLogger = $app->make(UltraLogManager::class); // ULM instance
            $translator = $app->make(TranslatorContract::class); // UTM instance (bound to TranslatorContract)
            $config = $app['config'][$configKey] ?? []; // Get merged config

            // Instantiate ErrorManager with core dependencies
            $manager = new ErrorManager($ulmLogger, $translator, $config);

            // Get the list of configured default handler classes
            $defaultHandlerClasses = $config['default_handlers'] ?? $this->getDefaultHandlerSet();

            // Register handlers by resolving them from the container
            foreach ($defaultHandlerClasses as $handlerClass) {
                try {
                    // Check if the class exists before trying to make it
                    if (class_exists($handlerClass)) {
                        // Resolve the handler instance from the container
                        // This ensures its dependencies (like ULM, Mailer) are injected
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
                     // Log error if a handler cannot be resolved/instantiated
                     $ulmLogger->error("UEM Provider: Failed to register handler {$handlerClass}", [
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(), // Optional: consider logging trace only in dev
                        'provider' => self::class
                     ]);
                }
            }

            return $manager;
        });

        // Bind the interface to the concrete implementation
        $this->app->bind(ErrorManagerInterface::class, 'ultra.error-manager');

        // 5. Register Middleware Singletons
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
        // LogHandler - Depends on UltraLogManager
        $this->app->singleton(LogHandler::class, function (Application $app) {
            return new LogHandler($app->make(UltraLogManager::class));
        });

        // EmailNotificationHandler - Depends on MailerContract and Config array
        $this->app->singleton(EmailNotificationHandler::class, function (Application $app) use ($configKey) {
            return new EmailNotificationHandler(
                $app->make(MailerContract::class),
                $app['config'][$configKey]['email_notification'] ?? [], // Pass specific email config section
                $app['config']['app']['name'] ?? 'Laravel', // Pass app name
                $app->environment() // Pass environment
                // Note: Assumes EmailNotificationHandler constructor accepts these. Needs verification/update.
            );
        });

        // UserInterfaceHandler - Currently minimal deps, might need Session/Request later
         $this->app->singleton(UserInterfaceHandler::class, function (Application $app) use ($configKey) {
             // If it needs session or config, inject them here
             // $session = $app->make('session.store');
             $config = $app['config'][$configKey]['ui'] ?? [];
             return new UserInterfaceHandler($config); // Assuming constructor takes UI config
         });

         // DatabaseLogHandler - Assumes ErrorLog model uses default connection. Might need ULM for logging *its own* errors.
         $this->app->singleton(DatabaseLogHandler::class, function (Application $app) use ($configKey) {
             // Inject ULM for internal logging within the handler itself
             $ulmLogger = $app->make(UltraLogManager::class);
             $dbConfig = $app['config'][$configKey]['database_logging'] ?? []; // Pass DB logging specific config
             return new DatabaseLogHandler($ulmLogger, $dbConfig); // Assuming constructor takes Logger & config
         });

         // RecoveryActionHandler - May need specific service dependencies later
         $this->app->singleton(RecoveryActionHandler::class, function (Application $app) {
             // Inject ULM for logging recovery attempts/failures
             $ulmLogger = $app->make(UltraLogManager::class);
             // If specific recovery actions need services (e.g., Storage), inject them here
             // $storage = $app->make(Storage::class);
             return new RecoveryActionHandler($ulmLogger /*, $storage, etc... */); // Assuming constructor takes Logger
         });

         // SlackNotificationHandler - Needs HttpClient (via Http facade usually) and Config
         $this->app->singleton(SlackNotificationHandler::class, function (Application $app) use ($configKey) {
             // Inject HttpClient factory and config
             $httpClientFactory = $app->make(\Illuminate\Http\Client\Factory::class); // Get the factory
             $slackConfig = $app['config'][$configKey]['slack_notification'] ?? [];
             $ulmLogger = $app->make(UltraLogManager::class); // For logging success/failure
             $request = $app->make(Request::class); // To get current request URL
             return new SlackNotificationHandler($httpClientFactory, $ulmLogger, $request, $slackConfig); // Assuming constructor takes these
         });


        // ErrorSimulationHandler - Depends on TestingConditionsManager
        $this->app->singleton(ErrorSimulationHandler::class, function (Application $app) {
             // Resolve the already registered TestingConditionsManager
            $testingManager = $app->make('ultra.testing-conditions');
            // Resolve ULM for internal logging
            $ulmLogger = $app->make(UltraLogManager::class);
            return new ErrorSimulationHandler($testingManager, $ulmLogger); // Assuming constructor takes both
        });

        // Add more handler registrations here if needed...
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
            EmailNotificationHandler::class,
            UserInterfaceHandler::class,
            DatabaseLogHandler::class,
            RecoveryActionHandler::class,
            SlackNotificationHandler::class,
        ];

        // Add simulation handler only if not in production
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
        // Load package resources (no changes needed here usually)
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'error-manager');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'error-manager');

        // Merge logging config (needed for channels defined by UEM)
        // Note: Ensure keys don't conflict badly with app's logging config.
        $this->mergeConfigFrom(__DIR__ . '/../../config/logging.php', 'logging.channels');

        // Register middlewares in router (no change needed)
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('error-handling', ErrorHandlingMiddleware::class);
        $router->aliasMiddleware('environment', EnvironmentMiddleware::class);

        // Publishing (no change needed)
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * 🧱 Console-specific booting (publishing assets).
     * (No changes needed here)
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Configuration
        $this->publishes([
            __DIR__.'/../../config/error-manager.php' => $this->app->configPath('error-manager.php'), // Use helper
        ], 'error-manager-config');

        // Views
        $this->publishes([
            __DIR__.'/../../resources/views' => $this->app->resourcePath('views/vendor/error-manager'), // Use helper
        ], 'error-manager-views');

        // Translations
        $this->publishes([
            __DIR__.'/../../resources/lang' => $this->app->langPath('vendor/error-manager'), // Use helper
        ], 'error-manager-language');

        // Assets (e.g., compiled JS/CSS for dashboard, if any)
        // $this->publishes([
        //     __DIR__.'/../../public' => public_path('vendor/error-manager'),
        // ], 'error-manager-assets');

        // Migrations
        $this->publishes([
            __DIR__.'/../../database/migrations' => $this->app->databasePath('migrations'), // Use helper
        ], 'error-manager-migrations');
    }
}