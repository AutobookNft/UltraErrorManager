<?php

declare(strict_types=1);

namespace Ultra\ErrorManager;

// Core Laravel contracts & classes
use Illuminate\Contracts\Foundation\Application; // Per environment (anche se non usato qui direttamente, buona pratica averlo)
use Illuminate\Contracts\Translation\Translator as TranslatorContract; // Dependency
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request; // Dependency

// UEM specific classes & interfaces
use Ultra\ErrorManager\Exceptions\UltraErrorException;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface;

// Dependencies from other Ultra packages
use Ultra\UltraLogManager\UltraLogManager; // Dependency

// PHP Standard library
use Throwable; // Import Throwable

/**
 * 🎯 ErrorManager – Oracoded Core Error Orchestrator (Facade-Free, GDPR-Aware)
 *
 * Central error management hub for the Ultra ecosystem. Orchestrates the error
 * handling lifecycle by resolving configurations, dispatching registered handlers,
 * preparing localized error information, and generating appropriate responses
 * or exceptions. Fully relies on Dependency Injection. Includes GDPR annotations.
 *
 * 🧱 Structure:
 * - Implements ErrorManagerInterface.
 * - Injects UltraLogManager, TranslatorContract, Request, and Config array.
 * - Maintains a registry of runtime handlers (`$handlers`).
 * - Stores runtime-defined error configurations (`$customErrors`).
 * - Resolves final error configuration using fallback logic (`resolveErrorConfig`).
 * - Prepares structured error data including localized messages (`prepareErrorInfo`).
 * - Dispatches the error event to all applicable registered handlers (`dispatchHandlers`).
 * - Builds HTTP responses (JSON/Redirect/Null) or throws UltraErrorException (`buildResponse`).
 *
 * 📡 Communicates:
 * - With registered ErrorHandlerInterface implementations.
 * - With UltraLogManager (injected) for all internal logging.
 * - With TranslatorContract (injected) for message localization.
 * - Reads Request data (injected) for response building context.
 * - Reads configuration (injected) for error definitions and settings.
 *
 * 🧪 Testable:
 * - All external dependencies are injected via the constructor.
 * - No static facade calls remain internally. Fully mockable.
 * - Supports testing via exception throwing (`$throw = true`).
 *
 * 🛡️ GDPR Considerations:
 * - Logs actions via ULM (`@log`).
 * - Handles potential PII within `$context` (`@data-input` on handle).
 * - Outputs potentially sensitive info in responses/exceptions (`@data-output`).
 * - Acts as an `@error-boundary`.
 * - Errors marked `@critical` in config require special attention.
 */
final class ErrorManager implements ErrorManagerInterface
{
    /** @var array<int, ErrorHandlerInterface> */
    protected array $handlers = [];

    /** @var array<string, array<string, mixed>> */
    protected array $customErrors = [];

    // Injected Dependencies (readonly for immutability post-construction)
    protected readonly UltraLogManager $logger;
    protected readonly TranslatorContract $translator;
    protected readonly Request $request; // Injected Request
    protected readonly array $config; // Injected Config array

    /**
     * 🎯 Constructor: Initializes with injected dependencies.
     *
     * @param UltraLogManager $logger Logger instance (ULM).
     * @param TranslatorContract $translator Translator instance (UTM via contract).
     * @param Request $request The current HTTP Request instance.
     * @param array $config The merged configuration array for 'error-manager'.
     */
    public function __construct(
        UltraLogManager $logger,
        TranslatorContract $translator,
        Request $request, // Add Request injection
        array $config = []
    ) {
        $this->logger = $logger;
        $this->translator = $translator;
        $this->request = $request; // Store injected Request
        $this->config = $config; // Store injected Config

        // Log initialization using injected logger
        // $this->logger->info('UltraErrorManager Initialized (DI Mode)', ['handler_count' => count($this->handlers)]);
        // Note: Handlers are now registered by the Service Provider *after* construction.
    }

    /**
     * {@inheritdoc}
     * 🎯 @structural Register a runtime error handler.
     * 📡 Logs registration via ULM.
     * 🧪 Handler mockable, registry inspectable.
     */
    public function registerHandler(ErrorHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;
        $this->logger->debug('Registered error handler', [
            'handler_class' => get_class($handler),
            'total_handlers' => count($this->handlers)
        ]);
        return $this;
    }

