<?php

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Support\Facades\Log;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Models\ErrorLog;

/**
 * Database Log Handler
 *
 * This handler logs errors to the database for tracking and reporting.
 *
 * @package Ultra\ErrorManager\Handlers
 */
class DatabaseLogHandler implements ErrorHandlerInterface
{
    /**
     * Determine if this handler should handle the error
     *
     * @param array $errorConfig Error configuration
     * @return bool True if this handler should process the error
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Check if database logging is enabled in config
        return config('error-manager.database_logging.enabled', true);
    }

    /**
     * Handle the error by logging it to the database
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], \Throwable $exception = null): void
    {
        try {
            // Prepare data for database record
            $data = [
                'error_code' => $errorCode,
                'type' => $errorConfig['type'] ?? 'error',
                'blocking' => $errorConfig['blocking'] ?? 'not',
                'message' => $errorConfig['dev_message'] ?? ($errorConfig['dev_message_key'] ?? null),
                'user_message' => $errorConfig['user_message'] ?? ($errorConfig['user_message_key'] ?? null),
                'http_status_code' => $errorConfig['http_status_code'] ?? 500,
                'context' => $this->sanitizeContext($context),
                'display_mode' => $errorConfig['msg_to'] ?? 'div',
            ];

            // Add exception details if available
            if ($exception) {
                $data['exception_class'] = get_class($exception);
                $data['exception_message'] = $exception->getMessage();
                $data['exception_file'] = $exception->getFile();
                $data['exception_line'] = $exception->getLine();

                // Store truncated stack trace if enabled
                if (config('error-manager.database_logging.include_trace', true)) {
                    $data['exception_trace'] = $this->truncateTrace($exception->getTraceAsString());
                }
            }

            // Create the log entry
            $errorLog = ErrorLog::create($data);

            Log::debug("Ultra Error Manager: Error logged to database with ID [{$errorLog->id}]");

            return;
        } catch (\Exception $e) {
            // Don't let an exception in the error handler cause more problems
            Log::error("Ultra Error Manager: Failed to log error to database", [
                'error_code' => $errorCode,
                'database_exception' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ]);
        }
    }

    /**
     * Sanitize context data for database storage
     *
     * @param array $context
     * @return array
     */
    protected function sanitizeContext(array $context): array
    {
        // Remove sensitive data from context before storing
        $sensitiveKeys = ['password', 'secret', 'token', 'auth', 'key', 'credentials'];

        $sanitized = [];
        foreach ($context as $key => $value) {
            // Skip sensitive keys
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            // Handle nested arrays
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
                continue;
            }

            // Handle scalar values
            if (is_scalar($value) || is_null($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            // For objects and resources, store class name or resource type
            if (is_object($value)) {
                $sanitized[$key] = '[Object: ' . get_class($value) . ']';
            } elseif (is_resource($value)) {
                $sanitized[$key] = '[Resource: ' . get_resource_type($value) . ']';
            } else {
                $sanitized[$key] = '[Unserializable data]';
            }
        }

        return $sanitized;
    }

    /**
     * Truncate stack trace to a reasonable length for database storage
     *
     * @param string $trace
     * @return string
     */
    protected function truncateTrace(string $trace): string
    {
        $maxLength = config('error-manager.database_logging.max_trace_length', 10000);

        if (strlen($trace) <= $maxLength) {
            return $trace;
        }

        return substr($trace, 0, $maxLength) . "\n[Truncated...]";
    }
}
