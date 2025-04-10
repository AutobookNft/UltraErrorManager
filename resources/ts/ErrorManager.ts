// /home/fabio/libraries/UltraErrorManager/resources/ts/ErrorManager.ts
// Oracode/GDPR Refactoring by Padmin D. Curtis

import {
    ErrorHandler,
    ErrorConfig,
    ErrorContext,
    ServerErrorResponse,
    UltraErrorEvent, // Import specific event type
    UltraErrorEventDetail, // Import specific detail type
    DisplayMode, // Use Enums for clarity
    BlockingLevel, // Use Enums for clarity

} from './interfaces/ErrorTypes'; // Assuming ErrorTypes.ts is correctly structured
import { ErrorDisplayHandler } from './handlers/ErrorDisplayHandler';
import { errorConfig } from './utils/ErrorConfigLoader'; // Dependency for config loading


// --- Oracode Refined Class Doc ---
/**
 * 🎯 Purpose: Central orchestrator for client-side error management in the UEM ecosystem.
 *    Provides a singleton instance to handle errors originating from server responses
 *    or client-side operations, applying configurations, dispatching to registered
 *    handlers (like display handlers), and notifying the application via custom events.
 *
 * 🧱 Structure:
 *    - Implements the Singleton pattern (`getInstance`).
 *    - Maintains a registry of `ErrorHandler` implementations (`handlers`).
 *    - Requires initialization (`initialize`) to load configurations via `ErrorConfigLoader`.
 *    - Provides `handleServerError` and `handleClientError` as main entry points.
 *    - Uses helper methods for message formatting (`formatMessage`, `getUserMessage`)
 *      and event dispatching (`dispatchErrorEvent`).
 *    - Includes special handling logic for critical/blocking errors (`handleCriticalBlockingError`).
 *    - Relies heavily on types defined in `ErrorTypes.ts`.
 *
 * 📡 Communicates:
 *    - Reads error configurations via `errorConfig` instance (`ErrorConfigLoader`).
 *    - Reads global `window.translations` for localization in `getUserMessage`.
 *    - Dispatches error details to registered `ErrorHandler` instances (`handlers`).
 *    - Dispatches `ultraError` CustomEvent on the `document` for application-wide listeners.
 *    - Logs warnings/errors to the browser `console`.
 *
 * 🧪 Testable:
 *    - Singleton pattern makes direct instantiation tricky for isolated tests; testing often involves the exported `ultraError` instance.
 *    - Key dependency: `errorConfig` (mockable).
 *    - Key dependency: Global `window.translations` (needs mocking/setup in test env).
 *    - Key dependency: Global `document.dispatchEvent` (mockable/spyable).
 *    - Key dependency: Browser `console` (spyable).
 *    - `handlers` array can be manipulated for testing specific handler interactions.
 *    - Async `initialize` method needs async testing patterns.
 *
 * 🛡️ GDPR Considerations:
 *    - Handles potentially sensitive `context` data (`@data-input` on handle methods).
 *    - Retrieves user messages (`@data-output` from `getUserMessage`) which *should* be pre-sanitized/localized server-side or via safe translation keys. Responsibility lies with the message source.
 *    - Dispatches `UltraErrorEventDetail` (`@data-output`) containing context and potentially simplified original error details. Event listeners must handle this data responsibly. Stack traces are *not* included in the event by default.
 *    - Logs context and potentially original error messages to the console (`@log`), acceptable for development but should be reviewed for production builds/transports.
 *
 * @module UltraErrorManager/Core
 * @version 1.0.1 // Version bumped post-refactor
 */
export class ErrorManager {
    /** @private The singleton instance. */
    private static instance: ErrorManager;

    /** @private Registry of active error handlers. */
    private handlers: ErrorHandler[] = [];

    /** @private Flag indicating if initialization (config loading) is complete. */
    private initialized: boolean = false;

    /** @private Default display mode if not specified in error config or server response. */
    private defaultDisplayMode: DisplayMode = DisplayMode.DIV; // Use Enum
  
    // --- INJECTED DEPENDENCIES ---
    // Non abbiamo ULM o Translator qui, ma abbiamo la config

