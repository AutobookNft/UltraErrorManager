<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Models\ErrorLog; // Eloquent Model
use Ultra\UltraLogManager\UltraLogManager; // Dependency: ULM Core Logger
use Throwable; // Import Throwable for exception handling

/**
 * 🎯 DatabaseLogHandler – Oracoded Error Persistence Handler (GDPR Reviewed)
 *
 * Responsible for persisting handled errors to the database using the ErrorLog model.
 * Logs its own operational errors via an injected UltraLogManager instance.
 * Includes context sanitization based on injected configuration to comply with privacy requirements.
 *
 * 🧱 Structure:
 * - Implements ErrorHandlerInterface.
 * - Requires UltraLogManager and a configuration array injected via constructor.
 * - Uses ErrorLog Eloquent model to interact with the database.
 * - Contains logic for sanitizing context (`sanitizeContext`) and truncating stack traces (`truncateTrace`).
 *
 * 📡 Communicates:
 * - With the Database via ErrorLog model.
 * - With UltraLogManager for logging its own operational status/errors.
 *
 * 🧪 Testable:
 * - Depends on injectable UltraLogManager and config array.
 * - Database interaction mockable.
 * - Sanitization/truncation logic testable.
 *
 * 🛡️ GDPR Considerations:
 * - Handles PII within `$context`. Sanitization (`@privacy-safe`) is crucial before persistence.
 * - Stores data that might be subject to GDPR requests (access, deletion).
 * - Log entries themselves form part of an audit trail (`@log`).
 */
final class DatabaseLogHandler implements ErrorHandlerInterface
{
    protected readonly UltraLogManager $ulmLogger;
    protected readonly array $dbConfig; // ['enabled', 'include_trace', 'max_trace_length', 'sensitive_keys'?]

    /**
     * 🎯 Constructor: Injects ULM logger and DB-specific configuration.
     *
     * @param UltraLogManager $ulmLogger Logger for internal handler operations.
     * @param array $dbConfig Configuration specific to database logging (from 'error-manager.database_logging').
     */
    public function __construct(UltraLogManager $ulmLogger, array $dbConfig)
    {
        $this->ulmLogger = $ulmLogger;
        $this->dbConfig = $dbConfig;
    }

    /**
     * 🧠 Determine if this handler should handle the error.
     * Checks if database logging is enabled in the injected configuration.
     *
     * @param array $errorConfig Resolved error configuration for the error being handled.
     * @return bool True if database logging is enabled.
     */
    public function shouldHandle(array $errorConfig): bool
    {
        return $this->dbConfig['enabled'] ?? true;
    }

