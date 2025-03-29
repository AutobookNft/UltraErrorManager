<?php

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Support\Facades\Log;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Services\TestingConditionsManager;

/**
 * Error Simulation Handler
 *
 * This handler integrates with TestingConditionsManager to provide
 * error simulation capabilities for testing and development.
 *
 * @package Ultra\ErrorManager\Handlers
 */
class ErrorSimulationHandler implements ErrorHandlerInterface
{
    /**
     * Testing conditions manager instance
     *
     * @var TestingConditionsManager
     */
    protected $testingManager;

    /**
     * Constructor
     *
     * @param TestingConditionsManager $testingManager
     */
    public function __construct(TestingConditionsManager $testingManager)
    {
        $this->testingManager = $testingManager;
    }

    /**
     * Determine if this handler should handle the error
     *
     * @param array $errorConfig Error configuration
     * @return bool True if this handler should process the error
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Only handle in non-production environments
        return app()->environment() !== 'production';
    }

    /**
     * Handle the error by logging simulation info
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], \Throwable $exception = null): void
    {
        // Log error simulation for analysis or debugging
        Log::channel('testing')->info("Error simulation: {$errorCode}", [
            'config' => $errorConfig,
            'context' => $context,
            'simulated' => $this->testingManager->isTesting($errorCode)
        ]);
    }

    /**
     * Simulate a specific error
     *
     * @param string $errorCode Error code to simulate
     * @param array $context Additional context for the error
     * @return mixed Response from error handler
     */
    public function simulateError(string $errorCode, array $context = [])
    {
        // Set the testing condition to true for this error code
        $this->testingManager->setCondition($errorCode, true);

        // Log the simulation activation
        Log::info("Activating error simulation for [{$errorCode}]", $context);

        // Return the error code for potential checks in code
        return $errorCode;
    }

    /**
     * Stop simulating a specific error
     *
     * @param string $errorCode Error code to stop simulating
     * @return void
     */
    public function stopSimulatingError(string $errorCode)
    {
        $this->testingManager->setCondition($errorCode, false);
        Log::info("Deactivating error simulation for [{$errorCode}]");
    }

    /**
     * Check if an error is currently being simulated
     *
     * @param string $errorCode Error code to check
     * @return bool True if the error is being simulated
     */
    public function isSimulatingError(string $errorCode)
    {
        return $this->testingManager->isTesting($errorCode);
    }
}