    /**
     * 🧱 @dependency The merged configuration object for 'error-manager'.
     * Injected via the constructor (simulated DI in TS). Used for default settings
     * like the generic error message key.
     * @protected
     * @readonly
     */
    protected readonly config: Record<string, any>; 
        
    /**
     * 🎯 Private constructor: Enforces singleton pattern. Registers the default display handler.
     * @private
     */
    private constructor() {
        // Register the default display handler automatically
        this.registerHandler(new ErrorDisplayHandler());
        // Note: ULM logger isn't available client-side like in PHP. Using console for lifecycle logs.
        console.info('[UEM] ErrorManager Singleton Instantiated');
    }

    /**
     * 🎯 Get the singleton instance of ErrorManager.
     * 🧱 Creates a new instance if one doesn't exist yet (Singleton pattern).
     * @public
     * @static
     * @returns {ErrorManager} The singleton instance.
     */
    public static getInstance(): ErrorManager {
        if (!ErrorManager.instance) {
            ErrorManager.instance = new ErrorManager();
        }
        return ErrorManager.instance;
    }

    /**
     * 🎯 Initialize the error manager, primarily by loading configurations asynchronously.
     * 🧱 Sets the initialization flag and default display mode.
     * 📡 Communicates with `ErrorConfigLoader` to fetch configurations.
     * 🪵 Logs warnings/errors related to initialization via `console`.
     * 🔄 @mutation Sets `initialized` flag and potentially `defaultDisplayMode`.
     * @public
     * @async
     * @param {object} [options={}] Initialization options.
     * @param {boolean} [options.loadConfig=true] Whether to load error configurations via `ErrorConfigLoader`.
     * @param {DisplayMode} [options.defaultDisplayMode] Override the default display mode.
     * @returns {Promise<void>} A promise that resolves when initialization is complete.
     * @testability Depends on mocking `errorConfig.loadConfig()`. Needs async test.
     */
    public async initialize(options: {
        loadConfig?: boolean,
        defaultDisplayMode?: DisplayMode // Use Enum
    } = {}): Promise<void> {
        if (this.initialized) {
            console.warn('[UEM] ErrorManager already initialized.');
            return Promise.resolve();
        }

        console.info('[UEM] Initializing ErrorManager...');

        // Set default display mode if provided
        if (options.defaultDisplayMode && Object.values(DisplayMode).includes(options.defaultDisplayMode)) {
            this.defaultDisplayMode = options.defaultDisplayMode;
            console.info(`[UEM] Default display mode set to: ${this.defaultDisplayMode}`);
        }

        // Load configurations if needed (default is true)
        if (options.loadConfig !== false) {
            try {
                console.info('[UEM] Loading error configurations...');
                await errorConfig.loadConfig(); // Use the singleton loader instance
                console.info('[UEM] Error configurations loaded successfully.');
            } catch (error) {
                // Log error but continue initialization with fallback logic
                console.error('[UEM] Failed to load error configurations:', error);
            }
        } else {
             console.info('[UEM] Skipping configuration loading as per options.');
        }

        this.initialized = true;
        console.info('[UEM] ErrorManager initialized successfully.');
        return Promise.resolve();
    }

    /**
     * 🎯 Register a new error handler to the processing pipeline.
     * 🧱 Adds the handler instance to the internal `handlers` array.
     * 🔄 @mutation Modifies the internal `handlers` array.
     * @public
     * @param {ErrorHandler} handler - The handler instance to register.
     * @returns {ErrorManager} The ErrorManager instance for chaining.
     */
    public registerHandler(handler: ErrorHandler): ErrorManager {
        // Optional: Check if handler already exists?
        if (!this.handlers.includes(handler)) {
            this.handlers.push(handler);
            console.info(`[UEM] Registered error handler: ${handler.constructor.name}`);
        }
        return this;
    }

