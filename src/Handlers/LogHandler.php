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
     * {@inheritdoc}
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        $logLevelName = strtolower($errorConfig['type'] ?? 'error');

        // Prepara il contesto che il nostro UemLineFormatter si aspetta
        $logContext = [
            'errorCode'   => $errorCode,
            'exception'   => $exception,
            'userContext' => $context,
        ];

        // Usa il logger privato per scrivere il log.
        // Il messaggio primario è breve, il grosso del lavoro lo fa il formatter
        // con i dati che riceve dal contesto.
        $this->logger->log($logLevelName, "UEM Handled Error: {$errorCode}", $logContext);
    }
}