    /**
     * {@inheritdoc}
     * 🎯 @structural Retrieve all registered error handlers.
     * 🧪 Pure getter.
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * {@inheritdoc}
     * 🎯 @structural Define a custom error dynamically at runtime.
     * 📡 Logs definition via ULM.
     * 🧪 Config mockable, inspectable via getErrorConfig.
     */
    public function defineError(string $errorCode, array $config): self
    {
        $this->customErrors[$errorCode] = $config;
        $this->logger->debug('Defined runtime error configuration', [
            'code' => $errorCode,
            'config_keys' => array_keys($config) // Log keys, not full config potentially
        ]);
        return $this;
    }

    /**
     * {@inheritdoc}
     * 🎯 @structural Resolve configuration for a specific error code.
     * 📡 Logs missing config via ULM. Prioritizes runtime definitions.
     * 🧪 Config mockable via constructor.
     * 📤 @data-output (Potentially, if config contains sensitive defaults)
     */
    public function getErrorConfig(string $errorCode): ?array
    {
        if (isset($this->customErrors[$errorCode])) {
            return $this->customErrors[$errorCode];
        }

        // Access injected config array
        $config = $this->config['errors'][$errorCode] ?? null;

        if ($config === null) {
            // Use injected logger
            $this->logger->notice('Static error code configuration not found', [
                'code' => $errorCode
            ]);
        }

        return $config;
    }

    /**
     * {@inheritdoc}
     * 🎯 @critical Primary Orchestration Entry Point for handling errors.
     * 📥 @data-input (Via $context)
     * 🔥 @error-boundary Core error handling logic.
     * 🪵 @log Logs lifecycle stages via ULM.
     * 📡 Dispatches to handlers, builds response or throws.
     * 📤 @data-output (Via response or exception)
     * 🧪 Dependencies mockable.
     *
     * @throws UltraErrorException If $throw is true or for fatal fallback failures.
     */
    public function handle(
        string $errorCode,
        array $context = [],
        ?Throwable $exception = null,
        bool $throw = false
    ): JsonResponse|RedirectResponse|null {
        // Ensure context is always an array
        $context = is_array($context) ? $context : [];
        $initialErrorCode = $errorCode; // Store original code for logging

        // $this->logger->info("UEM Handling error: [{$initialErrorCode}]", [
        //     'initial_context_keys' => array_keys($context), // Avoid logging full context here
        //     'has_exception' => !is_null($exception),
        //     'throw_mode' => $throw
        // ]);

        // Resolve config, potentially modifying $errorCode and $context via fallback
        $errorConfig = $this->resolveErrorConfig($errorCode, $context, $exception);

        // Prepare detailed info object using resolved config/code
        $errorInfo = $this->prepareErrorInfo($errorCode, $errorConfig, $context, $exception);

        // Dispatch to registered handlers
        $this->dispatchHandlers($errorCode, $errorConfig, $context, $exception);
        $this->logger->debug("UEM Handlers dispatched", ['resolved_code' => $errorCode]);

        // Decide whether to throw or return a response
        if ($throw) {
            $this->logger->warning("UEM Throwing UltraErrorException as requested.", [
                 'resolved_code' => $errorCode,
                 'http_status' => $errorInfo['http_status_code']
            ]);
            // Pass resolved code and context to exception
            throw new UltraErrorException(
                $errorInfo['user_message'] ?? "Error: {$errorCode}", // Use prepared user message
                $errorInfo['http_status_code'],
                $exception, // Original exception
                $errorCode, // Resolved error code
                $context // Context (potentially modified by fallback)
            );
        }

        // Build and return response if not throwing
        return $this->buildResponse($errorInfo);
    }

    /**
     * 🧱 Resolve error configuration with multiple fallback levels.
     * Modifies $errorCode and $context by reference if fallbacks are used.
     * Throws UltraErrorException if NO configuration can be found (fatal).
     *
     * 🪵 @log Logs fallback attempts and failures via ULM.
     * 🧪 Config mockable.
     *
     * @param string &$errorCode Incoming error code (modified by reference).
     * @param array &$context Context data (modified by reference).
     * @param Throwable|null $exception Optional original exception.
     * @return array Resolved configuration array.
     * @throws UltraErrorException If 'FATAL_FALLBACK_FAILURE' occurs.
     */
    protected function resolveErrorConfig(string &$errorCode, array &$context, ?Throwable $exception = null): array
    {
        // 1. Try direct code (runtime or static)
        $config = $this->getErrorConfig($errorCode);
        if ($config) {
            return $config;
        }
        $originalCode = $errorCode; // Keep track for context/logging
        $this->logger->warning("UEM Undefined error code: [{$originalCode}]. Attempting UNDEFINED_ERROR_CODE fallback.", $context);

        // 2. Try 'UNDEFINED_ERROR_CODE'
        $context['_original_code'] = $originalCode; // Add original code to context
        $errorCode = 'UNDEFINED_ERROR_CODE'; // Modify errorCode by reference
        $config = $this->getErrorConfig($errorCode);
        if ($config) {
            return $config;
        }
        $this->logger->error("UEM Missing config for UNDEFINED_ERROR_CODE. Trying 'fallback_error'.", ['original_code' => $originalCode]);

        // 3. Try 'fallback_error' from main config
        // Access injected config
        $fallbackConfig = $this->config['fallback_error'] ?? null;
        if ($fallbackConfig && is_array($fallbackConfig)) {
             $errorCode = 'FALLBACK_ERROR'; // Modify errorCode by reference
            return $fallbackConfig;
        }
        $this->logger->critical("UEM No 'fallback_error' configuration available. This is fatal.", ['original_code' => $originalCode]);

        // 4. Fatal: No configuration found anywhere
        // We MUST throw here because we cannot determine how to handle the error.
        throw new UltraErrorException(
            "FATAL: No error configuration found for [{$originalCode}] or any fallback.",
            500, // Internal Server Error
            $exception, // Pass original exception if any
            'FATAL_FALLBACK_FAILURE', // Specific code for this catastrophic failure
            $context // Pass context including _original_code
        );
    }

