<?php

declare(strict_types=1);

namespace Ultra\ErrorManager;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Exceptions\UltraErrorException;
use Ultra\UltraLogManager\Facades\UltraLog;

/**
 * ErrorManager - Central error management system
 *
 * This class implements a modified Singleton pattern (accessible via Facade)
 * and serves as the central point for handling all errors in the application.
 * It uses a modular approach with specialized handlers for different error types.
 *
 * @package Ultra\ErrorManager
 */
class ErrorManager
{
    /**
     * Registry of error handlers
     *
     * This holds all registered error handlers that can be used to process
     * different types of errors based on configuration or runtime definitions.
     *
     * @var array<ErrorHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * Custom errors defined at runtime
     *
     * These are errors defined dynamically during the application lifecycle,
     * allowing new error types to be added without modifying the configuration files.
     *
     * @var array<string, array>
     */
    protected array $customErrors = [];

    /**
     * Initialize the UltraErrorManager.
     *
     * Loads and registers default error handlers defined in the configuration.
     * Logs initialization events through UltraLog with full class traceability.
     * This process ensures that all standard error handling mechanisms are in place
     * and ready to process errors as they occur.
     */
    public function __construct()
    {
        UltraLog::info('UltraError', 'Initializing UltraErrorManager');

        // Load default handlers from configuration
        $defaultHandlers = Config::get('error-manager.default_handlers', []);

        foreach ($defaultHandlers as $handlerClass) {
            if (class_exists($handlerClass)) {
                $this->registerHandler(new $handlerClass());
                UltraLog::debug('UltraError', 'Registered default handler', [
                    'handler' => $handlerClass
                ]);
            } else {
                UltraLog::warning('UltraError', 'Default handler class not found', [
                    'handler' => $handlerClass
                ]);
            }
        }
    }

    /**
     * Register a new error handler.
     *
     * This method allows you to register a handler class that will be responsible for
     * processing specific types of errors. The handler must implement the
     * ErrorHandlerInterface and define the `handle()` method to perform custom actions.
     *
     * @param ErrorHandlerInterface $handler The handler instance to register
     * @return $this For method chaining
     */
    public function registerHandler(ErrorHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        UltraLog::debug('UltraError', 'Registered error handler', [
            'class' => get_class($handler)
        ]);

        return $this;
    }

    /**
     * Retrieve all currently registered error handlers.
     *
     * This method returns an array of all the error handlers that have been registered
     * with the ErrorManager. This is useful for debugging or inspecting which handlers
     * are available at runtime.
     *
     * @return array<ErrorHandlerInterface> An array of registered ErrorHandlerInterface instances
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Define a new error type dynamically at runtime.
     *
     * This allows extending the error manager without changing static config files.
     * You can define new error codes and associate them with specific configurations
     * like user messages, HTTP status codes, and more.
     *
     * @param string $errorCode Unique error code identifier
     * @param array  $config Error configuration array (user_message, http_status, etc.)
     * @return $this For method chaining
     */
    public function defineError(string $errorCode, array $config): self
    {
        if (empty($errorCode) || empty($config)) {
            UltraLog::warning('UltraError', 'Attempted to define an invalid runtime error', [
                'code' => $errorCode,
                'config' => $config
            ]);
            return $this;
        }

        $this->customErrors[$errorCode] = $config;

        UltraLog::debug('UltraError', 'Defined runtime error type', [
            'code' => $errorCode,
            'config' => $config
        ]);

        return $this;
    }

    /**
     * Get the configuration for a specific error code.
     *
     * This method first checks the custom errors defined at runtime. If the error code
     * is not found, it falls back to the static configuration loaded from the config files.
     * If no configuration is found, a warning is logged and `null` is returned.
     *
     * @param string $errorCode The error code to resolve
     * @return array|null The error configuration, or null if not found
     */
    public function getErrorConfig(string $errorCode): ?array
    {
        if (isset($this->customErrors[$errorCode])) {
            return $this->customErrors[$errorCode];
        }

        $config = Config::get("error-manager.errors.{$errorCode}");

        if ($config === null) {
            UltraLog::warning('UltraError', 'Error code configuration not found', [
                'code' => $errorCode
            ]);
        }

        return $config;
    }

