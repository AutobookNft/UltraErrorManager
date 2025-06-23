<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Support\UemLogFormatter;
use Ultra\UltraLogManager\UltraLogManager;
use Throwable;

/**
 * 🎯 LogHandler – Oracoded Error Logging Handler (Final)
 *
 * Delega la formattazione completa a UemLogFormatter e passa al logger
 * la stringa finale, ottenendo il pieno controllo sull'output.
 */
final class LogHandler implements ErrorHandlerInterface
{
    protected readonly UltraLogManager $ulmLogger;

    public function __construct(UltraLogManager $ulmLogger)
    {
        $this->ulmLogger = $ulmLogger;
    }

    public function shouldHandle(array $errorConfig): bool
    {
        return true;
    }

    /**
     * 🪵 Gestisce l'errore loggandolo tramite UemLogFormatter e UltraLogManager.
     * 🧠 @enhanced Ora passa un contesto vuoto al logger per evitare duplicazioni.
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        $logLevelMethod = $this->getLogLevelMethod($errorConfig['type'] ?? 'error');
        
        // UemLogFormatter crea il corpo del messaggio
        $messageBody = UemLogFormatter::format($errorCode, $errorConfig, $context, $exception);
        
        // Passiamo il corpo come messaggio e il contesto originale.
        // Il nostro UemLineFormatter si occuperà della formattazione finale.
        $this->ulmLogger->{$logLevelMethod}($messageBody, $context);
    }

    /**
     * 🧱 Mappa il tipo di errore UEM al metodo di log di UltraLogManager.
     */
    protected function getLogLevelMethod(string $errorType): string
    {
        $mapping = [
            'critical' => 'critical',
            'error'    => 'error',
            'warning'  => 'warning',
            'notice'   => 'notice',
        ];

        return $mapping[strtolower($errorType)] ?? 'error';
    }
}