    /**
     * 🎯 Handle an error response received from the server API.
     * 🚨 Orchestrates the handling flow: config lookup, message formatting, handler dispatch, event dispatch, critical handling.
     * 🧠 Determines display mode and blocking level based on response and config fallbacks.
     * 📡 Dispatches to registered handlers and triggers `ultraError` event.
     * 🪵 Logs warnings/errors via `console`.
     * 📥 @data-input `response` object (especially `message`, `details`), `context` object. Potentially sensitive.
     * 📤 @data-output Passes details to handlers and `ultraError` event listeners.
     * @public
     * @param {ServerErrorResponse} response - The parsed error response from the server.
     * @param {ErrorContext} [context={}] - Additional client-side context for the error.
     * @testability Depends on `errorConfig`, `handlers`, `document.dispatchEvent`, `console`.
     */
    public handleServerError(response: ServerErrorResponse, context: ErrorContext = {}): void {
        if (!this.initialized) {
            // Attempt initialization on the fly if not done yet.
            console.warn('[UEM] ErrorManager not initialized in handleServerError, initializing now...');
            this.initialize().then(() => {
                this.handleServerError(response, context); // Retry after initialization
            }).catch(initError => {
                 console.error('[UEM] Failed to auto-initialize ErrorManager during handleServerError:', initError);
                 // Basic fallback logging if init fails
                 console.error(`[UEM Fallback] Server Error Code: ${response.error}`, response, context);
            });
            return;
        }

        console.info(`[UEM] Handling server error: ${response.error}`);

        // Get additional configuration from the loader
        const config = errorConfig.getErrorConfig(response.error);

        // Determine final values, prioritizing server response, then config, then defaults
        const finalDisplayMode = response.display_mode || config?.msg_to || this.defaultDisplayMode;
        const finalBlocking = response.blocking || config?.blocking || BlockingLevel.NOT_BLOCKING; // Default to non-blocking
        const finalType = config?.type || 'error'; // Default type if config is missing

        // Format the user message provided by the server (assumed safe and localized)
        const userMessage = this.formatMessage(response.message || `Server error occurred (${response.error})`, context);

        // Process through each registered handler
        let handled = false;
        for (const handler of this.handlers) {
            try {
                 // Pass the *resolved* config to shouldHandle
                if (handler.shouldHandle(response.error, config)) {
                    handler.handle(
                        response.error,
                        userMessage, // Pass the prepared user message
                        finalDisplayMode,
                        finalBlocking,
                        context,
                        // No originalError for server responses typically
                    );
                    handled = true; // Mark if at least one handler processed it
                }
            } catch(handlerError) {
                console.error(`[UEM] Error in handler ${handler.constructor.name} while processing server error ${response.error}:`, handlerError);
            }
        }
        if (!handled) {
            console.warn(`[UEM] Server error ${response.error} was not processed by any registered handlers.`);
        }

        // Dispatch an application-wide event
        this.dispatchErrorEvent(response.error, userMessage, finalBlocking, context);

        // Special handling for critical blocking errors
        if (finalBlocking === BlockingLevel.BLOCKING && finalType === 'critical') {
            this.handleCriticalBlockingError(response.error, userMessage, context);
        }
    }

