<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Support\UemLineFormatter;
use Throwable;

/**
 * @package Ultra\ErrorManager\Handlers
 * @author Padmin D. Curtis (AI Partner OS2.0) for Fabio Cherici
 * @version 1.3.0 (
 * @deadline 2025-06-24
 *
 * 🎯 LogHandler – Oracoded Autonomous Error Logging Handler
 *
 * This handler is a self-contained logging system. It creates its own
 * pre-configured Monolog instance to write beautifully formatted UEM errors
 * to a dedicated log file, requiring ZERO configuration from the user in
 * their `config/logging.php` file. This ensures maximum portability and
 * zero-friction setup.
 */
final class LogHandler implements ErrorHandlerInterface
{
    /**
     * 🧱 @property A private, pre-configured Monolog logger instance.
     * @var Logger
     */
    private Logger $logger;

    /**
     * 🎯 Constructor: Builds the self-contained logger.
     *
     * This is where the magic happens. We instantiate and configure Monolog
     * without relying on Laravel's LogManager, giving us full control.
     *
     * @param array $config The 'log_handler' section from the error-manager config.
     */
    public function __construct(array $config = [])
    {
        // 1. Define the log file path from config or use a safe default.
        $logPath = $config['path'] ?? storage_path('logs/error_manager.log');

        // 2. Create a new, UEM-specific Monolog logger.
        $this->logger = new Logger('UEM'); // 'UEM' is the logger's name.

        // 3. Create a handler that knows how to write to a file stream.
        $streamHandler = new StreamHandler($logPath, Logger::DEBUG);

        // 4. Create an instance of our custom formatter.
        $formatter = new UemLineFormatter();

        // 5. Apply our formatter to the handler.
        $streamHandler->setFormatter($formatter);

        // 6. Push the configured handler to our private logger.
        $this->logger->pushHandler($streamHandler);
    }

    public function shouldHandle(array $errorConfig): bool
    {
        return true;
    }

    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        $uemType = $errorConfig['type'] ?? 'error';
        $logLevel = $this->mapUemTypeToPsrLevel($uemType);

        // Prepare the rich context our UemLineFormatter expects.
        $logContext = [
            'errorCode'   => $errorCode,
            'exception'   => $exception,
            'userContext' => $context,
        ];

        // Use the private logger. The formatter will do all the work.
        $this->logger->log($logLevel, "UEM Handled Error: {$errorCode}", $logContext);
    }

    /**
     * 🗺️ Translates a UEM-specific error type into a PSR-3 compliant log level.
     *
     * @param string $uemType The error type from the UEM configuration.
     * @return string A valid PSR-3 log level string (e.g., 'error', 'warning').
     */
    private function mapUemTypeToPsrLevel(string $uemType): string
    {
        return match (strtolower($uemType)) {
            'critical', 'fatal' => 'critical',
            'server_error', 'error' => 'error',
            'warning' => 'warning',
            'notice' => 'notice',
            'info' => 'info',
            default => 'error',
        };
    }
}