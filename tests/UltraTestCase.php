<?php

namespace Ultra\ErrorManager\Tests;

use Mockery;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider;
use Ultra\UltraLogManager\Providers\UltraLogManagerServiceProvider;
use Ultra\TranslationManager\Providers\UltraTranslationManagerServiceProvider;

/**
 * 📜 Oracode Base Test Class: UltraTestCase
 *
 * Provides the base testing environment for UltraErrorManager, configuring Laravel
 * and package dependencies, in-memory SQLite database, and test-specific settings.
 * Ensures isolated and repeatable tests for unit and integration scenarios.
 *
 * @package         Ultra\ErrorManager\Tests
 * @version         0.1.0 // Initial structure
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 UltraTestCase – Oracoded Base Test Class for UltraErrorManager
 *
 * Sets up the testing environment for UltraErrorManager, loading service providers,
 * configuring an in-memory SQLite database, and defining migrations. Provides cleanup
 * hooks to ensure test isolation and dependency mocking via Mockery.
 *
 * 🧱 Structure:
 * - Extends Orchestra\Testbench\TestCase for Laravel package testing.
 * - Configures UltraErrorManager and dependency service providers.
 * - Initializes in-memory SQLite database and migrations.
 *
 * 📡 Communicates:
 * - With Laravel application for configuration and providers.
 * - With in-memory SQLite database for test data persistence.
 * - With Mockery for dependency mocking.
 *
 * 🧪 Testable:
 * - Supports unit and integration tests with isolated environment.
 * - Ensures clean database state via migrations and cleanup.
 */
abstract class UltraTestCase extends OrchestraTestCase
{
    /**
     * 🎯 Load Package Providers
     * 🧪 Strategy: Register UltraErrorManager and dependency service providers for the test environment.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            UltraLogManagerServiceProvider::class,
            // UltraTranslationManagerServiceProvider::class, // Uncomment if Translator is injected
            UltraErrorManagerServiceProvider::class,
            \Illuminate\Mail\MailServiceProvider::class,
            // \Illuminate\Session\SessionServiceProvider::class, // Uncomment if Session is needed
        ];
    }

    /**
     * 🎯 Define Test Environment
     * 🧪 Strategy: Configure application settings, in-memory database, cache, and mail for testing.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Application Settings
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.debug', true);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Database Settings
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Cache Settings
        $app['config']->set('cache.default', 'array');

        // Mail Settings
        $app['config']->set('mail.driver', 'log');
        $app['config']->set('mail.mailer', 'log');
        $app['config']->set('mail.from.address', 'errors@example.com');
        $app['config']->set('mail.from.name', 'UEM Test');

        // UltraErrorManager Settings
        $app['config']->set('error-manager.email_notification.enabled', true);
        $app['config']->set('error-manager.email_notification.to', 'test@example.com');
        $app['config']->set('error-manager.slack_notification.enabled', false);
        $app['config']->set('error-manager.database_logging.enabled', true);
    }

    /**
     * 🎯 Define Database Migrations
     * 🧪 Strategy: Load migrations for error_logs table to support database tests.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /**
     * 🎯 Setup Test Environment
     * 🧪 Strategy: Initialize base test environment by calling parent setup.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * 🎯 Cleanup Test Environment
     * 🧪 Strategy: Restore error and exception handlers, close Mockery, and call parent teardown.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        Mockery::close();
        parent::tearDown();
    }
}