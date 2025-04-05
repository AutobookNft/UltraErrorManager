<?php

declare(strict_types=1);

namespace Ultra\ErrorManager;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Exceptions\UltraErrorException;
use Ultra\UltraLogManager\Facades\UltraLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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
     * @var array
     */
    protected $handlers = [];

    /**
     * Custom errors defined at runtime
     *
     * @var array
     */
    protected $customErrors = [];

    /**
     * Initialize the UltraErrorManager.
     *
     * Loads and registers default error handlers defined in the configuration.
     * Logs initialization events through UltraLog with full class traceability.
     */
    public function __construct()
    {
        UltraLog::info('UltraError', 'Initializing UltraErrorManager');

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
     * The handler must implement ErrorHandlerInterface.
     *
     * @param ErrorHandlerInterface $handler  The handler instance to register
     * @return $this                          For method chaining
     */
    public function registerHandler(ErrorHandlerInterface $handler)
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
     * @return array  An array of registered ErrorHandlerInterface instances
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Define a new error type dynamically at runtime.
     *
     * This allows extending the error manager without changing static config files.
     *
     * @param string $errorCode  Unique error code identifier
     * @param array  $config     Error configuration array (user_message, http_status, etc.)
     * @return $this             For method chaining
     */
    public function defineError(string $errorCode, array $config)
    {
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
     * Searches first among runtime-defined errors, then falls back to static config.
     * If the error is not found, logs a warning and returns null.
     *
     * @param string $errorCode  The error code to resolve
     * @return array|null        The error configuration, or null if not found
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
     * This method orchestrates the full error lifecycle:
     * - Resolves configuration (including fallback layers)
     * - Logs all stages with UltraLog
     * - Executes registered error handlers
     * - Returns or throws based on context and configuration
     *
     * @param string         $errorCode   The string code representing the error
     * @param array          $context     Optional contextual information for the error
     * @param \Throwable|null $exception  Optional original exception (if any)
     * @param bool           $throw       Whether to throw an UltraErrorException instead of returning a response
     * @return JsonResponse|RedirectResponse
     * @throws UltraErrorException        If configured to throw, or all resolution fails
     */
    public function handle(string $errorCode, array $context = [], ?\Throwable $exception = null, bool $throw = false)
    {
        UltraLog::info('UltraError', "Handling error [{$errorCode}]", ['context' => $context]);

        $errorConfig = $this->resolveErrorConfig($errorCode, $context, $exception);
        $errorInfo = $this->prepareErrorInfo($errorCode, $errorConfig, $context, $exception);

        $this->dispatchHandlers($errorCode, $errorConfig, $context, $exception);
        UltraLog::info('UltraError', "Processed error with handlers", ['code' => $errorCode]);

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
     * Resolve error configuration with fallback strategy.
     *
     * Handles error fallback logic across three levels:
     * - Primary error config
     * - Static UNDEFINED_ERROR_CODE
     * - Static fallback_error config
     *
     * @param string $errorCode
     * @param array $context
     * @param \Throwable|null $exception
     * @return array
     * @throws UltraErrorException
     */
    protected function resolveErrorConfig(string &$errorCode, array &$context, ?\Throwable $exception = null): array
    {
        $config = $this->getErrorConfig($errorCode);

        if ($config) return $config;

        UltraLog::warning('UltraError', "Undefined error code: [{$errorCode}]. Attempting fallback.", $context);

        // Attempt fallback: UNDEFINED_ERROR_CODE
        $context['_original_code'] = $errorCode;
        $errorCode = 'UNDEFINED_ERROR_CODE';
        $config = $this->getErrorConfig($errorCode);

        if ($config) return $config;

        // Final fallback: static fallback_error block
        UltraLog::critical('UltraError', 'Missing config for UNDEFINED_ERROR_CODE. Trying fallback_error.', []);
        $fallback = Config::get('error-manager.fallback_error');

        if (!$fallback || !is_array($fallback)) {
            UltraLog::emergency('UltraError', 'No fallback configuration available. Throwing hard.', []);
            throw new UltraErrorException(
                "Fallback failed: no configuration available.",
                500,
                $exception,
                'FATAL_FALLBACK_FAILURE'
            );
        }

        $errorCode = 'FALLBACK_ERROR';
        return $fallback;
    }

    /**
     * Execute all registered handlers for the current error.
     *
     * Each handler is tested against the configuration to decide whether to handle the error.
     *
     * @param string $errorCode
     * @param array $errorConfig
     * @param array $context
     * @param \Throwable|null $exception
     * @return void
     */
    protected function dispatchHandlers(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception = null): void
    {
        $count = 0;

        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($errorConfig)) {
                $count++;
                UltraLog::debug('UltraError', 'Executing handler', ['handler' => get_class($handler)]);
                $handler->handle($errorCode, $errorConfig, $context, $exception);
            }
        }

        UltraLog::info('UltraError', "Dispatched {$count} handlers", ['code' => $errorCode]);
    }

    /**
     * Prepare complete error information
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data for the error
     * @param \Throwable|null $exception Original exception (if available)
     * @return array Complete error.mybatisplus
     */
    protected function prepareErrorInfo($errorCode, array $errorConfig, array $context, ?\Throwable $exception = null)
    {
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

        // Add exception information if present
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
     * @param array  $errorConfig     The error configuration array
     * @param array  $context         Associative array of contextual replacements
     * @param string $directKey       The config key that holds the direct message string
     * @param string $translationKey  The config key that holds the translation key
     * @return string                 A fully formatted message string
     */
    protected function formatMessage(array $errorConfig, array $context, $directKey, $translationKey): string
    {
        // Prefer using a translation key if provided
        if (isset($errorConfig[$translationKey])) {
            $message = __($errorConfig[$translationKey], $context);
            UltraLog::debug('UltraError', 'Using translated message', ['key' => $errorConfig[$translationKey]]);
        }
        // Fallback to direct message with manual placeholder replacement
        elseif (isset($errorConfig[$directKey])) {
            $message = $errorConfig[$directKey];
            UltraLog::debug('UltraError', 'Using direct message', ['source' => $directKey]);

            foreach ($context as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $message = str_replace(":{$key}", $value, $message);
                }
            }
        }
        // Final fallback if no message is available
        else {
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
     * @param array $errorInfo
     * @return JsonResponse|RedirectResponse
     */
    protected function buildResponse(array $errorInfo)
    {
        // For AJAX or API requests, return JSON structure
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

        // For standard web requests — handle based on blocking level
        if ($errorInfo['blocking'] === 'blocking') {
            UltraLog::info('UltraError', 'Aborting request due to blocking error', [
                'code' => $errorInfo['error_code'],
                'status' => $errorInfo['http_status_code']
            ]);

            abort($errorInfo['http_status_code'], $errorInfo['user_message']);
        }

        // Non-blocking: flash message and redirect back
        UltraLog::info('UltraError', 'Flashing non-blocking error and returning back', [
            'code' => $errorInfo['error_code'],
            'mode' => $errorInfo['display_mode']
        ]);

        session()->flash('error_' . $errorInfo['display_mode'], $errorInfo['user_message']);
        session()->flash('error_info', $errorInfo);

        return back()->withInput();
    }
}