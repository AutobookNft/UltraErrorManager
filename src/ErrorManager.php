<?php

declare(strict_types=1);

namespace Ultra\ErrorManager;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Exceptions\UltraErrorException;
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface;
use Ultra\UltraLogManager\Facades\UltraLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * ErrorManager – Oracoded Core Error Handler
 *
 * 🎯 Central error management hub for the Ultra ecosystem.
 * 🧱 Singleton-style orchestrator for error lifecycle control.
 * 📡 Communicates across layers via structured context injection.
 * 🧪 Fully testable, modular, and logging-safe via UltraLog.
 */
class ErrorManager implements ErrorManagerInterface
{
    /**
     * 🧱 @structural Handler registry
     *
     * Stores all registered runtime error handlers.
     *
     * @var array<int, ErrorHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * 🧱 @structural Runtime-defined error configurations
     *
     * Allows dynamic expansion of the known error codes.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $customErrors = [];

    /**
     * 🎯 Lifecycle Bootstrap
     *
     * Initializes the error manager instance and registers
     * all default handlers defined in the Laravel configuration.
     * Ensures traceable startup via UltraLog.
     *
     * 🪵 Logs: registration, missing classes
     * 🧪 Covered indirectly by integration tests using fallback config
     *
     * @return void
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
     * 🧱 Register a runtime error handler
     *
     * Adds a handler to the internal stack. Handlers must implement
     * `ErrorHandlerInterface`, and will be invoked in the dispatch phase.
     * Useful for customizing error flows dynamically.
     *
     * 🪵 Logs the registration event with handler class name
     * 🧱 Alters the dispatch stack for subsequent errors
     * 🧪 Covered implicitly via integration tests that trigger handlers
     *
     * @param ErrorHandlerInterface $handler The handler instance to register
     * @return self For fluent chaining
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
     * 🧱 Retrieve all registered error handlers
     *
     * Returns the current list of error handlers stored in the internal registry.
     * Useful for inspection, debugging, or conditional manipulation in runtime.
     *
     * 🧪 Safe to call at any point after instantiation
     * 📡 Used internally during handler dispatching
     *
     * @return array<int, ErrorHandlerInterface> Registered handler instances
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }


     /**
     * 🔧 Define a custom error dynamically at runtime
     *
     * Adds a new error configuration into the in-memory registry.
     * This allows external modules, test suites, or CLI tools to
     * define behavior without altering the static config files.
     *
     * 🧱 Expands `$customErrors` structure
     * 🧪 Commonly used in test scaffolds and dynamic feature boot
     * 🪵 Logs definition with full config trace
     *
     * @param string $errorCode Unique identifier for the new error
     * @param array $config Associative array describing the error structure
     *                      (e.g. message, http_status_code, type, etc.)
     * @return self For fluent method chaining
     */
    public function defineError(string $errorCode, array $config): self
    {
        $this->customErrors[$errorCode] = $config;

        UltraLog::debug('UltraError', 'Defined runtime error type', [
            'code' => $errorCode,
            'config' => $config
        ]);

        return $this;
    }


        /**
     * 🧠 Resolve configuration for a specific error code
     *
     * Attempts to retrieve the configuration associated with the given error code.
     * Prioritizes runtime-defined errors, then falls back to Laravel config files.
     * Logs a warning if no configuration is found at either level.
     *
     * 📡 Central gateway for `handle()` and all dispatch logic
     * 🧪 Queried directly by resolveErrorConfig()
     * 🪵 Logs absence of known error config
     *
     * @param string $errorCode The symbolic identifier of the error
     * @return array|null The resolved configuration, or null if not found
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
     * 🧭 Primary Orchestration Entry Point
     *
     * Executes the full UltraError lifecycle for the given error code:
     * - Resolves error configuration
     * - Prepares contextual error info
     * - Dispatches all registered handlers
     * - Logs lifecycle events
     * - Throws or returns structured response
     *
     * 🧱 Core gateway for exception management
     * 🧪 Tested via response and thrown exception scenarios
     * 📡 Consumed by UI, API, background tasks
     * 🚨 May throw UltraErrorException depending on context
     *
     * @param string $errorCode Symbolic code for the error (e.g. INVALID_TOKEN)
     * @param array $context Optional context passed to message substitution and logging
     * @param \Throwable|null $exception Original exception to link with UltraError
     * @param bool $throw Whether to throw or return a structured response
     *
     * @return JsonResponse|RedirectResponse
     *
     * @throws UltraErrorException
     */