    /**
     * 🎯 Handle an error originating from the client-side code.
     * 🚨 Orchestrates the handling flow: config lookup, message retrieval/formatting, handler dispatch, event dispatch, critical handling.
     * 🧠 Determines display mode and blocking level primarily from config. Includes fallback for undefined codes.
     * 📡 Dispatches to registered handlers and triggers `ultraError` event. Reads `window.translations`.
     * 🪵 Logs warnings/errors via `console`.
     * 📥 @data-input `errorCode`, `context` object, `originalError` object. Potentially sensitive.
     * 📤 @data-output Passes details to handlers and `ultraError` event listeners.
     * @public
     * @param {string} errorCode - The symbolic UEM error code.
     * @param {ErrorContext} [context={}] - Additional context for the error.
     * @param {Error} [originalError] - The original JavaScript Error object, if available.
     * @testability Depends on `errorConfig`, `handlers`, `window.translations`, `document.dispatchEvent`, `console`.
     */
    public handleClientError(errorCode: string, context: ErrorContext = {}, originalError?: Error): void {
        if (!this.initialized) {
            // Attempt initialization on the fly
             console.warn('[UEM] ErrorManager not initialized in handleClientError, initializing now...');
             this.initialize().then(() => {
                 this.handleClientError(errorCode, context, originalError); // Retry after initialization
             }).catch(initError => {
                  console.error('[UEM] Failed to auto-initialize ErrorManager during handleClientError:', initError);
                  // Basic fallback logging if init fails
                  console.error(`[UEM Fallback] Client Error Code: ${errorCode}`, context, originalError);
             });
            return;
        }

         console.info(`[UEM] Handling client error: ${errorCode}`);

        // Get error configuration
        const config = errorConfig.getErrorConfig(errorCode);

        // Handle undefined error codes gracefully
        if (!config) {
            console.error(`[UEM] Error configuration not found for code: ${errorCode}. Falling back to UNEXPECTED_ERROR.`, { context, originalError });
            // Trigger UNEXPECTED_ERROR, passing original details in context
            this.handleClientError('UNEXPECTED_ERROR', {
                ...context, // Preserve original context
                _originalCode: errorCode, // Add original code for traceability
                _originalErrorMsg: originalError?.message,
            }, originalError); // Pass the originalError object along
            return;
        }

        // Determine final values from config or defaults
        const finalDisplayMode = config.msg_to || this.defaultDisplayMode;
        const finalBlocking = config.blocking || BlockingLevel.NOT_BLOCKING;
        const finalType = config.type || 'error';

        // Get and format the user message using translation logic
        const userMessage = this.getUserMessage(errorCode, config, context);

        // Process through each registered handler
         let handled = false;
         for (const handler of this.handlers) {
             try {
                if (handler.shouldHandle(errorCode, config)) {
                    handler.handle(
                        errorCode,
                        userMessage,
                        finalDisplayMode,
                        finalBlocking,
                        context,
                        originalError // Pass originalError to handlers
                    );
                    handled = true;
                }
             } catch(handlerError) {
                 console.error(`[UEM] Error in handler ${handler.constructor.name} while processing client error ${errorCode}:`, handlerError);
             }
         }
          if (!handled) {
             console.warn(`[UEM] Client error ${errorCode} was not processed by any registered handlers.`);
         }

        // Dispatch an application-wide event
        this.dispatchErrorEvent(errorCode, userMessage, finalBlocking, context, originalError);

        // Special handling for critical blocking errors
        if (finalBlocking === BlockingLevel.BLOCKING && finalType === 'critical') {
            this.handleCriticalBlockingError(errorCode, userMessage, context, originalError);
        }
    }

