<?php

namespace Ultra\ErrorManager\Interfaces;

/**
 * Interface for all error handlers
 *
 * This interface must be implemented by all error handlers that can be
 * registered with the ErrorManager.
 *
 * @package Ultra\ErrorManager\Interfaces
 */
interface ErrorHandlerInterface
{
    /**
     * Determine if this handler should handle the error
     *
     * @param array $errorConfig Error configuration
     * @return bool True if this handler should process the error
     */
    public function shouldHandle(array $errorConfig): bool;

    /**
     * Handle the error
     *
     * This method contains the actual error handling logic.
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], \Throwable $exception = null): void;
}
