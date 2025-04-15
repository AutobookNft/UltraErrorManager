<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\UltraLogManager\UltraLogManager; // Dependency: ULM Core Logger
use Throwable; // Import Throwable

/**
 * 🎯 LogHandler – Oracoded Error Logging Handler (GDPR Reviewed)
 *
 * Responsible for logging handled errors via the injected UltraLogManager (ULM).
 * Determines the appropriate log level and prepares structured context based on
 * the error configuration, delegating the actual log writing to ULM.
 *
 * 🧱 Structure:
 * - Implements ErrorHandlerInterface.
 * - Requires UltraLogManager injected via constructor.
 * - Maps UEM error types to PSR-3 log levels.
 * - Prepares structured log context.
 *
 * 📡 Communicates:
 * - With UltraLogManager to write log entries.
 *
 * 🧪 Testable:
 * - Depends on injectable UltraLogManager.
 * - Logic is deterministic based on input and ULM behavior.
 *
 * 🛡️ GDPR Considerations:
 * - This handler passes potentially sensitive `$context` and `exception` data to ULM.
 * - The ultimate GDPR compliance (e.g., PII redaction in logs, log storage security)
 *   depends on the configuration and behavior of the injected UltraLogManager and its
 *   underlying logging channels/drivers. `@log` tag indicates logging activity.
 */
final class LogHandler implements ErrorHandlerInterface
{
    /**
     * 🧱 @dependency UltraLogManager instance
     *
     * Used for all logging operations.
     *
     * @var UltraLogManager
     */
    protected readonly UltraLogManager $ulmLogger;

    /**
     * 🎯 Constructor: Injects the UltraLogManager dependency.
     *
     * @param UltraLogManager $ulmLogger The UltraLogManager instance provided by DI.
     */
    public function __construct(UltraLogManager $ulmLogger)
    {
        $this->ulmLogger = $ulmLogger;
    }

    /**
     * 🧠 Determine if this handler should handle the error.
     * By default, the LogHandler processes all errors passed to it.
     * Specific logic could be added here if needed (e.g., disable logging
     * for certain error types via config).
     *
     * @param array $errorConfig Resolved error configuration.
     * @return bool Always true by default.
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Currently logs all errors it receives.
        // Add specific conditions here if needed, e.g.:
        // return !($errorConfig['disable_logging'] ?? false);
        return true;
    }

    /**
     * 🪵 Handle the error by logging it via the injected UltraLogManager.
     * 📥 @data-input (Via $context and $exception, passed to ULM)
     *
     * @param string $errorCode The symbolic error code.
     * @param array $errorConfig The configuration metadata for the error.
     * @param array $context Contextual data available for logging.
     * @param Throwable|null $exception Optional original throwable.
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        // Determine the appropriate ULM log level method name
        $logLevelMethod = $this->getLogLevelMethod($errorConfig['type'] ?? 'error');

        // Prepare log message - use dev message as the primary source for logs
        $message = $this->getLogMessage($errorCode, $errorConfig);

        // Prepare structured context for ULM
        $logContext = $this->prepareLogContext($errorCode, $errorConfig, $context, $exception);

        // Log using the injected ULM instance and the determined level
        // ULM's internal methods will handle adding caller context etc.
        $this->ulmLogger->{$logLevelMethod}($message, $logContext);
    }

    /**
     * 🧱 Map UEM error type to UltraLogManager log level method name.
     * Provides mapping between conceptual error types and PSR-3 compatible levels.
     *
     * @param string $errorType The type of error ('critical', 'error', 'warning', 'notice').
     * @return string The corresponding method name on UltraLogManager (e.g., 'critical', 'error').
     */
    protected function getLogLevelMethod(string $errorType): string
    {
        // Mapping based on PSR-3 levels used by UltraLogManager
        $mapping = [
            'critical' => 'critical',
            'error'    => 'error',
            'warning'  => 'warning',
            'notice'   => 'notice',
            // 'info' might map from 'notice' or a dedicated 'info' type if added later
        ];

        // Default to 'error' if type is unknown or not mapped
        return $mapping[strtolower($errorType)] ?? 'error';
    }

    /**
     * 🧱 Prepare the primary log message string.
     * Prioritizes the developer message key/string.
     *
     * @param string $errorCode
     * @param array $errorConfig
     * @return string
     */
    protected function getLogMessage(string $errorCode, array $errorConfig): string
    {
         // Note: Assumes ErrorManager::formatMessage (using injected Translator)
         // has already prepared translated strings if keys were used.
         // For LogHandler, we prioritize the dev message if available in the config.
        $devMessage = $errorConfig['message'] ?? // Use 'message' if already formatted by ErrorManager
                      $errorConfig['dev_message'] ?? // Or direct dev_message
                      'Error occurred'; // Fallback

        return "[{$errorCode}] " . $devMessage;
    }

    /**
     * 🧱 Prepare the context array for logging via UltraLogManager.
     * Includes standard UEM fields and optional exception details.
     *
     * @param string $errorCode Error code identifier.
     * @param array $errorConfig Error configuration.
     * @param array $context Original contextual data passed to handle().
     * @param Throwable|null $exception Optional original exception.
     * @return array The structured log context data.
     */
    protected function prepareLogContext(string $errorCode, array $errorConfig, array $context, ?Throwable $exception): array
    {
        $logContext = [
            'uem_error_code' => $errorCode,
            'uem_error_type' => $errorConfig['type'] ?? 'error',
            'uem_blocking'   => $errorConfig['blocking'] ?? 'unknown',
            'original_context' => $context,
            'logged_at' => now()->toIso8601String(), 
        ];

        // Add exception information if present
        // Note: ULM itself might also add exception details; check for redundancy if needed.
        if ($exception) {
            $logContext['exception'] = [
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                // Decide whether to include trace here or rely on ULM's logger config
                 'trace' => $exception->getTraceAsString(), // Example: including trace
            ];
        }

        return $logContext;
    }
}