    /**
     * 🎯 Retrieves and formats the user-facing message for a client-side error.
     * 🕵️‍♀️ Logic: Checks `window.translations.js[errorCode]`, then `config.user_message_key` (looking up in `window.translations`),
     *    then `config.user_message`, then `config.dev_message`, finally a generic fallback from config or hardcoded.
     * 📡 Reads global `window.translations`. Reads `this.config` for generic fallback key.
     * 🧷 Provides multiple fallback levels for messages.
     * 📤 @data-output Returns the prepared, potentially localized, user-facing message string. Should be PII-safe.
     * @private
     * @param {string} errorCode - The error code.
     * @param {ErrorConfig} config - The resolved error configuration.
     * @param {ErrorContext} context - Context data for placeholder replacement.
     * @returns {string} The formatted user message (guaranteed to be a string).
     * @testability Requires mocking `window.translations` and `this.config`.
     */
    private getUserMessage(errorCode: string, config: ErrorConfig, context: ErrorContext): string {
        let message: string | null = null; // Initialize as potentially null

        // 1. Highest Priority: Direct translation key in window.translations.js
        const directJsTranslation = window?.translations?.js?.[errorCode];
        if (typeof directJsTranslation === 'string' && directJsTranslation !== '') {
            console.debug(`[UEM] Using direct JS translation for ${errorCode}`);
            message = directJsTranslation;
        }

        // 2. Next Priority: Use user_message_key from config to look up in window.translations
        if (message === null && config.user_message_key) {
            const keyParts = config.user_message_key.split('.');
            let translationSource: any = window?.translations;
            let found = true;
            for (const part of keyParts) {
                if (translationSource && typeof translationSource === 'object' && translationSource !== null && part in translationSource) {
                    translationSource = translationSource[part];
                } else {
                    found = false;
                    break;
                }
            }
            if (found && typeof translationSource === 'string' && translationSource !== '') {
                 console.debug(`[UEM] Using translation key '${config.user_message_key}' for ${errorCode}`);
                message = translationSource;
            } else {
                console.warn(`[UEM] Translation key '${config.user_message_key}' for ${errorCode} not found in window.translations.`);
            }
        }

        // 3. Fallback: Direct user_message from config
        if (message === null && config.user_message) {
             console.debug(`[UEM] Using direct user_message config for ${errorCode}`);
            message = config.user_message;
        }

        // 4. Fallback: Direct dev_message from config (use as last resort for user)
        if (message === null && config.dev_message) {
            console.warn(`[UEM] Falling back to dev_message for user message: ${errorCode}`);
            message = config.dev_message;
        }

        // 5. Ultimate Fallback: Generic message (guarantees a string)
        if (message === null) {
             console.error(`[UEM] No specific user message found for ${errorCode}. Using generic fallback.`);
             let genericMessage: string;
             try {
                 // Access generic key via the main config object CORREZIONE 1: Usa this.config['ui']
                 const genericKey = this.config?.['ui']?.['generic_error_message'] ?? 'error-manager::errors.user.generic_error';
                 // Attempt translation
                 const genericTranslation = window?.translations?.js?.[genericKey];

                 if (typeof genericTranslation === 'string' && genericTranslation !== '') {
                     genericMessage = genericTranslation;
                 } else {
                     // Hardcoded fallback if translation key itself is missing
                     genericMessage = `An unexpected error occurred. Please contact support. [Ref: ${errorCode}]`;
                     console.warn(`[UEM] Generic translation key '${genericKey}' not found. Using hardcoded fallback.`);
                 }
             } catch (e) {
                 // Catch errors accessing config or window.translations during fallback
                  genericMessage = `An unexpected error occurred. Please contact support. [Ref: ${errorCode}]`;
                  console.error('[UEM] Error retrieving generic fallback message:', e);
             }
              message = genericMessage; // Assign the guaranteed string
        }

        // CORREZIONE 2: message is now guaranteed to be a string here
        // Format the chosen message (which is now definitely a string) with context placeholders
        return this.formatMessage(message, context);
    }

    /**
     * 🎯 Replaces placeholders (like `:key`) in a message string with values from the context object.
     * 🧼 @sanitizer Performs basic string replacement. Input message assumed to be safe.
     * 📤 @data-output Returns the message with placeholders filled.
     * @private
     * @param {string} message - The message template with placeholders.
     * @param {ErrorContext} context - Data source for placeholder values.
     * @returns {string} The formatted message.
     * @testability Pure function, easily testable.
     */
    private formatMessage(message: string, context: ErrorContext): string {
        let formattedMessage = message;

        // Iterate through context keys
        for (const key in context) {
            // Ensure the key is directly on the context object and the value is suitable for replacement
            if (Object.prototype.hasOwnProperty.call(context, key)) {
                 const value = context[key];
                 // Replace only if value is string or number (or boolean maybe?)
                 if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
                     // Use RegExp for global replacement of the placeholder :key
                     const placeholder = `:${key}`;
                     // Use 'g' flag for global replacement
                     const regex = new RegExp(placeholder.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g');
                     formattedMessage = formattedMessage.replace(regex, String(value));
                 }
            }
        }

        // Optional: Remove any remaining placeholders that weren't in the context?
        // formattedMessage = formattedMessage.replace(/:\w+/g, ''); // Example: removes any leftover :word

        return formattedMessage;
    }

