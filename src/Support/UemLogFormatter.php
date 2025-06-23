<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Support;

use Throwable;

/**
 * 🎯 UemLogFormatter – Clean Single-Line Log Presentation Engine for UEM
 *
 * Transforms verbose UEM error data into concise, single-line scannable log entries
 * optimized for standard log viewers and operational monitoring tools.
 *
 * 🧱 Structure:
 * - Static utility class with single formatting responsibility.
 * - Produces single-line, emoji-enhanced log entries for visual scanning.
 * - Extracts essential context (IP, keys) without overwhelming detail.
 * - Compatible with all log viewers and aggregation tools.
 *
 * 📡 Use Case:
 * - Primary formatting engine for LogHandler in UEM error processing.
 * - Transforms exception stack traces into concise file:line references.
 * - Optimizes log readability while preserving diagnostic information.
 *
 * 🧪 Testable:
 * - Pure static function with deterministic output based on input.
 * - No external dependencies or side effects.
 * - Handles null exception cases gracefully.
 *
 * 🛡️ GDPR Considerations:
 * - Reduces context exposure in logs through key-only enumeration.
 * - IP address display configurable via context inclusion/exclusion.
 * - No sensitive data expansion - maintains privacy by design.
 */
final class UemLogFormatter
{
    /**
     * 🎨 Format UEM error data into clean, single-line scannable log entry.
     * 📥 @data-input (Via $context - IP address and contextual keys)
     * 📤 @data-output (Single-line formatted log string)
     * 🔒 @privacy-aware (Shows context keys only, not values)
     *
     * Produces structured single-line format:
     * ```
     * ✦ UEM [ERROR_CODE] ExceptionClass: Exception message | File: filename.php:123 | IP: 127.0.0.1 | Context: [key1, key2, key3]
     * ```
     *
     * @param string $code UEM error code identifier
     * @param Throwable|null $exception Original exception if available
     * @param array $context Request/error context data
     * @return string Single-line formatted log entry
     */
    public static function format(string $code, ?Throwable $exception, array $context = []): string
    {
        // Build core error summary with exception details
        $summary = "✦ UEM [{$code}]";
        if ($exception) {
            $exceptionClass = get_class($exception);
            $message = self::truncateMessage($exception->getMessage(), 120);
            $summary .= " {$exceptionClass}: {$message}";
        }
        
        // Extract file location for quick navigation
        $location = $exception 
            ? " | File: " . basename($exception->getFile()) . ":" . $exception->getLine() 
            : '';
        
        // Essential context summary (GDPR-safe: keys only, not values)
        $ip = $context['ip_address'] ?? 'N/A';
        $contextKeys = array_keys($context);
        $keysStr = $contextKeys ? implode(', ', $contextKeys) : 'none';

        return $summary . $location . " | IP: {$ip} | Context: [{$keysStr}]";
    }

    /**
     * 🎨 Truncate long exception messages to keep log entries scannable.
     * 
     * @param string $message Original exception message
     * @param int $maxLength Maximum length before truncation
     * @return string Truncated message with ellipsis if needed
     */
    private static function truncateMessage(string $message, int $maxLength = 120): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }
        
        // For SQL errors, try to extract just the essential part
        if (preg_match('/Column not found:.*Unknown column \'([^\']+)\'/', $message, $matches)) {
            return "Column not found: Unknown column '{$matches[1]}'";
        }
        
        if (preg_match('/Table \'([^\']+)\' doesn\'t exist/', $message, $matches)) {
            return "Table '{$matches[1]}' doesn't exist";
        }
        
        // Generic truncation for other long messages
        return substr($message, 0, $maxLength - 3) . '...';
    }
}