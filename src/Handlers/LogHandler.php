<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\UltraLogManager\UltraLogManager; // Dependency: ULM Core Logger
use Throwable; // Import Throwable
use Ultra\ErrorManager\Support\UemLogFormatter;

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
     * 🪵 Handle the error by logging it via UemLogFormatter and UltraLogManager.
     * 📥 @data-input (Via $context and $exception, processed by UemLogFormatter)
     * 🔄 @enhanced (Now passes full context to getLogMessage for formatting)
     *
     * Enhanced to support improved log presentation through UemLogFormatter.
     * Passes complete context and exception data to message formatting while
     * maintaining minimal context for ULM metadata.
     *
     * @param string $errorCode The symbolic error code
     * @param array $errorConfig The configuration metadata for the error
     * @param array $context Contextual data available for logging
     * @param Throwable|null $exception Optional original throwable
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        // Determine the appropriate ULM log level method name
        $logLevelMethod = $this->getLogLevelMethod($errorConfig['type'] ?? 'error');

        // Prepare enhanced log message using UemLogFormatter - NOW with full context
        $message = $this->getLogMessage($errorCode, $errorConfig, $context, $exception);

        // Prepare minimal structured context for ULM
        $logContext = $this->prepareLogContext($errorCode, $errorConfig, $context, $exception);

        // Log using the injected ULM instance with enhanced formatting
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
     * 🎨 Prepare the primary log message string using UemLogFormatter.
     * 📥 @data-input (Via $context and $exception for formatting)
     * 📤 @data-output (Clean, formatted log message string)
     * 🔄 @enhanced (Now uses UemLogFormatter for visual consistency)
     *
     * Replaces previous developer message priority approach with structured,
     * visual formatting optimized for log scanning and operational monitoring.
     * Delegates formatting logic to UemLogFormatter for consistency across UEM.
     *
     * Output format provides:
     * - Visual boundaries for log entry identification
     * - Exception class and message prominence
     * - File location for immediate developer navigation  
     * - Context overview without sensitive data exposure
     *
     * @param string $errorCode UEM error code identifier
     * @param array $errorConfig Error configuration metadata
     * @param array $context Original contextual data passed to handle()
     * @param Throwable|null $exception Optional original exception
     * @return string Formatted log message ready for ULM consumption
     */
    protected function getLogMessage(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): string
    {
        try {
            return UemLogFormatter::format($errorCode, $exception, $context);
        } catch (\Throwable $e) {
            return "FORMATTER ERROR: " . $e->getMessage() . " | FALLBACK: [{$errorCode}]";
        }
    }

    /**
     * 🧱 Prepare enhanced context array for ULM logging with additional debug info.
     * 📥 @data-input (Error metadata and context)
     * 📤 @data-output (Enhanced UEM metadata for debugging)
     * 🔄 @enhanced (Includes more debugging information)
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Original contextual data
     * @param Throwable|null $exception Optional original exception
     * @return array Enhanced structured context for ULM processing
     */
    protected function prepareLogContext(string $errorCode, array $errorConfig, array $context, ?Throwable $exception): array
    {
        $logContext = [
            'uem_code' => $errorCode,
            'uem_type' => $errorConfig['type'] ?? 'error',
            'uem_blocking' => $errorConfig['blocking'] ?? 'unknown',
            'uem_timestamp' => now()->toIso8601String(),
        ];

        // Add exception details if available
        if ($exception) {
            $logContext['Class'] = get_class($exception);
            $logContext['Message'] = $exception->getMessage();
            
            // Extract calling method from stack trace
            $trace = $exception->getTrace();
            if (!empty($trace[0])) {
                $caller = $trace[0];
                $method = '';
                
                if (isset($caller['class'])) {
                    $method = $caller['class'] . '::' . ($caller['function'] ?? 'unknown');
                } elseif (isset($caller['function'])) {
                    $method = $caller['function'];
                }
                
                if ($method) {
                    $logContext['Method'] = $method;
                }
            }
        }

        // Add the formatted message for reference
        $logContext['FormattedMessage'] = $this->getLogMessage($errorCode, $errorConfig, $context, $exception);

        return $logContext;
    }
}