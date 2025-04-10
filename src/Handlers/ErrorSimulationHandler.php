<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Contracts\Foundation\Application; // Dependency for environment check
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Services\TestingConditionsManager; // Dependency for checking conditions
use Ultra\UltraLogManager\UltraLogManager; // Dependency for internal logging
use Throwable; // Import Throwable

/**
 * 🎯 ErrorSimulationHandler – Oracoded Simulation State Logger
 *
 * Logs information about handled errors specifically when running in a non-production
 * environment and error simulations might be active. It checks the TestingConditionsManager
 * to record whether the currently handled error was actively being simulated.
 * This handler DOES NOT activate/deactivate simulations; it only reports their state during error handling.
 *
 * 🧱 Structure:
 * - Implements ErrorHandlerInterface.
 * - Requires Application, TestingConditionsManager, and UltraLogManager injected via constructor.
 * - `shouldHandle` only returns true in non-production environments.
 * - `handle` logs the error code, config, context, and the simulation status for that code.
 *
 * 📡 Communicates:
 * - With TestingConditionsManager to check simulation status.
 * - With UltraLogManager to log simulation-related information.
 * - With Application contract to check the environment.
 *
 * 🧪 Testable:
 * - All dependencies (App, TestingConditionsManager, ULM Logger) are injectable.
 * - Logic is straightforward logging based on dependencies' state.
 *
 * 🛡️ GDPR Considerations:
 * - Operates only in non-production environments (guard in `shouldHandle`).
 * - Logs `$context` data (`@data-input`) passed to it. While acceptable in dev/staging,
 *   ensure sensitive production data is not inadvertently used in these environments
 *   if context logging is enabled in ULM for the target channel. `@log` tag applies.
 */
final class ErrorSimulationHandler implements ErrorHandlerInterface
{
    /**
     * 🧱 @dependency Application contract instance.
     * Used to check the current environment.
     * @var Application
     */
    protected readonly Application $app;

    /**
     * 🧱 @dependency Testing conditions manager instance.
     * Used to check if the current error code is being simulated.
     * @var TestingConditionsManager
     */
    protected readonly TestingConditionsManager $testingManager;

    /**
     * 🧱 @dependency UltraLogManager instance.
     * Used for logging simulation information.
     * @var UltraLogManager
     */
    protected readonly UltraLogManager $ulmLogger;

    /**
     * 🎯 Constructor: Injects required dependencies.
     *
     * @param Application $app Laravel Application instance.
     * @param TestingConditionsManager $testingManager Singleton instance managing test conditions.
     * @param UltraLogManager $ulmLogger Logger for internal handler operations.
     */
    public function __construct(
        Application $app,
        TestingConditionsManager $testingManager,
        UltraLogManager $ulmLogger
    ) {
        $this->app = $app;
        $this->testingManager = $testingManager;
        $this->ulmLogger = $ulmLogger;
    }

    /**
     * 🧠 Determine if this handler should handle the error.
     * Only active in non-production environments to avoid logging simulation
     * data in production. Uses injected Application contract.
     *
     * @param array $errorConfig Resolved error configuration (not used directly here).
     * @return bool True if the environment is not 'production'.
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Use injected app instance to check environment
        return $this->app->environment() !== 'production';
    }

    /**
     * 🔬 Handle the error by logging simulation-specific information via ULM.
     * 📥 @data-input (Via $context and $exception, logged)
     * 🪵 @log (Logs simulation details)
     *
     * @param string $errorCode The symbolic error code.
     * @param array $errorConfig The configuration metadata for the error.
     * @param array $context Contextual data.
     * @param Throwable|null $exception Optional original throwable.
     * @return void
     */    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        // Check if this specific error code is currently being simulated
        $isSimulated = $this->testingManager->isTesting($errorCode);

        // Log relevant information using the injected ULM logger
        // Consider using a specific log channel if ULM supports it and it's configured,
        // otherwise, use a distinct message prefix or context key.
        $this->ulmLogger->info("UEM SimulationHandler: Error handled.", [
            'errorCode'    => $errorCode,
            'isSimulated'  => $isSimulated, // Log whether the condition was active
            'errorConfig'  => $errorConfig, // Log config for context
            'context'      => $context,     // Log original context
            'exception'    => $exception ? get_class($exception) : null, // Log exception type if present
        ]);
    }

    // Removed simulateError, stopSimulatingError, isSimulatingError methods.
    // This handler's responsibility is to LOG simulation info during error handling,
    // not to MANAGE the simulation state itself. State management is done via
    // TestingConditionsManager directly or its Facade.
}