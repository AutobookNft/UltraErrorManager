<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Support\UemLineFormatter; // Il nostro formatter custom
use Throwable;

/**
 * 🎯 LogHandler – Oracoded Autonomous Error Logging Handler
 *
 * This handler is a self-contained logging system. It creates its own
 * pre-configured Monolog instance to write beautifully formatted UEM errors
 * to a dedicated log file, requiring ZERO configuration from the user in
 * their `config/logging.php` file.
 */
final class LogHandler implements ErrorHandlerInterface
{
    /**
     * 🧱 @property A private, pre-configured Monolog logger instance.
     */
    private Logger $logger;

    /**
     * 🎯 Constructor: Builds the self-contained logger.
     *
     * This is where the magic happens. We instantiate and configure Monolog
     * without relying on Laravel's LogManager.
     *
     * @param array $config The 'log_handler' section from the error-manager config.
     */
    public function __construct(array $config = [])
    {
        // 1. Definisci il percorso del file di log, prendendolo dalla config o usando un default.
        $logPath = $config['path'] ?? storage_path('logs/error_manager.log');

        // 2. Crea un nuovo logger Monolog, specifico per UEM.
        $this->logger = new Logger('UEM');

        // 3. Crea un handler che sa come scrivere su un file (Stream).
        $streamHandler = new StreamHandler($logPath, Logger::DEBUG);

        // 4. Crea un'istanza del nostro formatter perfetto.
        $formatter = new UemLineFormatter();

        // 5. Applica il nostro formatter all'handler.
        $streamHandler->setFormatter($formatter);

        // 6. Push the configured handler to our private logger.
        // This is now valid because $this->logger is a Monolog\Logger object.
        $this->logger->pushHandler($streamHandler);
    }

    /**
     * {@inheritdoc}
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // By default, this handler always processes errors.
        return true;
    }

    /**
     * 🧠 @enhanced Now correctly maps UEM types to PSR-3 log levels.
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        // Get the UEM-specific error type from config.
        $uemType = $errorConfig['type'] ?? 'error';

        // Translate the UEM type into a valid PSR-3 log level.
        $logLevel = $this->mapUemTypeToPsrLevel($uemType);

        $logContext = [
            'errorCode'   => $errorCode,
            'exception'   => $exception,
            'userContext' => $context,
        ];

        // Use the private logger with the correctly translated log level.
        $this->logger->log($logLevel, "UEM Handled Error: {$errorCode}", $logContext);
    }
    /**
     * 🗺️ Translates a UEM-specific error type into a PSR-3 compliant log level.
     *
     * This ensures that custom UEM types like 'server_error' are mapped to a
     * level that Monolog understands, preventing exceptions within the handler.
     *
     * @param string $uemType The error type from the UEM configuration.
     * @return string A valid PSR-3 log level string (e.g., 'error', 'warning').
     */
    private function mapUemTypeToPsrLevel(string $uemType): string
    {
        return match (strtolower($uemType)) {
            'critical', 'fatal' => 'critical',
            'server_error', 'error' => 'error', // 'server_error' is now correctly mapped
            'warning' => 'warning',
            'notice' => 'notice',
            'info' => 'info',
            default => 'error', // Default to 'error' for any unknown type
        };
    }
}