    /**
     * Handle an Ultra error in a structured and context-aware way.
     *
     * This method processes the error based on its code, executes registered handlers,
     * logs the event through UltraLog, and either returns an appropriate error response
     * or throws an UltraErrorException if requested.
     *
     * @param string $errorCode The string code representing the error
     * @param array $context Optional contextual information for the error
     * @param \Throwable|null $exception Optional original exception (if any)
     * @param bool $throw Whether to throw an UltraErrorException instead of returning a response
     * @return mixed An UltraError response object, or throws UltraErrorException if $throw = true
     * @throws UltraErrorException If the error is undefined, or if $throw is set to true
     */
    public function handle(string $errorCode, array $context = [], ?\Throwable $exception = null, bool $throw = false): mixed
    {
        UltraLog::info('UltraError', "Handling error [{$errorCode}]", ['context' => $context]);

        $errorConfig = $this->getErrorConfig($errorCode);

        if (!$errorConfig) {
            UltraLog::error('UltraError', "Undefined error code: [{$errorCode}]", $context);

            throw new UltraErrorException(
                "Undefined error code: {$errorCode}",
                500,
                $exception,
                'UNDEFINED_ERROR_CODE'
            );
        }

        $errorInfo = $this->prepareErrorInfo($errorCode, $errorConfig, $context, $exception);

        UltraLog::debug('UltraError', 'Prepared error info', ['errorInfo' => $errorInfo]);

        $handlerCount = 0;
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($errorConfig)) {
                $handlerCount++;
                UltraLog::debug('UltraError', 'Executing handler', ['handler' => get_class($handler)]);
                $handler->handle($errorCode, $errorConfig, $context, $exception);
            }
        }

        UltraLog::info('UltraError', "Processed with {$handlerCount} handlers", ['errorCode' => $errorCode]);

        if ($throw) {
            throw new UltraErrorException(
                "UltraError: {$errorCode}",
                0,
                $exception,
                $errorCode,
                $context
            );
        }

        return $this->buildResponse($errorInfo);
    }

    /**
     * Prepare complete error information
     *
     * This method combines the error code, configuration, context, and exception data
     * into a single structured array that can be passed to handlers, formatted, or used
     * in response generation.
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data for the error
     * @param \Throwable|null $exception Original exception (if available)
     * @return array Complete error information array
     */
    protected function prepareErrorInfo(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception = null): array
    {
        /** @var array<string, mixed> $errorInfo */
        $errorInfo = [
            'error_code' => $errorCode,
            'type' => $errorConfig['type'] ?? 'error',
            'blocking' => $errorConfig['blocking'] ?? 'blocking',
            'message' => $this->formatMessage($errorConfig, $context, 'dev_message', 'dev_message_key'),
            'user_message' => $this->formatMessage($errorConfig, $context, 'user_message', 'user_message_key'),
            'http_status_code' => $errorConfig['http_status_code'] ?? 500,
            'context' => $context,
            'display_mode' => $errorConfig['msg_to'] ?? 'div',
            'timestamp' => now(),
        ];

        if ($exception) {
            $errorInfo['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return $errorInfo;
    }

    /**
     * Format a localized or direct error message with contextual data.
     *
     * This method selects a message from the error configuration, prioritizing
     * translation keys if available. It then replaces any `:placeholder` tokens
     * in the message using the provided context array.
     *
     * @param array  $errorConfig The error configuration array
     * @param array  $context Associative array of contextual replacements
     * @param string $directKey The config key that holds the direct message string
     * @param string $translationKey The config key that holds the translation key
     * @return string A fully formatted message string
     */
    protected function formatMessage(array $errorConfig, array $context, string $directKey, string $translationKey): string
    {
        if (isset($errorConfig[$translationKey])) {
            $message = (string) __($errorConfig[$translationKey], $context); 
            UltraLog::debug('UltraError', 'Using translated message', ['key' => $errorConfig[$translationKey]]); 
        } elseif (isset($errorConfig[$directKey])) {
            $message = $errorConfig[$directKey];
            UltraLog::debug('UltraError', 'Using direct message', ['source' => $directKey]);
            foreach ($context as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $message = str_replace(":{$key}", (string) $value, $message);
                }
            }
        } else {
            $message = "An error has occurred";
            UltraLog::debug('UltraError', 'No message key found, using fallback');
        }
    
        return $message;
    }
    
    /**
     * Build the appropriate error response based on the request context and error severity.
     *
     * This method determines how to return or display an error based on:
     * - Request type (AJAX/API vs standard web)
     * - Blocking level (blocking vs non-blocking)
     *
     * If blocking, it aborts with the appropriate HTTP status.
     * If non-blocking, it flashes the message and redirects back.
     *
     * @param array<string, mixed> $errorInfo Complete error information as generated by prepareErrorInfo()
     * @return mixed A JSON response, HTTP redirect, or abort depending on context
     */
    protected function buildResponse(array $errorInfo): mixed
    {
        if (request()->expectsJson() || request()->is('api/*')) {
            UltraLog::info('UltraError', 'Returning JSON error response', [
                'code' => $errorInfo['error_code'],
                'status' => $errorInfo['http_status_code'],
            ]);
    
            return response()->json([
                'error' => $errorInfo['error_code'],
                'message' => $errorInfo['user_message'],
                'blocking' => $errorInfo['blocking'],
                'display_mode' => $errorInfo['display_mode']
            ], $errorInfo['http_status_code']);
        }
    
        if ($errorInfo['blocking'] === 'blocking') {
            UltraLog::info('UltraError', 'Aborting request due to blocking error', [
                'code' => $errorInfo['error_code'],
                'status' => $errorInfo['http_status_code']
            ]);
    
            abort($errorInfo['http_status_code'], $errorInfo['user_message']);
        }
    
        UltraLog::info('UltraError', 'Flashing non-blocking error and returning back', [
            'code' => $errorInfo['error_code'],
            'mode' => $errorInfo['display_mode']
        ]);
    
        session()->flash('error_' . $errorInfo['display_mode'], $errorInfo['user_message']);
        session()->flash('error_info', $errorInfo);
    
        return back()->withInput();
    }
}