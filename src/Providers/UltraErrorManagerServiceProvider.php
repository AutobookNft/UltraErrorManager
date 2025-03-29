<?php

namespace Ultra\ErrorManager\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Ultra\ErrorManager\ErrorManager;
use Ultra\ErrorManager\Handlers\LogHandler;
use Ultra\ErrorManager\Handlers\EmailNotificationHandler;
use Ultra\ErrorManager\Handlers\UserInterfaceHandler;
use Ultra\ErrorManager\Handlers\ErrorSimulationHandler;
use Ultra\ErrorManager\Handlers\RecoveryActionHandler;
use Ultra\ErrorManager\Handlers\DatabaseLogHandler;
use Ultra\ErrorManager\Handlers\SlackNotificationHandler;
use Ultra\ErrorManager\Services\TestingConditionsManager;
use Ultra\ErrorManager\Http\Middleware\ErrorHandlingMiddleware;
use Ultra\ErrorManager\Http\Middleware\EnvironmentMiddleware;

class UltraErrorManagerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register TestingConditionsManager singleton
        $this->app->singleton('ultra.testing-conditions', function ($app) {
            return TestingConditionsManager::getInstance();
        });

        // Register ErrorManager singleton
        $this->app->singleton('ultra.error-manager', function ($app) {
            $manager = new ErrorManager();

            // Register default handlers based on config
            $defaultHandlers = config('error-manager.default_handlers', []);

            // If the default handlers array is empty, register our basic set
            if (empty($defaultHandlers)) {
                $manager->registerHandler(new LogHandler());
                $manager->registerHandler(new EmailNotificationHandler());
                $manager->registerHandler(new UserInterfaceHandler());
                $manager->registerHandler(new DatabaseLogHandler());
                $manager->registerHandler(new RecoveryActionHandler());
                $manager->registerHandler(new SlackNotificationHandler());

                // Register ErrorSimulationHandler if not in production
                if (app()->environment() !== 'production') {
                    $manager->registerHandler(new ErrorSimulationHandler(
                        $app->make('ultra.testing-conditions')
                    ));
                }
            } else {
                // Register handlers from configuration
                foreach ($defaultHandlers as $handlerClass) {
                    if (class_exists($handlerClass)) {
                        $manager->registerHandler(new $handlerClass());
                    } else {
                        Log::warning("Ultra Error Manager: Handler class not found [{$handlerClass}]");
                    }
                }
            }

            return $manager;
        });

        // Register middleware
        $this->app->singleton(ErrorHandlingMiddleware::class);
        $this->app->singleton(EnvironmentMiddleware::class);

        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/error-manager.php', 'error-manager'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'error-manager');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'error-manager');
        $this->mergeConfigFrom(__DIR__ . '/../../config/logging.php', 'logging');

        // Register middlewares in router
        $router = $this->app['router'];
        $router->aliasMiddleware('error-handling', ErrorHandlingMiddleware::class);
        $router->aliasMiddleware('environment', EnvironmentMiddleware::class);

        // Publishing is only necessary when using the CLI
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Console-specific booting.
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file
        $this->publishes([
            __DIR__.'/../../config/error-manager.php' => config_path('error-manager.php'),
        ], 'error-manager-config');

        // Publishing the views
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/error-manager'),
        ], 'error-manager-views');

        // Publishing the translations
        $this->publishes([
            __DIR__.'/../../resources/lang' => resource_path('lang/vendor/error-manager'),
        ], 'error-manager-language');

        // Publishing assets (CSS, JS, images)
        $this->publishes([
            __DIR__.'/../../public' => public_path('vendor/error-manager'),
        ], 'error-manager-assets');

        // Publishing the migration files
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'error-manager-migrations');
    }
}
