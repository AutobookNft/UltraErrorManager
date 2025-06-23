<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Support;

use Throwable;

/**
 * 🎯 UemLogFormatter – Motore di Presentazione Log per UEM (Sintesi Finale)
 *
 * Trasforma i dati di errore UEM in voci di log multi-linea leggibili.
 * - Trova intelligentemente l'origine dell'errore nel codice applicativo.
 * - Formatta il contesto come un blocco JSON multi-linea e sanitizzato.
 */
final class UemLogFormatter
{
    /**
     * 🎨 Formatta i dati di errore UEM in una voce di log multi-linea e leggibile.
     * 🧠 @enhanced (Trova la posizione a livello di applicazione)
     * 💅 @enhanced (Formatta il contesto come JSON multi-linea)
     */
    public static function format(string $code, ?Throwable $exception, array $context = []): string
    {
        $lines = [];
        
        // 1. Riepilogo Principale
        $summary = "✦ UEM [{$code}]";
        if ($exception) {
            $exceptionClass = get_class($exception);
            $message = self::truncateMessage($exception->getMessage(), 120);
            $summary .= " {$exceptionClass}: {$message}";
        }
        $lines[] = $summary;
        
        // 2. Posizione del File (Intelligente)
        if ($exception) {
            $appFrame = self::findApplicationFrame($exception);
            
            $file = $appFrame ? $appFrame['file'] : $exception->getFile();
            $line = $appFrame ? $appFrame['line'] : $exception->getLine();
            
            $displayPath = function_exists('base_path') 
                ? str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)
                : basename($file);

            $lines[] = "File: " . $displayPath . ":" . $line;
        }
        
        // 3. Contesto (Multi-linea, Sanitizzato e Formattato)
        $formattedContext = self::formatContext($context);
        $lines[] = "Context:\n" . $formattedContext;

        return implode("\n", $lines);
    }

    /**
     * 🔎 Trova il primo frame rilevante dello stack trace nell'applicazione.
     */
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
                    return [
                        'file' => $frame['file'],
                        'line' => $frame['line'] ?? null,
                    ];
                }
            }
        }
        return null;
    }

    /**
     * 💅 Formatta e sanitizza il contesto per una visualizzazione chiara e sicura.
     * @param array $context
     * @return string
     */
    private static function formatContext(array $context): string
    {
        if (empty($context)) {
            return '  (empty)';
        }

        // Rimuoviamo l'IP dal contesto principale se presente, lo mostriamo separatamente
        if (isset($context['ip_address'])) {
             unset($context['ip_address']);
        }
        
        $sanitizedContext = self::sanitizeContextForLog($context);

        $json = json_encode($sanitizedContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Indentiamo il JSON per allinearlo sotto la label "Context:"
        $indentedJson = "  " . implode("\n  ", explode("\n", $json));

        return $indentedJson;
    }

    /**
     * 🔐 Sanitizza il contesto per i log, redigendo chiavi sensibili.
     * @param array $context
     * @return array
     */
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
                $sanitized[$key] = mb_strimwidth($value, 0, 250, '...'); // Tronca stringhe lunghe
            } elseif (is_array($value)) {
                $sanitized[$key] = '[Array:' . count($value) . ' items]'; // Non espandere array annidati nel log
            } elseif (is_object($value)) {
                $sanitized[$key] = '[Object:' . get_class($value) . ']';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    /**
     * 🎨 Tronca i messaggi di eccezione lunghi.
     */
    private static function truncateMessage(string $message, int $maxLength = 120): string
    {
        // ... (logica invariata) ...
        if (strlen($message) <= $maxLength) {
            return $message;
        }
        
        if (preg_match('/Column not found:.*Unknown column \'([^\']+)\'/', $message, $matches)) {
            return "Column not found: Unknown column '{$matches[1]}'";
        }
        
        if (preg_match('/Table \'([^\']+)\' doesn\'t exist/', $message, $matches)) {
            return "Table '{$matches[1]}' doesn't exist";
        }
        
        return substr($message, 0, $maxLength - 3) . '...';
    }
}