    /**
     * 🧱 Dispatch the error event to all applicable registered handlers.
     *
     * 🪵 @log Logs count of dispatched handlers via ULM.
     * 🧪 Handlers mockable.
     *
     * @param string $errorCode Resolved error code.
     * @param array $errorConfig Resolved configuration.
     * @param array $context Contextual data.
     * @param Throwable|null $exception Optional linked exception.
     * @return void
     */
    protected function dispatchHandlers(string $errorCode, array $errorConfig, array $context, ?Throwable $exception = null): void
    {
        $dispatchedCount = 0;
        foreach ($this->handlers as $handler) {
            // Check if handler should process this error type/config
            if ($handler->shouldHandle($errorConfig)) {
                $dispatchedCount++;
                $handlerClass = get_class($handler);
                // $this->logger->debug("UEM Dispatching handler: {$handlerClass}", ['errorCode' => $errorCode]);
                try {
                    // Execute the handler
                    $handler->handle($errorCode, $errorConfig, $context, $exception);
                } catch (Throwable $handlerException) {
                    // Log if a handler itself throws an exception
                    $this->logger->error("UEM Exception occurred within handler: {$handlerClass}", [
                        'errorCode' => $errorCode,
                        'handler_exception_message' => $handlerException->getMessage(),
                        'handler_exception_trace' => $handlerException->getTraceAsString(), // Be careful in production
                    ]);
                    // Avoid re-throwing to prevent error loops, but log it critically.
                }
            }
        }
        $this->logger->info("UEM Dispatched {$dispatchedCount} handlers for [{$errorCode}].", ['total_registered' => count($this->handlers)]);
    }

    /**
     * 🧱 Prepare a structured array containing comprehensive error information.
     * Includes localized messages using the injected translator.
     *
     * 📤 @data-output (Contains potentially sensitive info before response sanitization)
     * 🧪 Translator mockable. Pure function.
     *
     * @param string $errorCode Resolved error code.
     * @param array $errorConfig Resolved configuration.
     * @param array $context Contextual data.
     * @param Throwable|null $exception Optional exception.
     * @return array Structured error information.
     */
    protected function prepareErrorInfo(string $errorCode, array $errorConfig, array $context, ?Throwable $exception = null): array
    {
        $errorInfo = [
            'error_code'       => $errorCode,
            'type'             => $errorConfig['type'] ?? 'error',
            'blocking'         => $errorConfig['blocking'] ?? 'blocking',
            // Get localized messages using the injected translator via formatMessage helper
            'message'          => $this->formatMessage($errorConfig, $context, 'dev_message', 'dev_message_key', "Dev message missing for {$errorCode}"),
            'user_message'     => $this->formatMessage($errorConfig, $context, 'user_message', 'user_message_key', __("error-manager::errors.user.fallback_error")), // Use generic fallback key
            'http_status_code' => $errorConfig['http_status_code'] ?? 500,
            'context'          => $context, // Pass raw context; sanitization happens at output boundaries (DB, Email, Slack handlers)
            'display_mode'     => $errorConfig['msg_to'] ?? ($this->config['ui']['default_display_mode'] ?? 'div'),
            'timestamp'        => now()->toIso8601String(),
        ];

        // Add structured exception info if present
        if ($exception) {
            $errorInfo['exception'] = [
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                // Trace is not included here by default; handlers decide based on their config
            ];
        }

        return $errorInfo;
    }