    /**
     * 🎯 Handles critical, blocking errors with specific logging and potentially UI-blocking actions.
     * 🔥 Marks the error as critical.
     * 🚨 Central point for defining application-level critical failure behavior.
     * 🪵 Logs detailed info via `console.error`.
     * @private
     * @param {string} errorCode - The error code.
     * @param {string} message - The prepared user-facing message.
     * @param {ErrorContext} context - Error context.
     * @param {Error} [originalError] - The original JS error, if available.
     */
    private handleCriticalBlockingError(
        errorCode: string,
        message: string, // User message
        context: ErrorContext,
        originalError?: Error
    ): void {
        // Log detailed information for critical errors using console.error
        console.error(`[UEM] CRITICAL BLOCKING ERROR Encountered: ${errorCode}`, {
            message, // User message shown/prepared
            context,
            originalError: originalError ? {
                name: originalError.name,
                message: originalError.message,
                // Avoid logging full stack to console by default, keep it in handlers if needed
                // stack: originalError.stack?.split('\n').slice(0, 10).join('\n') // Example snippet
            } : undefined,
            timestamp: new Date().toISOString()
        });

        // --- Production Behavior Considerations ---
        // This section depends heavily on application requirements.
        // Examples:
        // 1. Display a Full-Page Overlay:
        //    const overlay = document.getElementById('critical-error-overlay');
        //    if (overlay) {
        //        overlay.querySelector('.error-message').textContent = message; // Show safe message
        //        overlay.style.display = 'block';
        //    }
        // 2. Disable Further Interaction:
        //    document.body.style.pointerEvents = 'none'; // Simple example
        // 3. Attempt Safe Reload/Redirect:
        //    setTimeout(() => { window.location.href = '/error-page?code=' + errorCode; }, 5000);
        // 4. Send Telemetry (if not already done by a handler):
        //    // Send critical error beacon to dedicated endpoint
        //    navigator.sendBeacon?.('/api/telemetry/critical-error', JSON.stringify({ errorCode, context })); // Example
    }

    /**
     * 🎯 Dispatches a standardized `ultraError` CustomEvent on the document.
     * 📡 Broadcasts error details for consumption by other application modules/listeners.
     * 📤 @data-output Event `detail` payload (`UltraErrorEventDetail`). Contains context, should be handled carefully by listeners.
     * @private
     * @param {string} errorCode - The error code.
     * @param {string} message - The prepared user-facing message.
     * @param {string} blocking - The resolved blocking level (use Enum ideally).
     * @param {ErrorContext} context - Additional context data.
     * @param {Error} [originalError] - The original JS error, if available.
     */
    private dispatchErrorEvent(
        errorCode: string,
        message: string,
        blocking: string, // Ideally BlockingLevel enum value
        context: ErrorContext,
        originalError?: Error
    ): void {
        // Avoid dispatching if running in a non-browser environment (e.g., SSR, tests without DOM)
        if (typeof document === 'undefined' || typeof CustomEvent === 'undefined') {
            console.info('[UEM] Skipping dispatchErrorEvent in non-browser environment.');
            return;
        }

        try {
            // Construct the detail payload adhering to UltraErrorEventDetail interface
            const detail: UltraErrorEventDetail = {
                errorCode,
                message, // User-facing message
                blocking, // Resolved blocking level
                context, // Include context (listeners must be careful)
                originalError: originalError ? { // Simplify originalError for the event
                    name: originalError.name,
                    message: originalError.message,
                    // Exclude stack trace from event detail payload by default for security/privacy
                    // stack: originalError.stack // Uncomment carefully if needed client-side
                } : undefined,
                timestamp: new Date().toISOString()
            };

            // Create the custom event
            // Ensure the type alias UltraErrorEvent is used if defined, otherwise CustomEvent<UltraErrorEventDetail>
            const errorEvent: UltraErrorEvent = new CustomEvent('ultraError', {
                detail: detail,
                bubbles: true, // Allow event to bubble up the DOM
                cancelable: true // Allow listeners to potentially call preventDefault (though likely not standard use)
            });

            // Dispatch the event on the document
            document.dispatchEvent(errorEvent);
            console.debug(`[UEM] Dispatched 'ultraError' event for ${errorCode}`);

        } catch (e) {
            // Catch potential errors during event creation/dispatch (less likely but possible)
            console.warn('[UEM] Failed to dispatch ultraError event:', e);
        }
    }
 } // End of ErrorManager class

 // --- Export Singleton Instance ---
 /**
 * 🎯 Exported singleton instance of the ErrorManager.
 * Provides a convenient way to access the manager throughout the application.
 * Usage: `import { ultraError } from './ErrorManager'; ultraError.handleClientError(...)`
 */
 export const ultraError = ErrorManager.getInstance();