    /**
     * 💾 Handle the error by logging it to the database via ErrorLog model.
     * Applies sanitization to context data before persistence.
     * Logs internal failures using the injected ULM logger.
     *
     * 📥 @data-input (Via $context and $exception)
     * 🪵 @log (Persists error log entry, logs internal status)
     * 🔥 @critical (Storing error logs can be critical for audit/compliance)
     *
     * @param string $errorCode The symbolic error code.
     * @param array $errorConfig The configuration metadata for the error.
     * @param array $context Contextual data potentially containing PII.
     * @param Throwable|null $exception Optional original throwable.
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        try {
            // Sanitize context *before* encoding and saving
            // Apply privacy-safe transformation
            $sanitizedContext = $this->sanitizeContext($context);

            $data = [
                'error_code'       => $errorCode,
                'type'             => $errorConfig['type'] ?? 'error',
                'blocking'         => $errorConfig['blocking'] ?? 'not',
                'message'          => $errorConfig['message'] ?? $errorConfig['dev_message'] ?? ($errorConfig['dev_message_key'] ?? null),
                'user_message'     => $errorConfig['user_message'] ?? ($errorConfig['user_message_key'] ?? null),
                'http_status_code' => $errorConfig['http_status_code'] ?? 500,
                'context'          => json_encode($sanitizedContext), // Use sanitized context
                'display_mode'     => $errorConfig['msg_to'] ?? 'div',
                'resolved'         => false,
                'notified'         => false,
                'request_method'   => $sanitizedContext['request_method'] ?? null, // Get from sanitized context if passed
                'request_url'      => $sanitizedContext['request_url'] ?? null,
                'user_agent'       => $sanitizedContext['user_agent'] ?? null,
                'ip_address'       => $sanitizedContext['ip_address'] ?? null, // Should ideally be pseudonymized if possible earlier
                'user_id'          => $sanitizedContext['user_id'] ?? null, // User ID might be PII
            ];

            if ($exception) {
                $data['exception_class']   = get_class($exception);
                // Sanitize exception message? Usually technical, but could leak info.
                $data['exception_message'] = $this->sanitizeStringValue($exception->getMessage()); // Apply basic sanitization
                $data['exception_file']    = $exception->getFile(); // Path could be sensitive? Maybe just basename?
                $data['exception_line']    = $exception->getLine();

                if ($this->dbConfig['include_trace'] ?? true) {
                    // Sanitize trace? Very verbose, maybe just truncate is enough.
                    $data['exception_trace'] = $this->truncateTrace($exception->getTraceAsString());
                }
            }

            $errorLog = ErrorLog::create($data);

            $this->ulmLogger->debug("UEM DatabaseLogHandler: Error persisted.", [
                'error_log_id' => $errorLog->id,
                'error_code' => $errorCode
            ]);

        } catch (Throwable $e) {
            $this->ulmLogger->error("UEM DatabaseLogHandler: Failed to persist error log.", [
                'original_error_code' => $errorCode,
                'db_handler_exception_class' => get_class($e),
                'db_handler_exception_message' => $e->getMessage(),
                // Avoid logging trace of DB logging failure unless necessary
            ]);
        }
    }

    /**
     * 🔐 Sanitize context data recursively for database storage.
     * Removes keys defined as sensitive in configuration.
     *
     * 🛡️ @privacy-safe Core PII sanitization logic for context.
     * 🧼 @sanitizer
     *
     * @param array $context The context array to sanitize.
     * @return array The sanitized context array.
     */
    protected function sanitizeContext(array $context): array
    {
        // Get sensitive keys from config, fallback to a default list
        $defaultSensitiveKeys = ['password', 'secret', 'token', 'auth', 'key', 'credentials', 'authorization', 'php_auth_user', 'php_auth_pw', 'credit_card', 'cvv', 'api_key'];
        $sensitiveKeys = $this->dbConfig['sensitive_keys'] ?? $defaultSensitiveKeys;
        // Ensure keys are lowercase for comparison
        $sensitiveKeys = array_map('strtolower', $sensitiveKeys);

        $sanitized = [];
        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string)$key);

            // Basic check for common sensitive patterns even if not in list
            if (str_contains($lowerKey, 'password') || str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'token') || str_contains($lowerKey, '_key')) {
                 if (!in_array($lowerKey, $sensitiveKeys)) {
                     $sensitiveKeys[] = $lowerKey; // Add dynamically detected potential key
                     $this->ulmLogger->debug("UEM DatabaseLogHandler: Dynamically added potential sensitive key to sanitization list.", ['key' => $key]);
                 }
            }

            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value); // Recursive call
            } elseif (is_string($value)) {
                // Apply basic sanitization/truncation to strings?
                $sanitized[$key] = $this->sanitizeStringValue($value);
            } elseif (is_scalar($value) || is_null($value)) {
                $sanitized[$key] = $value;
            } elseif (is_object($value)) {
                $sanitized[$key] = '[Object:' . get_class($value) . ']';
            } elseif (is_resource($value)) {
                 $sanitized[$key] = '[Resource:' . get_resource_type($value) . ']';
            } else {
                 $sanitized[$key] = '[Unloggable Type:' . gettype($value) . ']';
            }
        }
        return $sanitized;
    }

    /**
      * ✂️ Sanitize a string value for logging (basic example).
      * Could be enhanced (e.g., truncate, remove control chars).
      *
      * 🛡️ @privacy-safe Helper for string sanitization.
      * 🧼 @sanitizer
      *
      * @param string $value
      * @param int $maxLength
      * @return string
      */
     protected function sanitizeStringValue(string $value, int $maxLength = 500): string
     {
         // Example: Limit length
         if (mb_strlen($value) > $maxLength) {
             $value = mb_substr($value, 0, $maxLength - 16) . '...[TRUNCATED]';
         }
         // Example: Remove null bytes which can cause issues
         $value = str_replace("\0", '', $value);
         // Potentially add more sanitization (e.g., control characters) if needed
         return $value;
     }


    /**
     * ✂️ Truncate stack trace based on configured maximum length.
     *
     * @param string $trace The full stack trace string.
     * @return string The potentially truncated stack trace.
     */
    protected function truncateTrace(string $trace): string
    {
        $maxLength = $this->dbConfig['max_trace_length'] ?? 10000;
        if (mb_strlen($trace) <= $maxLength) {
            return $trace;
        }
        return mb_substr($trace, 0, $maxLength - 16) . "\n[...TRUNCATED]";
    }
}