<?php

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Support\Facades\Log;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;

/**
 * Log Handler
 *
 * This handler is responsible for logging errors to the appropriate
 * logging channel with the correct severity level.
 *
 * @package Ultra\ErrorManager\Handlers
 */
class LogHandler implements ErrorHandlerInterface
{
    /**
     * Determine if this handler should handle the error
     *
     * @param array $errorConfig Error configuration
     * @return bool True if this handler should process the error
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // By default, we log all errors
        return true;
    }

    /**
     * Handle the error by logging it appropriately
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?\Throwable $exception = null): void
    {
        // Determine the appropriate log level based on error type
        $logLevel = $this->getLogLevel($errorConfig['type'] ?? 'error');

        // Prepare log message and context
        $message = "[{$errorCode}] " . ($errorConfig['dev_message'] ?? $errorConfig['message'] ?? 'Error occurred');

        $logContext = $this->prepareLogContext($errorCode, $errorConfig, $context, $exception);

        // Get the configured log channel or use default
        $channel = config('error-manager.logging.channel', 'stack');

        // Log the error with the appropriate level
        Log::channel($channel)->$logLevel($message, $logContext);
    }

    /**
     * Map error type to log level
     *
     * @param string $errorType The type of error (critical, error, warning, notice)
     * @return string The corresponding log level method name
     */
    protected function getLogLevel(string $errorType): string
    {
        $mapping = [
            'critical' => 'critical',
            'error' => 'error',
            'warning' => 'warning',
            'notice' => 'notice',
            'info' => 'info',
        ];

        return $mapping[$errorType] ?? 'error';
    }

    /**
     * Prepare the context array for logging
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return array The log context data
     */
    protected function prepareLogContext(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception): array
    {
        $logContext = [
            'error_code' => $errorCode,
            'error_type' => $errorConfig['type'] ?? 'error',
            'blocking' => $errorConfig['blocking'] ?? 'blocking',
        ];

        // Include detailed context if configured
        if (config('error-manager.logging.detailed_context', true)) {
            $logContext['context'] = $context;
        }

        // Add exception information if present and trace logging is enabled
        if ($exception && config('error-manager.logging.include_trace', true)) {
            $logContext['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return $logContext;
    }
}