    public function handle(string $errorCode, array $context = [], ?\Throwable $exception = null, bool $throw = false): JsonResponse|RedirectResponse
    {
        // Ensure context is an array
        if (!is_array($context)) {
            $context = [];
        }

        // Log the error handling start
        UltraLog::info('UltraError', 'Starting error handling', [
            'code' => $errorCode,
            'context' => $context
        ]);

        // Handle the error lifecycle
        return $this->processError($errorCode, $context, $exception, $throw);
    }
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
     * 🧷 Fallback Resolver: Determine error configuration
     *
     * Resolves the effective configuration for a given error code.
     * Applies fallback logic in cascading order:
     *
     * 1. Runtime-defined error (`customErrors`)
     * 2. Static config (`error-manager.errors`)
     * 3. Special code: `UNDEFINED_ERROR_CODE`
     * 4. Final fallback block: `fallback_error`
     *
     * Throws UltraErrorException if no fallback can be resolved.
     *
     * 🧪 Covered via simulated unknown codes
     * 🪵 Logs all resolution attempts and failure stages
     * 🚨 Critical to error safety in unhandled scenarios
     *
     * @param string $errorCode Incoming symbolic code to resolve (passed by ref for fallback overwrite)
     * @param array $context Associated context data
     * @param \Throwable|null $exception Optional original throwable
     *
     * @return array Resolved configuration
     *
     * @throws UltraErrorException If resolution fails at all fallback layers
     */
    protected function resolveErrorConfig(string &$errorCode, array &$context, ?\Throwable $exception = null): array
    {
        $config = $this->getErrorConfig($errorCode);

        if ($config) return $config;

        UltraLog::warning('UltraError', "Undefined error code: [{$errorCode}]. Attempting fallback.", $context);

        // Fallback 1: symbolic remap to UNDEFINED_ERROR_CODE
        $context['_original_code'] = $errorCode;
        $errorCode = 'UNDEFINED_ERROR_CODE';
        $config = $this->getErrorConfig($errorCode);

        if ($config) return $config;

        // Fallback 2: hardcoded fallback_error block
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
     * 🧠 Dispatch active error handlers
     *
     * Iterates over all registered handlers and invokes those
     * whose `shouldHandle()` method returns true for the current error config.
     *
     * Each handler is invoked with the full error context, including
     * original exception and configuration metadata.
     *
     * 🧱 Core of the dynamic extensibility of the error system
     * 🧪 Side-effects: logs, alerts, integrations
     * 🪵 Logs each dispatched handler by class name
     *
     * @param string $errorCode The symbolic error code (post-fallback)
     * @param array $errorConfig Resolved configuration array
     * @param array $context Contextual values for this error instance
     * @param \Throwable|null $exception Optional linked exception
     * @return void
     */
    protected function dispatchHandlers(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception = null): void
    {
        $count = 0;

        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($errorConfig)) {
                $count++;
                UltraLog::debug('UltraError', 'Executing handler', [
                    'handler' => get_class($handler)
                ]);

                $handler->handle($errorCode, $errorConfig, $context, $exception);
            }
        }

        UltraLog::info('UltraError', "Dispatched {$count} handlers", ['code' => $errorCode]);
    }


    /**
     * 🧠 Prepare semantic error identity
     *
     * Assembles a structured representation of the error from config, context,
     * and optional exception. This data is later used to construct a response
     * or to be consumed by UI elements or external systems.
     *
     * Includes all information needed to render, debug, and track the error:
     * - Technical and user-facing messages
     * - Display modality
     * - Exception metadata (if present)
     * - HTTP code, type, blocking level, timestamp
     *
     * 🧱 Core to the buildResponse() logic
     * 🧪 Always present after `handle()`
     * 📡 Transmitted in API or Flash error flows
     *
     * @param string $errorCode The resolved error code
     * @param array $errorConfig The resolved configuration array
     * @param array $context Any contextual values passed in by the trigger
     * @param \Throwable|null $exception Optional original exception
     * @return array Associative array representing the full error
     */
    protected function prepareErrorInfo(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception = null): array
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
     * 🧬 Message Generator: Translate or render error message
     *
     * Generates a human-readable message by selecting either a static
     * message or a translation key, and interpolating placeholders using context.
     *
     * Priority:
     * 1. Translation key (e.g. `dev_message_key`)
     * 2. Direct string (`dev_message`)
     * 3. Fallback to generic default
     *
     * 🧠 Used for both developer and user-facing messages
     * 🧪 Placeholder substitution tested via `prepareErrorInfo()`
     * 🪵 Logs source of message selection
     *
     * @param array $errorConfig Full error config array
     * @param array $context Context for substitution (e.g. :email, :field)
     * @param string $directKey Config key for direct message
     * @param string $translationKey Config key for i18n lookup
     * @return string Resolved message string
     */
    protected function formatMessage(array $errorConfig, array $context, string $directKey, string $translationKey): string
    {
        if (isset($errorConfig[$translationKey])) {
            $message = __($errorConfig[$translationKey], $context);
            UltraLog::debug('UltraError', 'Using translated message', ['key' => $errorConfig[$translationKey]]);
        }

        elseif (isset($errorConfig[$directKey])) {
            $message = $errorConfig[$directKey];
            UltraLog::debug('UltraError', 'Using direct message', ['source' => $directKey]);

            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $message = str_replace(":{$key}", (string) $value, $message);
                }
            }
        }

        else {
            $message = "An error has occurred";
            UltraLog::debug('UltraError', 'No message key found, using fallback');
        }

        return $message;
    }


    /**
     * 🧭 Response Resolver: Format the outbound error response
     *
     * Chooses how to return the error depending on request context and error blocking level.
     * - API/AJAX: returns a JsonResponse with structured payload
     * - Blocking web: throws HTTP exception (via abort)
     * - Non-blocking web: flashes message and redirects back
     *
     * 🧪 Covered via handle() tests across display modes
     * 🧱 Core delivery mechanism in user-facing flows
     * 🪵 Logs response method chosen and related status/code
     *
     * @param array $errorInfo Result of prepareErrorInfo()
     * @return JsonResponse|RedirectResponse
     */
    protected function buildResponse(array $errorInfo): JsonResponse|RedirectResponse
    {
        // API/AJAX mode
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

        // Blocking flow
        if ($errorInfo['blocking'] === 'blocking') {
            UltraLog::info('UltraError', 'Aborting request due to blocking error', [
                'code' => $errorInfo['error_code'],
                'status' => $errorInfo['http_status_code']
            ]);

            abort($errorInfo['http_status_code'], $errorInfo['user_message']);
        }

        // Non-blocking flow
        UltraLog::info('UltraError', 'Flashing non-blocking error and returning back', [
            'code' => $errorInfo['error_code'],
            'mode' => $errorInfo['display_mode']
        ]);

        session()->flash('error_' . $errorInfo['display_mode'], $errorInfo['user_message']);
        session()->flash('error_info', $errorInfo);

        return back()->withInput();
    }

}