<?php

/**
 * 📜 Oracode Provider Test: UltraErrorManagerServiceProviderTest
 *
 * @package         Ultra\ErrorManager\Tests\Providers
 * @version         1.0.2 // Final clean version with full Oracode compliance.
 * @author          Padmin D. Curtis <fabiocherici@gmail.com> (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 */

namespace Ultra\ErrorManager\Tests\Providers;

// --- Core Dependencies ---
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;
use TypeError;

// --- Framework Dependencies ---
use Ultra\ErrorManager\Tests\UltraTestCase;

// --- Package Specific ---
use Ultra\ErrorManager\Handlers\UserInterfaceHandler;
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider;

// --- Other Ultra Dependencies ---
use Ultra\TranslationManager\Providers\UltraTranslationServiceProvider;
use Ultra\UltraLogManager\Providers\UltraLogManagerServiceProvider;
use PHPUnit\Framework\Attributes\UsesClass;   // Per indicare classi USATE dal test
use PHPUnit\Framework\Attributes\UsesMethod;  // Per indicare metodi USATI dal test
// --- End Imports ---

/**
 * 🎯 Purpose: Verifies the correct service container bindings and registrations
 *    performed by the UltraErrorManagerServiceProvider. Ensures key components
 *    are correctly bound with their dependencies.
 *
 * 🧱 Structure: Extends UltraTestCase (using Testbench). Loads necessary providers.
 *    Defines minimal configuration for the environment.
 *
 * 📡 Interaction: Interacts solely with the Laravel service container ($this->app)
 *    to test dependency resolution. No external network or filesystem calls.
 *
 * 🧪 Testability: Focused integration test verifying the provider's interaction
 *    with the Laravel container. Dependencies are configured minimally for the test context.
 */

 #[CoversClass(UltraErrorManagerServiceProvider::class)]
 #[UsesClass(UserInterfaceHandler::class)] // La classe che viene istanziata
final class UltraErrorManagerServiceProviderTest extends UltraTestCase
{
    /**
     * ⚙️ Loads the necessary Service Providers for these tests.
     * Includes UEM itself and its direct dependencies (UTM, ULM) required for booting.
     *
     * @param \Illuminate\Foundation\Application $app The application instance.
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>> Array of Service Provider class strings.
     */
    protected function getPackageProviders($app): array
    {
        return [
            // Dependencies first
            UltraTranslationServiceProvider::class,
            UltraLogManagerServiceProvider::class,
            // The Service Provider under test
            UltraErrorManagerServiceProvider::class,
        ];
    }

    /**
     * ⚙️ Defines the minimal environment configuration required for the Service Provider
     *    to register its bindings without throwing configuration errors during the test setup.
     *
     * @param \Illuminate\Foundation\Application $app The application instance.
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Minimal UEM config required by the provider during registration/boot
        Config::set('error-manager.ui', [
             'display_mode' => 'flash',
             'show_error_code' => false,
             'generic_message_key' => 'error-manager::errors.user.fallback_error', // Example key
        ]);
        Config::set('error-manager.default_handlers', [
            UserInterfaceHandler::class, // Ensure the handler is expected
             // Add other handlers if the provider iterates/binds them
        ]);
        // Disable external/db handlers for this binding test
        Config::set('error-manager.database_logging.enabled', false);
        Config::set('error-manager.email_notification.enabled', false);
        Config::set('error-manager.slack_notification.enabled', false);

        // Minimal config for Dependencies
        Config::set('translation-manager.default_locale', 'en');
        Config::set('translation-manager.available_locales', ['en']);
        Config::set('translation-manager.fallback_locale', 'en');
        Config::set('translation-manager.cache_enabled', false);
        Config::set('ultra_log_manager.log_channel', 'null');
        Config::set('logging.channels.null.driver', 'monolog');
        Config::set('logging.channels.null.handler', \Monolog\Handler\NullHandler::class);
    }

    // --- Test Methods ---

    /**
     * ⛓️ Oracular Behavior: Verify UserInterfaceHandler Binding (Addresses Bug #UEM-SP-DI-01).
     *
     * Ensures that UserInterfaceHandler is correctly bound in the service container
     * by the UltraErrorManagerServiceProvider with an array for its `$uiConfig` dependency.
     * This specifically tests against the bug where TranslationManager was incorrectly injected.
     *
     * 🎯 Target: Binding resolution of UserInterfaceHandler via UltraErrorManagerServiceProvider.
     * 🧪 Strategy: Resolve UserInterfaceHandler from the container ($this->app->make). Assert the instance type.
     *              The test will fail if the underlying provider code causes a TypeError during resolution.
     *
     * @see https://github.com/YourOrg/UltraErrorManager/issues/XX (Replace with actual issue tracker link/ID)
     * @return void
     * @throws BindingResolutionException If the container cannot resolve the handler.
     * @throws TypeError If the handler is constructed with incorrect dependency types (the bug).
     * @throws Throwable For any other unexpected errors during the test.
     */
    #[Test]
    #[Group('oracular')]
    #[Group('bugfix')]
    #[Group('serviceprovider')]
    public function userInterfaceHandler_isBoundWithCorrectDependencies(): void
    {
        try {
            // Attempt to resolve the handler from the container
            $handler = $this->app->make(UserInterfaceHandler::class);

            // Assert that the resolved object is an instance of UserInterfaceHandler
            $this->assertInstanceOf(
                UserInterfaceHandler::class,
                $handler,
                "Failed to resolve a valid UserInterfaceHandler instance. Check ServiceProvider binding logic."
            );

        } catch (TypeError $e) {
            // If the specific TypeError related to the bug occurs, fail clearly.
            if (str_contains($e->getMessage(), 'must be of type array') && str_contains($e->getMessage(), 'TranslationManager given')) {
                 $this->fail('[BUG CONFIRMED] Service Provider injected TranslationManager instead of config array for UserInterfaceHandler. Error: ' . $e->getMessage());
            }
            // Fail for any other unexpected TypeError.
            $this->fail('Unexpected TypeError during resolution: ' . $e->getMessage());

        } catch (BindingResolutionException $e) {
             // Check if the root cause was the TypeError we are looking for.
             $previous = $e->getPrevious();
             if ($previous instanceof TypeError && str_contains($previous->getMessage(), 'must be of type array') && str_contains($previous->getMessage(), 'TranslationManager given')) {
                 $this->fail('[BUG CONFIRMED via BindingResolutionException] Service Provider injected TranslationManager instead of config array. Error: ' . $previous->getMessage());
             }
             // Fail for other unexpected resolution errors.
             $this->fail('Unexpected BindingResolutionException: ' . $e->getMessage());

        } catch (Throwable $e) {
            // Catch any other unexpected exception.
            $this->fail('Unexpected Throwable caught during test execution: ' . get_class($e) . ' - ' . $e->getMessage());
        }
    }

    // Add other tests for UltraErrorManagerServiceProvider bindings here if needed.

} // End class UltraErrorManagerServiceProviderTest