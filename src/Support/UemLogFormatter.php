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
    public static function format(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): string
    {
        $separator = str_repeat('-', 80);
        $header = "✦ UEM [{$errorCode}]";

        $exceptionDetails = '';
        if ($exception) {
            $exceptionDetails = get_class($exception) . ":\n" . $exception->getMessage();
        }

        $fileLocation = '';
        if ($exception) {
            $appFrame = self::findApplicationFrame($exception);
            $file = $appFrame['file'] ?? $exception->getFile();
            $line = $appFrame['line'] ?? $exception->getLine();
            $displayPath = function_exists('base_path')
                ? str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file)
                : basename($file);
            $fileLocation = "File: {$displayPath}:{$line}";
        }
        
        $contextForJson = self::prepareContextForJson($context, $exception);
        $jsonContext = json_encode($contextForJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $indentedJson = "  " . implode("\n  ", explode("\n", $jsonContext));

        // Assembla solo il corpo del messaggio
        $entry  = $separator . "\n";
        $entry .= "{$header}\n";
        $entry .= $exceptionDetails . "\n\n";
        $entry .= $fileLocation . "\n";
        $entry .= "Context {\n" . $indentedJson . "\n}\n";
        $entry .= $separator;

        return $entry;
    }
    

    /**
     * 🧠 Prepara il contesto per il JSON, rimuovendo dati ridondanti.
     */
    private static function prepareContextForJson(array $context, ?Throwable $exception): array
    {
        // Sanitizza prima di tutto
        $sanitized = self::sanitizeContextForLog($context);

        // Rimuovi l'oggetto eccezione completo se presente, è già formattato sopra
        if (isset($sanitized['exception'])) unset($sanitized['exception']);
        if (isset($sanitized['error']) && $sanitized['error'] instanceof Throwable) {
             unset($sanitized['error']);
        }
       
        // Aggiungi il messaggio completo dell'eccezione se non già presente e utile
        if ($exception && !isset($sanitized['error_message'])) {
             $sanitized['error_message'] = $exception->getMessage();
        }

        return $sanitized;
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