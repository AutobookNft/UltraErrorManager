<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Support;

use Throwable;

/**
 * 🎯 UemLogFormatter – Clean Log Presentation Engine for UEM
 *
 * Transforms verbose UEM error data into concise, scannable log entries optimized
 * for developer readability and operational monitoring. Replaces multi-line verbose
 * JSON context with structured, visual format that highlights critical information.
 *
 * 🧱 Structure:
 * - Static utility class with single formatting responsibility.
 * - Produces bordered, emoji-enhanced log entries for visual scanning.
 * - Extracts essential context (IP, keys) without overwhelming detail.
 * - Provides consistent 80-character visual boundaries for log readability.
 *
 * 📡 Use Case:
 * - Primary formatting engine for LogHandler in UEM error processing.
 * - Transforms exception stack traces into concise file:line references.
 * - Optimizes log volume while preserving diagnostic information.
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
     * 🎨 Format UEM error data into clean, scannable log entry.
     * 📥 @data-input (Via $context - IP address and contextual keys)
     * 📤 @data-output (Formatted log string with visual boundaries)
     * 🔒 @privacy-aware (Shows context keys only, not values)
     *
     * Produces structured log format:
     * ```
     * --------------------------------------------------------------------------------
     * ✦ UEM Error: [ERROR_CODE] ExceptionClass: Exception message
     * In: filename.php:123
     * IP: 127.0.0.1
     * Context keys: [key1, key2, key3]
     * --------------------------------------------------------------------------------
     * ```
     *
     * @param string $code UEM error code identifier
     * @param Throwable|null $exception Original exception if available
     * @param array $context Request/error context data
     * @return string Formatted log entry with visual boundaries
     */
    public static function format(string $code, ?Throwable $exception, array $context = []): string
    {
        $line = str_repeat('-', 80);
        
        // Build core error summary with exception details
        $summary = "[{$code}]";
        if ($exception) {
            $summary .= " " . get_class($exception) . ": " . $exception->getMessage();
        }
        
        // Extract file location for quick navigation
        $location = $exception 
            ? "In: " . basename($exception->getFile()) . ":" . $exception->getLine() 
            : '';
        
        // Essential context summary (GDPR-safe: keys only, not values)
        $ip = $context['ip_address'] ?? 'N/A';
        $contextKeys = array_keys($context);
        $keysStr = $contextKeys ? implode(', ', $contextKeys) : 'none';

        return <<<EOT
$line
✦ UEM Error: {$summary}
{$location}
IP: {$ip}
Context keys: [{$keysStr}]
$line
EOT;
    }
}