<?php

namespace Ultra\ErrorManager;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Exceptions\UltraErrorException;

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
     * Configure the ErrorManager
     */
    public function __construct()
    {
        Log::info('Ultra Error Manager: Initializing');

        // Load default handlers if specified in configuration
        $defaultHandlers = Config::get('error-manager.default_handlers', []);
        foreach ($defaultHandlers as $handlerClass) {
            if (class_exists($handlerClass)) {
                $this->registerHandler(new $handlerClass());
                Log::debug("Ultra Error Manager: Registered default handler [{$handlerClass}]");
            } else {
                Log::warning("Ultra Error Manager: Default handler class not found [{$handlerClass}]");
            }
        }
    }

    /**
     * Register a new error handler
     *
     * @param ErrorHandlerInterface $handler The handler instance to register
     * @return $this For method chaining
     */
    public function registerHandler(ErrorHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
        Log::debug('Ultra Error Manager: Registered handler [' . get_class($handler) . ']');
        return $this;
    }

    /**
     * Get all registered handlers
     *
     * @return array Array of registered handler instances
     */
    public function getHandlers()
    {
        return $this->handlers;
    }

    /**
     * Define a new error type at runtime
     *
     * @param string $errorCode Unique error code identifier
     * @param array $config Error configuration
     * @return $this For method chaining
     */
    public function defineError($errorCode, array $config)
    {
        $this->customErrors[$errorCode] = $config;
        Log::debug("Ultra Error Manager: Defined custom error [{$errorCode}]", ['config' => $config]);
        return $this;
    }

    /**
     * Get configuration for a specific error
     *
     * @param string $errorCode The error code to look up
     * @return array|null The error configuration or null if not found
     */
    public function getErrorConfig($errorCode)
    {
        // First check runtime-defined custom errors
        if (isset($this->customErrors[$errorCode])) {
            return $this->customErrors[$errorCode];
        }

        // Then check static configuration
        $config = Config::get("error-manager.errors.{$errorCode}");

        if ($config === null) {
            Log::warning("Ultra Error Manager: No configuration found for error code [{$errorCode}]");
        }

        return $config;
    }

    /**
     * Handle a specific error
     *
     * This is the main method for processing errors through the manager.
     * It coordinates all handlers and builds an appropriate response.
     *
     * @param string $errorCode Error code identifier
     * @param array $context Contextual data for the error
     * @param \Throwable|null $exception Original exception (if available)
     * @return mixed Formatted response based on error type
     * @throws UltraErrorException If the error cannot be handled
     */
    public function handle($errorCode, array $context = [], \Throwable $exception = null)
    {
        Log::info("Ultra Error Manager: Handling error [{$errorCode}]", ['context' => $context]);

        // Get error configuration
        $errorConfig = $this->getErrorConfig($errorCode);

        // If error is not defined, log and throw an exception
        if (!$errorConfig) {
            Log::error("Ultra Error Manager: Undefined error code [{$errorCode}]", $context);
            throw new UltraErrorException(
                "Undefined error code: {$errorCode}",
                500,
                $exception,
                'UNDEFINED_ERROR_CODE'
            );
        }

        // Add information to the error
        $errorInfo = $this->prepareErrorInfo($errorCode, $errorConfig, $context, $exception);

        Log::debug("Ultra Error Manager: Prepared error info", ['errorInfo' => $errorInfo]);

        // Process error through all registered handlers
        $handlerCount = 0;
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($errorConfig)) {
                $handlerCount++;
                Log::debug("Ultra Error Manager: Executing handler [" . get_class($handler) . "]");
                $handler->handle($errorCode, $errorConfig, $context, $exception);
            }
        }

        Log::info("Ultra Error Manager: {$handlerCount} handlers processed error [{$errorCode}]");

        // Return appropriate response based on error type
        return $this->buildResponse($errorInfo);
    }

    /**
     * Prepare complete error information
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data for the error
     * @param \Throwable|null $exception Original exception (if available)
     * @return array Complete error information array
     */
    protected function prepareErrorInfo($errorCode, array $errorConfig, array $context, \Throwable $exception = null)
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
     * Format a message by replacing placeholders and supporting localization
     *
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data for the error
     * @param string $directKey Key for direct message in config
     * @param string $translationKey Key for translation key in config
     * @return string Formatted message
     */
    protected function formatMessage(array $errorConfig, array $context, $directKey, $translationKey)
    {
        // First try to use a translation key if present
        if (isset($errorConfig[$translationKey])) {
            $message = __($errorConfig[$translationKey], $context);
            Log::debug("Ultra Error Manager: Using translated message from key [{$errorConfig[$translationKey]}]");
        }
        // Otherwise use direct message if present
        elseif (isset($errorConfig[$directKey])) {
            $message = $errorConfig[$directKey];
            Log::debug("Ultra Error Manager: Using direct message from [{$directKey}]");

            // Replace ":key" placeholders with values from context
            foreach ($context as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $message = str_replace(":{$key}", $value, $message);
                }
            }
        }
        // Use a generic fallback if no message is available
        else {
            $message = "An error has occurred";
            Log::debug("Ultra Error Manager: No message found, using generic fallback");
        }

        return $message;
    }

    /**
     * Build the response based on error information
     *
     * Adapts the response format based on request type (API/AJAX vs web)
     * and error blocking level.
     *
     * @param array $errorInfo Complete error information
     * @return mixed Response appropriate for the context
     */
    protected function buildResponse(array $errorInfo)
    {
        // In an AJAX or API request, return a JSON response
        if (request()->expectsJson() || request()->is('api/*')) {
            Log::info("Ultra Error Manager: Returning JSON response for error [{$errorInfo['error_code']}]");
            return response()->json([
                'error' => $errorInfo['error_code'],
                'message' => $errorInfo['user_message'],
                'blocking' => $errorInfo['blocking'],
                'display_mode' => $errorInfo['display_mode']
            ], $errorInfo['http_status_code']);
        }

        // For normal web requests, depends on blocking type
        if ($errorInfo['blocking'] === 'blocking') {
            // Blocking error, redirect to error page or show error view
            Log::info("Ultra Error Manager: Aborting with status [{$errorInfo['http_status_code']}] for blocking error [{$errorInfo['error_code']}]");
            abort($errorInfo['http_status_code'], $errorInfo['user_message']);
        } else {
            // Non-blocking error, save in flash session and continue
            Log::info("Ultra Error Manager: Flashing error message for non-blocking error [{$errorInfo['error_code']}]");
            session()->flash('error_' . $errorInfo['display_mode'], $errorInfo['user_message']);
            session()->flash('error_info', $errorInfo);

            // Going back or continuing depends on application context
            return back()->withInput();
        }
    }
}
