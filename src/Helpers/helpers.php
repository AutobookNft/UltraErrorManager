<?php

use Ultra\ErrorManager\Facades\UltraError;
use Ultra\ErrorManager\Facades\TestingConditions;

if (!function_exists('ultra_error')) {
    /**
     * Handle an error through the Ultra Error Manager
     *
     * @param string $errorCode Error code
     * @param array $context Additional context for the error
     * @param \Throwable|null $exception Original exception if available
     * @return mixed Response from error handler
     */
    function ultra_error($errorCode, array $context = [], \Throwable $exception = null)
    {
        return UltraError::handle($errorCode, $context, $exception);
    }
}

if (!function_exists('simulate_error')) {
    /**
     * Simulate a specific error condition
     *
     * @param string $errorCode Error code to simulate
     * @param bool $active Whether to activate or deactivate the simulation
     * @return bool Current simulation state
     */
    function simulate_error($errorCode, $active = true)
    {
        TestingConditions::setCondition($errorCode, $active);
        return TestingConditions::isTesting($errorCode);
    }
}

if (!function_exists('is_simulating_error')) {
    /**
     * Check if a specific error is being simulated
     *
     * @param string $errorCode Error code to check
     * @return bool True if error is being simulated
     */
    function is_simulating_error($errorCode)
    {
        return TestingConditions::isTesting($errorCode);
    }
}

if (!function_exists('get_active_error_simulations')) {
    /**
     * Get all active error simulations
     *
     * @return array Active error simulation codes
     */
    function get_active_error_simulations()
    {
        return TestingConditions::getActiveConditions();
    }
}