    /**
     * 🧱 Format error messages using Translation keys or direct strings.
     * Uses the injected TranslatorContract. Provides a fallback message.
     *
     * 📤 @data-output (Returns processed message strings)
     * 🧪 Translator mockable. Pure function.
     *
     * @param array $errorConfig Error configuration.
     * @param array $context Substitution context.
     * @param string $directKey Key for the direct message in config (e.g., 'dev_message').
     * @param string $translationKey Key for the translation key in config (e.g., 'dev_message_key').
     * @param string $fallbackMessage Message to use if no other source is found.
     * @return string Formatted and potentially localized message.
     */
    protected function formatMessage(array $errorConfig, array $context, string $directKey, string $translationKey, string $fallbackMessage = "An error occurred."): string
    {
        $message = $fallbackMessage; // Start with fallback

        // Prioritize translation key
        if (!empty($errorConfig[$translationKey])) {
            $translation = $this->translator->get($errorConfig[$translationKey], $context);
            // Check if translation returned the key itself (meaning not found)
            if ($translation !== $errorConfig[$translationKey]) {
                $message = $translation;
                $this->logger->debug('UEM Using translated message', ['key' => $errorConfig[$translationKey]]);
            } else {
                 $this->logger->warning('UEM Translation key not found, trying direct message.', ['key' => $errorConfig[$translationKey]]);
                 // Fall through to check direct key if translation failed
                 if (!empty($errorConfig[$directKey])) {
                     $message = $errorConfig[$directKey];
                     $this->logger->debug('UEM Using direct message as fallback from failed translation.', ['source' => $directKey]);
                 } else {
                      $this->logger->warning('UEM Direct message key also not found, using fallback.', ['key' => $directKey, 'fallback' => $fallbackMessage]);
                 }
            }
        }
        // Else, use direct message if available
        elseif (!empty($errorConfig[$directKey])) {
            $message = $errorConfig[$directKey];
            $this->logger->debug('UEM Using direct message', ['source' => $directKey]);
        }
        // Else, fallback is already set

        // Perform placeholder replacement on the final message string
        // (Handles cases where direct message has placeholders or translation failed)
        foreach ($context as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $message = str_replace(":{$key}", (string) $value, $message);
            }
        }

        return $message;
    }

    /**
     * 🧱 Build HTTP response based on error info and request context.
     * Uses the injected Request object. Throws exception for blocking HTML requests.
     *
     * 📤 @data-output (Returns HTTP Response or null)
     * 📡 Logs response type via ULM.
     * 🧪 Requires Request to be mocked for testing different contexts.
     *
     * @param array $errorInfo Prepared error information from `prepareErrorInfo`.
     * @return JsonResponse|RedirectResponse|null Response object or null for non-blocking HTML.
     * @throws UltraErrorException For blocking errors in non-JSON/API contexts.
     */
    protected function buildResponse(array $errorInfo): JsonResponse|RedirectResponse|null
    {
        // Use injected Request object
        if ($this->request->expectsJson() || $this->request->is('api/*')) {
            // $this->logger->info('UEM Returning JSON error response', [
            //     'code' => $errorInfo['error_code'],
            //     'status' => $errorInfo['http_status_code']
            // ]);
            // Return only safe fields in JSON response
            return new JsonResponse([
                'error'        => $errorInfo['error_code'],
                'message'      => $errorInfo['user_message'], // Use prepared user message
                'blocking'     => $errorInfo['blocking'],
                'display_mode' => $errorInfo['display_mode'],
                 // Avoid sending full context or exception details in JSON response by default
                 // 'details' => $errorInfo['context'] // Add only if explicitly needed and safe
            ], $errorInfo['http_status_code']);
        }

        // For non-JSON requests (e.g., standard web requests)
        if ($errorInfo['blocking'] === 'blocking') {
             // For blocking errors in HTML context, re-throwing the exception is often
             // the desired behavior, allowing Laravel's main exception handler
             // (which might render a specific error page) to take over.
            $this->logger->warning('UEM Throwing exception for blocking error in HTML context.', [
                'code' => $errorInfo['error_code'],
                'status' => $errorInfo['http_status_code']
            ]);
            throw new UltraErrorException(
                $errorInfo['user_message'],
                $errorInfo['http_status_code'],
                null, // Original exception already passed if $throw=true in handle()
                $errorInfo['error_code'],
                $errorInfo['context']
            );
        }

        // For non-blocking errors in HTML context:
        // Assume UserInterfaceHandler has flashed data to the session.
        // Return null, allowing the request to continue. The view/middleware
        // responsible for reading session flash data will display the error.
        // $this->logger->info('UEM Handling non-blocking error for HTML request (flashed to session).', [
        //     'code' => $errorInfo['error_code']
        // ]);
        return null;
    }
}