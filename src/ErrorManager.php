<?php

declare(strict_types=1);

namespace Ultra\ErrorManager;

use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Ultra\UltraLogManager\UltraLogManager;
use Ultra\ErrorManager\Exceptions\UltraErrorException;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface;

/**
 * 🎯 ErrorManager – Oracoded Core Error Handler
 *
 * Central error management hub for the Ultra ecosystem, designed to orchestrate
 * error handling across layers with explicit intent and testability.
 * Implements a singleton-style dispatcher for handlers, ensuring predictable
 * error lifecycle management.
 *
 * 🧱 Structure:
 * - Maintains a registry of runtime handlers
 * - Stores dynamic error configurations
 * - Delegates logging, translation, and response building to injected dependencies
 *
 * 📡 Communicates:
 * - With handlers via `dispatchHandlers`
 * - With external systems via UltraLogManager and translator
 * - With HTTP/CLI contexts via structured responses or exceptions
 *
 * 🧪 Testable:
 * - All dependencies are injected
 * - No static calls, fully mockable
 * - Safe for CLI and test environments
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
     * Allows dynamic expansion of error codes beyond static config.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $customErrors = [];

    /**
     * 🧱 @dependency Logger instance
     *
     * Handles all logging operations using UltraLogManager, injected for consistency
     * with the Ultra ecosystem.
     *
     * @var UltraLogManager
     */
    protected UltraLogManager $logger;

    /**
     * 🧱 @dependency Translator instance
     *
     * Manages translation of error messages, injected for testability.
     *
     * @var TranslatorContract
     */
    protected TranslatorContract $translator;

    /**
     * 🧱 @dependency Configuration array
     *
     * Static error configurations, injected to replace Config facade.
     *
     * @var array
     */
    protected array $config;

    /**
     * 🎯 Lifecycle Bootstrap
     *
     * Initializes the error manager with injected dependencies and registers
     * default handlers from configuration.
     *
     * 🧱 Structure:
     * - Stores logger, translator, and config for reuse
     * - Registers default handlers dynamically
     *
     * 📡 Communicates:
     * - Logs initialization via injected UltraLogManager
     *
     * 🧪 Testable:
     * - Dependencies mockable via constructor
     * - No static calls
     *
     * @param UltraLogManager $logger Logger for error tracking
     * @param TranslatorContract $translator Translator for message localization
     * @param array $config Configuration array (default handlers, error definitions)
     */
    public function __construct(UltraLogManager $logger, TranslatorContract $translator, array $config = [])
    {
        $this->logger = $logger;
        $this->translator = $translator;
        $this->config = $config;

        $this->logger->info('Initializing UltraErrorManager', []);
    }

    /**
     * 🎯 Register a runtime error handler
     *
     * Adds a handler to the internal stack for dynamic error processing.
     *
     * 🧱 Structure:
     * - Appends handler to `$handlers` array
     *
     * 📡 Communicates:
     * - Logs registration event with handler class
     *
     * 🧪 Testable:
     * - Handler injection mockable
     * - Registry inspectable via `getHandlers`
     *
     * @param ErrorHandlerInterface $handler The handler instance to register
     * @return self For fluent chaining
     */
    public function registerHandler(ErrorHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;
        $this->logger->debug('Registered error handler', [
            'class' => get_class($handler)
        ]);
        return $this;
    }

    /**
     * 🎯 Retrieve all registered error handlers
     *
     * Provides access to the current handler stack for inspection or modification.
     *
     * 🧱 Structure:
     * - Returns `$handlers` array as-is
     *
     * 📡 Communicates:
     * - Used by external systems to verify handler registration
     *
     * 🧪 Testable:
     * - Pure getter, no side effects
     *
     * @return array<int, ErrorHandlerInterface> Registered handler instances
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * 🎯 Define a custom error dynamically at runtime
     *
     * Expands the error configuration set with runtime-defined errors.
     *
     * 🧱 Structure:
     * - Adds to `$customErrors` array
     *
     * 📡 Communicates:
     * - Logs definition event with config details
     *
     * 🧪 Testable:
     * - Config injection mockable
     * - Inspectable via `getErrorConfig`
     *
     * @param string $errorCode Unique identifier for the new error
     * @param array $config Associative array describing the error structure
     * @return self For fluent chaining
     */
    public function defineError(string $errorCode, array $config): self
    {
        $this->customErrors[$errorCode] = $config;
        $this->logger->debug('Defined runtime error type', [
            'code' => $errorCode,
            'config' => $config
        ]);
        return $this;
    }

    /**
     * 🎯 Resolve configuration for a specific error code
     *
     * Retrieves error configuration from runtime or static sources.
     *
     * 🧱 Structure:
     * - Prioritizes `$customErrors` over `$config['errors']`
     *
     * 📡 Communicates:
     * - Logs missing config attempts
     *
     * 🧪 Testable:
     * - Config mockable via constructor
     * - No external dependencies
     *
     * @param string $errorCode The symbolic identifier of the error
     * @return array|null The resolved configuration, or null if not found
     */
    public function getErrorConfig(string $errorCode): ?array
    {
        if (isset($this->customErrors[$errorCode])) {
            return $this->customErrors[$errorCode];
        }

        $config = $this->config['errors'][$errorCode] ?? null;

        if ($config === null) {
            $this->logger->warning('Error code configuration not found', [
                'code' => $errorCode
            ]);
        }

        return $config;
    }

    /**
     * 🎯 Primary Orchestration Entry Point
     *
     * Executes the full error handling lifecycle: config resolution, handler dispatch,
     * and response generation or exception throwing.
     *
     * 🧱 Structure:
     * - Resolves config via `resolveErrorConfig`
     * - Prepares error info with `prepareErrorInfo`
     * - Dispatches handlers via `dispatchHandlers`
     * - Builds response or throws exception
     *
     * 📡 Communicates:
     * - Logs lifecycle stages via UltraLogManager
     * - Returns response or throws UltraErrorException
     *
     * 🧪 Testable:
     * - Dependencies mockable
     * - Response separable via `buildResponse`
     *
     * @param string $errorCode Symbolic code for the error
     * @param array $context Optional context for message substitution and logging
     * @param \Throwable|null $exception Original exception to link with error
     * @param bool $throw Whether to throw an exception instead of returning a response
     * @return JsonResponse|RedirectResponse|null Response for HTTP, null for CLI
     * @throws UltraErrorException If $throw is true
     */
    public function handle(string $errorCode, array $context = [], ?\Throwable $exception = null, bool $throw = false): JsonResponse|RedirectResponse|null
    {
        $context = is_array($context) ? $context : [];
        $this->logger->info('Handling error', $context);

        $errorConfig = $this->resolveErrorConfig($errorCode, $context, $exception);
        $errorInfo = $this->prepareErrorInfo($errorCode, $errorConfig, $context, $exception);

        $this->dispatchHandlers($errorCode, $errorConfig, $context, $exception);
        $this->logger->info('Handlers dispatched', ['code' => $errorCode]);

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
     * 🎯 Resolve error configuration with fallback logic
     *
     * Determines the effective configuration for an error code, applying fallback
     * strategies if needed.
     *
     * 🧱 Structure:
     * - Checks runtime config, static config, UNDEFINED_ERROR_CODE, fallback_error
     *
     * 📡 Communicates:
     * - Logs resolution attempts and failures
     * - Updates $errorCode and $context by reference
     *
     * 🧪 Testable:
     * - Config mockable
     * - Throws exception for ultimate failure
     *
     * @param string &$errorCode Incoming error code (modified if fallback applied)
     * @param array &$context Context data (modified with original code if fallback)
     * @param \Throwable|null $exception Optional original exception
     * @return array Resolved configuration
     * @throws UltraErrorException If all fallbacks fail
     */
    protected function resolveErrorConfig(string &$errorCode, array &$context, ?\Throwable $exception = null): array
    {
        $config = $this->getErrorConfig($errorCode);
        if ($config) {
            return $config;
        }

        $this->logger->warning("Undefined error code: [{$errorCode}]. Attempting fallback.", $context);

        $context['_original_code'] = $errorCode;
        $errorCode = 'UNDEFINED_ERROR_CODE';
        $config = $this->getErrorConfig($errorCode);

        if ($config) {
            return $config;
        }

        $this->logger->critical('Missing config for UNDEFINED_ERROR_CODE. Trying fallback_error.', []);

        $fallback = $this->config['fallback_error'] ?? null;
        if (!$fallback || !is_array($fallback)) {
            $this->logger->critical('No fallback configuration available. Throwing hard.', []);
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
     * 🎯 Dispatch registered handlers for an error
     *
     * Invokes all applicable handlers based on their `shouldHandle` logic.
     *
     * 🧱 Structure:
     * - Iterates `$handlers`, filters with `shouldHandle`
     *
     * 📡 Communicates:
     * - Logs handler execution count
     *
     * 🧪 Testable:
     * - Handlers mockable
     * - No external side effects
     *
     * @param string $errorCode Resolved error code
     * @param array $errorConfig Resolved configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Optional linked exception
     * @return void
     */
    protected function dispatchHandlers(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception = null): void
    {
        $count = 0;
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($errorConfig)) {
                $count++;
                $this->logger->debug('Executing handler', [
                    'handler' => get_class($handler)
                ]);
                $handler->handle($errorCode, $errorConfig, $context, $exception);
            }
        }
        $this->logger->info("Dispatched {$count} handlers", ['code' => $errorCode]);
    }

    /**
     * 🎯 Prepare structured error information
     *
     * Assembles a comprehensive error object for response or logging.
     *
     * 🧱 Structure:
     * - Combines config, context, and exception data
     * - Uses translator for message localization
     *
     * 📡 Communicates:
     * - Provides data to `buildResponse`
     *
     * 🧪 Testable:
     * - Translator mockable
     * - Pure function, no side effects
     *
     * @param string $errorCode Resolved error code
     * @param array $errorConfig Resolved configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Optional exception
     * @return array Structured error info
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
            'timestamp' => now()->toIso8601String(),
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
     * 🎯 Format error messages with translation
     *
     * Generates localized messages using the injected translator.
     *
     * 🧱 Structure:
     * - Prioritizes translation key, then direct message, then fallback
     *
     * 📡 Communicates:
     * - Logs message source selection
     *
     * 🧪 Testable:
     * - Translator mockable
     * - Pure function
     *
     * @param array $errorConfig Error configuration
     * @param array $context Substitution context
     * @param string $directKey Direct message key
     * @param string $translationKey Translation key
     * @return string Formatted message
     */
    protected function formatMessage(array $errorConfig, array $context, string $directKey, string $translationKey): string
    {
        if (isset($errorConfig[$translationKey])) {
            $message = $this->translator->get($errorConfig[$translationKey], $context);
            $this->logger->debug('Using translated message', ['key' => $errorConfig[$translationKey]]);
        } elseif (isset($errorConfig[$directKey])) {
            $message = $errorConfig[$directKey];
            $this->logger->debug('Using direct message', ['source' => $directKey]);
            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $message = str_replace(":{$key}", (string) $value, $message);
                }
            }
        } else {
            $message = "An error has occurred";
            $this->logger->debug('No message key found, using fallback');
        }

        return $message;
    }

    /**
     * 🎯 Build HTTP response for error
     *
     * Constructs a response based on error info, suitable for HTTP contexts.
     * For now, throws an exception for blocking errors; non-blocking responses
     * require a request handler (to be injected later).
     *
     * 🧱 Structure:
     * - Handles JSON and redirect cases
     *
     * 📡 Communicates:
     * - Logs response type
     *
     * 🧪 Testable:
     * - Requires request handler injection (TODO)
     *
     * @param array $errorInfo Prepared error information
     * @return JsonResponse|RedirectResponse|null
     * @throws UltraErrorException For blocking errors until request handler is added
     */
    protected function buildResponse(array $errorInfo): JsonResponse|RedirectResponse|null
    {
        // TODO: Inject request handler to replace static calls
        if (request()->expectsJson() || request()->is('api/*')) {
            $this->logger->info('Returning JSON error response', [
                'code' => $errorInfo['error_code'],
                'status' => $errorInfo['http_status_code']
            ]);
            return new JsonResponse([
                'error' => $errorInfo['error_code'],
                'message' => $errorInfo['user_message'],
                'blocking' => $errorInfo['blocking'],
                'display_mode' => $errorInfo['display_mode']
            ], $errorInfo['http_status_code']);
        }

        if ($errorInfo['blocking'] === 'blocking') {
            $this->logger->info('Throwing exception for blocking error', [
                'code' => $errorInfo['error_code'],
                'status' => $errorInfo['http_status_code']
            ]);
            throw new UltraErrorException(
                $errorInfo['user_message'],
                $errorInfo['http_status_code'],
                null,
                $errorInfo['error_code'],
                $errorInfo['context']
            );
        }

        $this->logger->warning('Non-blocking response not fully implemented without request handler', [
            'code' => $errorInfo['error_code']
        ]);
        return null; // Temporary until request handler is injected
    }
}