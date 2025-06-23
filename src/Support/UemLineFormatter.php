<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Support;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Throwable;

/**
 * @package Ultra\ErrorManager\Support
 * @author Padmin D. Curtis (AI Partner OS2.0) for Fabio Cherici
 * @version 1.0.0 (FlorenceEGI MVP - Personal Data Domain)
 * @deadline 2025-06-30
 * 🎯 UemLineFormatter – The Definitive Monolog Formatter for UEM
 *
 * Takes full control of the log output, producing a clean, rich, and immediately
 * understandable multi-line diagnostic report. This component is responsible for
 * translating raw error data into a clear narrative for the developer.
 *
 * 🧱 Structure:
 * - Extends Monolog's `LineFormatter` to natively integrate with Laravel's logging system.
 * - Overrides the `format()` method to implement the custom formatting logic.
 * - Uses private helper methods for specific, complex tasks:
 * - `findApplicationFrame()`: For pinpointing the error's origin point.
 * - `sanitizeContextForLog()`: For cleaning sensitive data.
 *
 * 📡 Communication:
 * - Receives a `LogRecord` object from Monolog, containing all log event data.
 * - Returns a final, multi-line string that will be written to the log file.
 *
 * 🧪 Testability:
 * - Its output is deterministic. Given the same `LogRecord` as input, it will
 * always produce the same formatted string.
 *
 * 🛡️ Security:
 * - Implements context sanitization to prevent sensitive data (passwords, tokens,
 * API keys) from being written to logs, adhering to the "privacy by design" principle.
 */
final class UemLineFormatter extends LineFormatter
{
    /**
     * 🎯 Constructor: Sets the date format for the log entry.
     *
     * @param string $dateFormat The format for the datetime placeholder.
     * @param bool $allowInlineLineBreaks Whether to allow newlines in the main message.
     * @param bool $ignoreEmptyContextAndExtra Whether to ignore empty context/extra arrays.
     */
    public function __construct(string $dateFormat = 'Y-m-d H:i:s', bool $allowInlineLineBreaks = true, bool $ignoreEmptyContextAndExtra = true)
    {
        parent::__construct(dateFormat: $dateFormat, allowInlineLineBreaks: $allowInlineLineBreaks, ignoreEmptyContextAndExtra: $ignoreEmptyContextAndExtra);
    }

    /**
     * 🎨 Formats a UEM log record into a precise multi-line string.
     *
     * This is the core of the formatter. It orchestrates the creation of the log entry,
     * assembling the various parts (header, exception, file, context) into a
     * standardized and readable format.
     *
     * @param LogRecord $record The complete log record provided by Monolog.
     * @return string The final, formatted log entry as a string.
     */
    public function format(LogRecord $record): string
    {
        // Extract the main data from the record's context
        $context = $record->context;
        $exception = $context['exception'] ?? null;
        $errorCode = $context['errorCode'] ?? 'UNKNOWN_ERROR';
        $userContext = $context['userContext'] ?? [];
        
        $separator = str_repeat('-', 80);

        // --- 1. Header ---
        $header = "✦ UEM [{$errorCode}]";

        // --- 2. Exception Details ---
        $exceptionDetails = '';
        if ($exception instanceof Throwable) {
            $exceptionDetails = get_class($exception) . ":\n" . $exception->getMessage();
        }

        // --- 3. File Location (Intelligent) ---
        $fileLocation = '';
        if ($exception instanceof Throwable) {
            $appFrame = self::findApplicationFrame($exception);
            $file = $appFrame['file'] ?? $exception->getFile();
            $line = $appFrame['line'] ?? $exception->getLine();
            $displayPath = function_exists('base_path')
                ? str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)
                : basename($file);
            $fileLocation = "File: {$displayPath}:{$line}";
        }
        
        // --- 4. Formatted Context ---
        $jsonContext = json_encode(self::sanitizeContextForLog($userContext), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $indentedJson = "  " . implode("\n  ", explode("\n", $jsonContext));

        // --- 5. Final Assembly ---
        $output  = "[%datetime%] %channel%.%level_name%:\n";
        $output .= $separator . "\n";
        $output .= $header . "\n\n";
        if ($exceptionDetails) $output .= $exceptionDetails . "\n\n";
        if ($fileLocation) $output .= $fileLocation . "\n\n";
        $output .= "Context {\n" . $indentedJson . "\n}\n";
        $output .= $separator . "\n";
        
        // Replace Monolog's placeholders
        $record = $record->with(message: $output);
        return parent::format($record);
    }

    /**
     * 🔎 Finds the first relevant application frame from the exception trace.
     *
     * Iterates the stack trace to find the first file that is not inside
     * the /vendor/ directory, which is the likely origin of the error
     * in the application's own code.
     *
     * @param Throwable $exception The exception to inspect.
     * @return array|null An array containing 'file' and 'line' keys, or null if not found.
     */
    private static function findApplicationFrame(Throwable $exception): ?array
    {
        // Chain traces from the exception and all previous exceptions
        $traces = [$exception->getTrace()];
        $previous = $exception->getPrevious();
        while ($previous) {
            $traces[] = $previous->getTrace();
            $previous = $previous->getPrevious();
        }

        foreach ($traces as $trace) {
            foreach ($trace as $frame) {
                // Check if the frame has a file path and if it's outside the 'vendor' directory
                if (isset($frame['file']) && !str_contains($frame['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                    return [
                        'file' => $frame['file'],
                        'line' => $frame['line'] ?? null, // Return the line if available
                    ];
                }
            }
        }

        return null; // No application-level frame was found
    }

    /**
     * 🔐 Sanitizes context data for safe logging.
     *
     * This method cleans the context array before it's written to the log. It redacts
     * values for sensitive keys and handles different data types to ensure
     * the log is both readable and secure.
     *
     * @param array $context The context array to sanitize.
     * @return array The sanitized context array.
     */
    private static function sanitizeContextForLog(array $context): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'auth', 'key', 'credentials', 'authorization', 'php_auth_pw', 'api_key'];

        $sanitized = [];
        foreach ($context as $key => $value) {
            // Redact the value if the key is in the sensitive list
            if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            // Handle different data types for safe and readable logging
            if (is_string($value)) {
                // Truncate long strings to keep logs concise
                $sanitized[$key] = mb_strimwidth($value, 0, 500, '...');
            } elseif (is_array($value)) {
                // To avoid overly verbose logs, do not expand nested arrays
                $sanitized[$key] = '[Array:' . count($value) . ' items]';
            } elseif (is_object($value) && !$value instanceof \UnitEnum) {
                // Show the object's class but not its content
                $sanitized[$key] = '[Object:' . get_class($value) . ']';
            } else {
                // Keep scalar values (int, float, bool, null) and enums as they are
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}