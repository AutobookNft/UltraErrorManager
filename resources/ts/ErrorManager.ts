/**
* /home/fabio/sandbox/UltraUploadSandbox/packages/ultra/errormanager/resources/ts/ErrorManager.ts
*
* Ultra Error Manager - Core Manager Class
*
* This is the main class for handling errors in the client-side application,
* working as a counterpart to the server-side UEM (Ultra Error Manager).
* It implements a centralized error management system with configurable handlers,
* error types, and display modes.
*
* The class follows enterprise patterns including:
* - Singleton pattern for centralized access
* - Handler pattern for extensible error processing
* - Strategy pattern for different error display modes
* - Observer pattern for error notifications
*
* Security features:
* - Safe error handling with no leakage of sensitive information
* - Proper escaping of displayed messages
* - Validation of error configurations
* - Secure communication with server
*
* @module UltraErrorManager
* @version 1.0.0
*/

import {
    ErrorHandler,
    ErrorConfig,
    ErrorContext,
    ServerErrorResponse
 } from './interfaces/ErrorTypes';
 import { ErrorDisplayHandler } from './handlers/ErrorDisplayHandler';
 import { errorConfig } from './utils/ErrorConfigLoader';

 /**
 * Main ErrorManager class
 */
 export class ErrorManager {
    private static instance: ErrorManager;
    private handlers: ErrorHandler[] = [];
    private initialized: boolean = false;
    private defaultDisplayMode: string = 'div';

    /**
     * Private constructor to enforce singleton pattern
     * Cannot be instantiated directly from outside
     */
    private constructor() {
        // Register the default display handler
        this.registerHandler(new ErrorDisplayHandler());
    }

    /**
     * Get the singleton instance of ErrorManager
     * Creates a new instance if one doesn't exist yet
     *
     * @returns {ErrorManager} The singleton instance
     */
    public static getInstance(): ErrorManager {
        if (!ErrorManager.instance) {
            ErrorManager.instance = new ErrorManager();
        }
        return ErrorManager.instance;
    }

    /**
     * Initialize the error manager by loading necessary configurations
     * This should be called early in the application lifecycle
     *
     * @param {Object} options - Initialization options
     * @param {boolean} options.loadConfig - Whether to load error configurations
     * @param {string} options.defaultDisplayMode - Default display mode for errors
     * @returns {Promise<void>} A promise that resolves when initialization is complete
     */
    public async initialize(options: {
        loadConfig?: boolean,
        defaultDisplayMode?: string
    } = {}): Promise<void> {
        if (this.initialized) {
            console.warn('ErrorManager already initialized');
            return Promise.resolve();
        }

        // Set default display mode if provided
        if (options.defaultDisplayMode) {
            this.defaultDisplayMode = options.defaultDisplayMode;
        }

        // Load configurations if needed
        if (options.loadConfig !== false) {
            try {
                await errorConfig.loadConfig();
            } catch (error) {
                console.error('Failed to load error configurations:', error);
                // Continue initialization even if config loading fails
                // We'll use fallback configurations
            }
        }

        this.initialized = true;
        return Promise.resolve();
    }

    /**
     * Register a new error handler
     *
     * @param {ErrorHandler} handler - The handler to register
     * @returns {ErrorManager} The error manager instance for chaining
     */
    public registerHandler(handler: ErrorHandler): ErrorManager {
        this.handlers.push(handler);
        return this;
    }

    /**
     * Handle a server error response
     *
     * @param {ServerErrorResponse} response - The error response from server
     * @param {ErrorContext} context - Additional context for the error
     */
    public handleServerError(response: ServerErrorResponse, context: ErrorContext = {}): void {
        if (!this.initialized) {
            console.warn('ErrorManager not initialized, initializing now...');
            this.initialize().then(() => {
                this.handleServerError(response, context);
            });
            return;
        }

        // Get additional configuration if available
        const config = errorConfig.getErrorConfig(response.error);

        // Format the user message with context variables
        const userMessage = this.formatMessage(response.message, context);

        // Process through each handler
        for (const handler of this.handlers) {
            if (handler.shouldHandle(response.error, config)) {
                handler.handle(
                    response.error,
                    userMessage,
                    response.display_mode || (config?.msg_to || this.defaultDisplayMode),
                    response.blocking || (config?.blocking || 'not'),
                    context
                );
            }
        }

        // Dispatch an error event for other parts of the application
        this.dispatchErrorEvent(response.error, userMessage, response.blocking, context);

        // If this is a blocking error, additional action might be needed
        if (response.blocking === 'blocking' && (config?.type === 'critical')) {
            this.handleCriticalBlockingError(response.error, userMessage, context);
        }
    }

    /**
     * Handle a client-side error
     *
     * @param {string} errorCode - The error code
     * @param {ErrorContext} context - Additional context for the error
     * @param {Error} originalError - The original error object if available
     */
    public handleClientError(errorCode: string, context: ErrorContext = {}, originalError?: Error): void {
        if (!this.initialized) {
            console.warn('ErrorManager not initialized, initializing now...');
            this.initialize().then(() => {
                this.handleClientError(errorCode, context, originalError);
            });
            return;
        }

        // Get error configuration if available
        const config = errorConfig.getErrorConfig(errorCode);

        if (!config) {
            console.error(`Error code not defined: ${errorCode}`, context, originalError);
            // Fall back to UNEXPECTED_ERROR
            this.handleClientError('UNEXPECTED_ERROR', {
                originalCode: errorCode,
                originalContext: JSON.stringify(context),
                originalError: originalError?.message || 'Unknown error'
            }, originalError);
            return;
        }

        // Get the user message from translations or config
        const userMessage = this.getUserMessage(errorCode, config, context);

        // Process through each handler
        for (const handler of this.handlers) {
            if (handler.shouldHandle(errorCode, config)) {
                handler.handle(
                    errorCode,
                    userMessage,
                    config.msg_to || this.defaultDisplayMode,
                    config.blocking || 'not',
                    context,
                    originalError
                );
            }
        }

        // Dispatch an error event for other parts of the application
        this.dispatchErrorEvent(errorCode, userMessage, config.blocking, context, originalError);

        // If this is a blocking error, additional action might be needed
        if (config.blocking === 'blocking' && config.type === 'critical') {
            this.handleCriticalBlockingError(errorCode, userMessage, context, originalError);
        }
    }

    /**
     * Get a translated user message for an error
     *
     * @param {string} errorCode - The error code
     * @param {ErrorConfig} config - The error configuration
     * @param {ErrorContext} context - Context data for placeholders
     * @returns {string} The formatted user message
     */
    private getUserMessage(errorCode: string, config: ErrorConfig, context: ErrorContext): string {
        // Try to get from window.translations first (highest priority)
        if (typeof window !== 'undefined' &&
            window.translations &&
            window.translations.js &&
            window.translations.js[errorCode]) {
            return this.formatMessage(window.translations.js[errorCode], context);
        }

        // Try to use user_message_key if available
        if (config.user_message_key) {
            // Check if window.translations contains this key
            const parts = config.user_message_key.split('.');
            let translation: any = window.translations;

            if (translation) {
                for (const part of parts) {
                    if (translation && translation[part]) {
                        translation = translation[part];
                    } else {
                        translation = null;
                        break;
                    }
                }

                if (typeof translation === 'string') {
                    return this.formatMessage(translation, context);
                }
            }
        }

        // Fallback to direct message or dev_message
        return this.formatMessage(
            config.message ||
            config.dev_message ||
            `An error occurred (${errorCode})`,
            context
        );
    }

    /**
     * Format a message by replacing placeholders with context values
     *
     * @param {string} message - The message with placeholders
     * @param {ErrorContext} context - Context data for placeholders
     * @returns {string} The formatted message
     */
    private formatMessage(message: string, context: ErrorContext): string {
        let formattedMessage = message;

        // Replace :key placeholders with context values
        for (const key in context) {
            if (Object.prototype.hasOwnProperty.call(context, key) &&
                (typeof context[key] === 'string' || typeof context[key] === 'number')) {
                const regex = new RegExp(`:${key}`, 'g');
                formattedMessage = formattedMessage.replace(regex, String(context[key]));
            }
        }

        return formattedMessage;
    }

    /**
     * Handle a critical blocking error
     * This method can be extended to implement additional actions for critical errors
     *
     * @param {string} errorCode - The error code
     * @param {string} message - The error message
     * @param {ErrorContext} context - Error context
     * @param {Error} originalError - The original error if available
     */
    private handleCriticalBlockingError(
        errorCode: string,
        message: string,
        context: ErrorContext,
        originalError?: Error
    ): void {
        // Log detailed information for critical errors
        console.error('CRITICAL ERROR', {
            errorCode,
            message,
            context,
            originalError
        });

        // In production, we might want to:
        // 1. Disable the UI
        // 2. Force a reload after a delay
        // 3. Send detailed telemetry to the server
        // 4. Show a full-page error overlay

        // These actions would depend on specific application requirements
    }

    /**
     * Dispatch a custom event for the error
     *
     * @param {string} errorCode - The error code
     * @param {string} message - The error message
     * @param {string} blocking - The blocking level
     * @param {ErrorContext} context - Error context
     * @param {Error} originalError - The original error if available
     */
    private dispatchErrorEvent(
        errorCode: string,
        message: string,
        blocking: string,
        context: ErrorContext,
        originalError?: Error
    ): void {
        try {
            // Create a custom event with error details
            const errorEvent = new CustomEvent('ultraError', {
                detail: {
                    errorCode,
                    message,
                    blocking,
                    context,
                    originalError: originalError ? {
                        message: originalError.message,
                        name: originalError.name
                    } : undefined,
                    timestamp: new Date().toISOString()
                },
                bubbles: true,
                cancelable: true
            });

            // Dispatch the event for other components to listen
            document.dispatchEvent(errorEvent);
        } catch (e) {
            console.warn('Failed to dispatch error event:', e);
        }
    }
 }

 // Export a singleton instance for easier usage
 export const ultraError = ErrorManager.getInstance();

 // Add to window interface for type safety
 declare global {
    interface Window {
        translations?: {
            js?: Record<string, any>;
        };
    }
 }
