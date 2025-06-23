<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Support;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Throwable;

/**
 * @package Ultra\ErrorManager\Support
 * @author Padmin D. Curtis (AI Partner OS2.0) for Fabio Cherici
 * @version 1.3.0 
 * @deadline 2025-06-24
 *
 * 🎯 UemLineFormatter – The Definitive Monolog Formatter for UEM
 *
 * Takes full control of the log output, producing a clean, rich, and immediately
 * understandable multi-line diagnostic report.
 */
final class UemLineFormatter extends LineFormatter
{
    /**
     * @param string $dateFormat The format for the datetime placeholder.
     * @param bool $allowInlineLineBreaks Whether to allow newlines in the main message.
     * @param bool $ignoreEmptyContextAndExtra Whether to ignore empty context/extra arrays.
     */
    public function __construct(string $dateFormat = 'Y-m-d H:i:s', bool $allowInlineLineBreaks = true, bool $ignoreEmptyContextAndExtra = true)
    {
        parent::__construct("[%datetime%] UEM.%level_name%: %message%\n", $dateFormat, $allowInlineLineBreaks, $ignoreEmptyContextAndExtra);
    }

    public function format(LogRecord $record): string
    {
        $context = $record->context;
        $exception = $context['exception'] ?? null;
        $errorCode = $context['errorCode'] ?? 'UNKNOWN_ERROR';
        $userContext = $context['userContext'] ?? [];

        $separator = str_repeat('-', 80);

        // Build the multi-line message BODY
        $body  = "\n";
        $body .= $separator . "\n";
        $body .= "✦ UEM [{$errorCode}]\n\n";

        if ($exception instanceof Throwable) {
            $body .= get_class($exception) . ":\n";
            $body .= $exception->getMessage() . "\n\n";

            $appFrame = self::findApplicationFrame($exception);
            $file = $appFrame['file'] ?? $exception->getFile();
            $line = $appFrame['line'] ?? $exception->getLine();
            $displayPath = function_exists('base_path')
                ? str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)
                : basename($file);
            $body .= "File: {$displayPath}:{$line}\n\n";
        }
        
        $jsonContext = json_encode(self::sanitizeContextForLog($userContext), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        $body .= "Context {\n";
        $body .= "  " . implode("\n  ", explode("\n", $jsonContext)) . "\n";
        $body .= "}\n";
        $body .= $separator;

        // Replace the original message with our crafted body
        // and clear the context to prevent Monolog from appending it.
        $record = $record->with(message: $body, context: []);
        
        return parent::format($record);
    }

    private static function findApplicationFrame(Throwable $exception): ?array
    {
        $traces = [$exception->getTrace()];
        $previous = $exception->getPrevious();
        while ($previous) {
            $traces[] = $previous->getTrace();
            $previous = $previous->getPrevious();
        }

        foreach ($traces as $trace) {
            foreach ($trace as $frame) {
                if (isset($frame['file']) && !str_contains($frame['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                    return ['file' => $frame['file'], 'line' => $frame['line'] ?? null];
                }
            }
        }
        return null;
    }

    private static function sanitizeContextForLog(array $context): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'auth', 'key', 'credentials', 'authorization', 'php_auth_pw', 'api_key'];
        
        $sanitized = [];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = mb_strimwidth($value, 0, 500, '...');
            } elseif (is_array($value)) {
                $sanitized[$key] = '[Array:' . count($value) . ' items]';
            } elseif (is_object($value) && !$value instanceof \UnitEnum) {
                $sanitized[$key] = '[Object:' . get_class($value) . ']';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}