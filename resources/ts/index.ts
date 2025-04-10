// /home/fabio/libraries/UltraErrorManager/resources/ts/index.ts
// Oracode/GDPR Refactoring by Padmin D. Curtis

// --- Oracode Refined Module Doc ---
/**
 * 🎯 Purpose: Serves as the main entry point and public API facade for the UEM
 *    client-side library. Exports core classes, singleton instances, types,
 *    and provides convenience functions for initialization and error handling.
 *    Simplifies integration into the consuming application.
 *
 * 🧱 Structure:
 *    - Re-exports `ErrorManager` class and its singleton `ultraError`.
 *    - Re-exports `ErrorDisplayHandler` class.
 *    - Re-exports `ErrorConfigLoader` class and its singleton `errorConfig`.
 *    - Re-exports all types/interfaces from `ErrorTypes.ts`.
 *    - Provides convenience functions: `initializeUEM`, `handleClientError`, `handleServerError`,
 *      `safeFetch` (fetch wrapper), `onUltraError` (event listener helper).
 *    - Exports a consolidated `UEM` object literal acting as a simplified API facade.
 *
 * 📡 Communicates:
 *    - Acts as an intermediary, delegating calls to the underlying `ultraError` (ErrorManager)
 *      or `errorConfig` (ErrorConfigLoader) instances.
 *    - `safeFetch` interacts with the global `fetch` API and delegates errors to `ultraError`.
 *    - `onUltraError` interacts with the global `document` to add/remove event listeners.
 *
 * 🧪 Testable:
 *    - Convenience functions are generally thin wrappers around testable singletons (`ultraError`, `errorConfig`).
 *      Testing focuses on ensuring delegation occurs correctly.
 *    - `safeFetch` requires mocking the global `fetch` API and potentially `ultraError` for error handling verification.
 *    - `onUltraError` requires mocking `document.addEventListener/removeEventListener`.
 *    - The exported `UEM` object's methods are tested similarly to the standalone functions.
 *
 * 🛡️ GDPR Considerations:
 *    - Acts primarily as a pass-through. GDPR responsibilities lie within the components it wraps (`ErrorManager`, `ErrorConfigLoader`).
 *    - `handleClientError`/`handleServerError` functions accept `context` (`@data-input`), which is passed to `ErrorManager`.
 *    - `safeFetch` handles server responses which might contain user messages (`@data-input` from server, potentially `@data-output` if re-thrown). It passes errors (potentially containing details) to `ErrorManager`.
 *    - `onUltraError` provides access to the `ultraError` event, whose detail payload (`UltraErrorEventDetail`) contains context (`@data-output`). Callbacks using this must handle the data responsibly.
 *
 * @module UltraErrorManager/Index
 * @version 1.0.1 // Match ErrorManager version or maintain independently
 */

// --- Core Exports ---
import { ErrorManager, ultraError } from './ErrorManager';
export { ErrorManager, ultraError };

import { ErrorDisplayHandler } from './handlers/ErrorDisplayHandler';
export { ErrorDisplayHandler }; // Assuming this is the primary handler to expose

import { ErrorConfigLoader, errorConfig } from './utils/ErrorConfigLoader';
export { ErrorConfigLoader, errorConfig };

// --- Type Exports ---
// Re-export all types and interfaces for easy consumption
export * from './interfaces/ErrorTypes';

// --- Convenience Functions ---

/**
 * 🎯 Convenience function to initialize the Ultra Error Manager singleton.
 * 📡 Delegates directly to `ultraError.initialize()`.
 * @public
 * @async
 * @param {object} [options={}] Initialization options (see `ErrorManager.initialize`).
 * @param {boolean} [options.loadConfig=true] Whether to load error configurations.
 * @param {DisplayMode} [options.defaultDisplayMode] Override the default display mode.
 * @returns {Promise<void>} A promise that resolves when initialization is complete.
 * @see {ErrorManager.initialize}
 */
export async function initializeUEM(options: {
   loadConfig?: boolean,
   defaultDisplayMode?: import('./interfaces/ErrorTypes').DisplayMode // Use imported type for clarity
} = {}): Promise<void> {
   // Delegate to the singleton instance
   return ultraError.initialize(options);
}

/**
 * 🎯 Convenience function to handle a client-side error via the singleton.
 * 📡 Delegates directly to `ultraError.handleClientError()`.
 * 📥 @data-input Passes `errorCode`, `context`, `originalError` to ErrorManager.
 * @public
 * @param {string} errorCode - The symbolic UEM error code.
 * @param {Record<string, any>} [context={}] - Additional context for the error (use `ErrorContext` type ideally).
 * @param {Error} [originalError] - The original JavaScript Error object, if available.
 * @returns {void}
 * @see {ErrorManager.handleClientError}
 */
export function handleClientError(
   errorCode: string,
   context: Record<string, any> = {}, // Consider using ErrorContext type alias here
   originalError?: Error
): void {
   // Delegate to the singleton instance
   ultraError.handleClientError(errorCode, context, originalError);
}

/**
 * 🎯 Convenience function to handle a server error response via the singleton.
 * 📡 Delegates directly to `ultraError.handleServerError()`.
 * 📥 @data-input Passes `response` object and `context` to ErrorManager.
 * @public
 * @param {object} response - The error response object from the server (use `ServerErrorResponse` type ideally).
 * @param {string} response.error - Error code.
 * @param {string} response.message - User-facing message.
 * @param {string} response.blocking - Blocking level.
 * @param {string} response.display_mode - Display mode.
 * @param {any} [response.details] - Optional details.
 * @param {Record<string, any>} [context={}] - Additional client-side context (use `ErrorContext` type ideally).
 * @returns {void}
 * @see {ErrorManager.handleServerError}
 * @see {ServerErrorResponse}
 */
export function handleServerError(
   response: import('./interfaces/ErrorTypes').ServerErrorResponse, // Use imported type
   context: Record<string, any> = {} // Consider using ErrorContext type alias here
): void {
   // Delegate to the singleton instance
   ultraError.handleServerError(response, context);
}

/**
 * 🎯 Provides a wrapped version of the global `fetch` function that automatically
 *    handles UEM-formatted JSON errors and common network/HTTP errors using `ultraError`.
 * 🔥 Acts as an error boundary for fetch calls.
 * 📡 Interacts with global `fetch`. Delegates errors to `ultraError`.
 * 📥 @data-input Receives server response (potentially containing error details).
 * 📤 @data-output Re-throws original error on fetch failure after handling via UEM.
 * @public
 * @async
 * @param {RequestInfo} input - The resource to fetch (URL string or Request object).
 * @param {RequestInit} [init] - Optional request configuration options.
 * @returns {Promise<Response>} The fetch Response object if the request was successful (even if HTTP status indicates an error handled by UEM).
 * @throws {Error} Re-throws the original network error or fetch exception after handling it via UEM.
 * @testability Requires mocking global `fetch` and potentially `ultraError`.
 */
export async function safeFetch(
   input: RequestInfo,
   init?: RequestInit
): Promise<Response> {
    let response: Response;
    try {
        response = await fetch(input, init);

        // Check if response indicates failure (e.g., 4xx, 5xx)
        if (!response.ok) {
            const contentType = response.headers.get('content-type');
            let errorHandled = false;

            // Attempt to parse as JSON only if Content-Type indicates it
            if (contentType && contentType.includes('application/json')) {
                try {
                    // Clone response to allow body parsing here AND return original response later
                    const errorData = await response.clone().json();

                    // Check if it matches the UEM ServerErrorResponse structure
                    if (errorData && typeof errorData.error === 'string' && typeof errorData.message === 'string') {
                        console.debug('[UEM safeFetch] Handling UEM JSON error response.');
                        // Use the specific ServerErrorResponse type if possible
                        ultraError.handleServerError(errorData as import('./interfaces/ErrorTypes').ServerErrorResponse);
                        errorHandled = true;
                    } else {
                        // It's JSON, but not a recognized UEM error format
                        console.warn('[UEM safeFetch] Received non-UEM JSON error response.');
                        ultraError.handleClientError('UNEXPECTED_ERROR', { // Use a more specific code? Maybe SERVER_MALFORMED_JSON?
                            status: response.status,
                            statusText: response.statusText,
                            responseBody: errorData, // Include parsed body for debugging
                            url: typeof input === 'string' ? input : (input as Request).url,
                        });
                        errorHandled = true; // Handled as unexpected
                    }
                } catch (jsonError: any) {
                    // Failed to parse JSON, even though header indicated it
                    console.error('[UEM safeFetch] Failed to parse JSON error response:', jsonError);
                    ultraError.handleClientError('JSON_ERROR', {
                        status: response.status,
                        statusText: response.statusText,
                        url: typeof input === 'string' ? input : (input as Request).url,
                        parseErrorMessage: jsonError?.message
                    });
                    errorHandled = true;
                }
            }

            // If not JSON or if JSON parsing failed but error wasn't handled yet
            if (!errorHandled) {
                console.warn(`[UEM safeFetch] Handling generic HTTP error (Status: ${response.status}).`);
                // Handle non-JSON HTTP errors (e.g., 500 with HTML page, 404)
                ultraError.handleClientError('SERVER_ERROR', { // Generic HTTP error code
                    status: response.status,
                    statusText: response.statusText,
                    url: typeof input === 'string' ? input : (input as Request).url,
                    contentType: contentType ?? 'N/A',
                });
                // Consider errorHandled = true here as well, as we've logged it via UEM
            }
        }

        // Return the original response object regardless of ok status,
        // allowing the caller to potentially handle non-UEM errors or process successful responses.
        return response;

    } catch (error: any) { // Catch network errors or other exceptions during fetch itself
        console.error('[UEM safeFetch] Network or fetch exception occurred:', error);
        // Handle network errors (e.g., DNS lookup failure, connection refused)
        ultraError.handleClientError('NETWORK_ERROR', {
            url: typeof input === 'string' ? input : (input as Request).url,
            errorMessage: error?.message,
        }, error instanceof Error ? error : undefined); // Pass the original error object if it's an Error

        // Re-throw the original error to allow the calling code to implement
        // its own specific error handling (e.g., retries, state updates)
        // after UEM has logged/processed it.
        throw error;
    }
}

/**
 * 🎯 Convenience function to add an event listener for the `ultraError` CustomEvent.
 * 📡 Interacts with `document.addEventListener`.
 * @public
 * @param {(event: import('./interfaces/ErrorTypes').UltraErrorEvent) => void} callback - The function to execute when the event is dispatched. Receives the `UltraErrorEvent`.
 * @returns {() => void} A function that, when called, removes the event listener.
 * @see {UltraErrorEvent}
 * @testability Requires mocking `document.addEventListener/removeEventListener`.
 */
export function onUltraError(
   callback: (event: import('./interfaces/ErrorTypes').UltraErrorEvent) => void
): () => void {
    // Wrapper function to ensure correct type casting inside the listener
    const eventHandler = (event: Event) => {
        // Type assertion to treat the generic Event as our specific UltraErrorEvent
        callback(event as import('./interfaces/ErrorTypes').UltraErrorEvent);
    };

    // Add the listener to the document
    if (typeof document !== 'undefined') {
         document.addEventListener('ultraError', eventHandler);
    } else {
         console.warn('[UEM onUltraError] Cannot add listener in non-browser environment.');
         // Return a no-op function if document is not available
         return () => {};
    }

    // Return a cleanup function to remove the listener
    return () => {
        if (typeof document !== 'undefined') {
            document.removeEventListener('ultraError', eventHandler);
            console.debug('[UEM onUltraError] Removed ultraError event listener.');
        }
    };
}

// --- Consolidated API Object ---
/**
 * 🎯 Provides a simplified, consolidated API object (Facade pattern) for interacting
 *    with the UEM client-side library. Bundles common functions and getters.
 * 🧱 Acts as a convenient alternative to importing individual functions/singletons.
 * @public
 */
export const UEM = {
    /** @see initializeUEM */
    initialize: initializeUEM,
    /** @see handleClientError */
    handleClientError,
    /** @see handleServerError */
    handleServerError,
    /** @see safeFetch */
    safeFetch,
    /** @see onUltraError */
    onError: onUltraError,
    /**
     * 📡 Get configuration for a specific error code.
     * Delegates to `errorConfig.getErrorConfig()`.
     * @param {string} errorCode - The error code.
     * @returns {import('./interfaces/ErrorTypes').ErrorConfig | null} Configuration or null.
     * @see {ErrorConfigLoader.getErrorConfig}
     */
    getErrorConfig: (errorCode: string): import('./interfaces/ErrorTypes').ErrorConfig | null => {
        return errorConfig.getErrorConfig(errorCode);
    },
    /**
     * 📡 Get all available error codes.
     * Delegates to `errorConfig.getAllErrorCodes()`.
     * @returns {string[]} Array of error codes.
     * @see {ErrorConfigLoader.getAllErrorCodes}
     */
    getAllErrorCodes: (): string[] => {
        return errorConfig.getAllErrorCodes();
    },
    /**
     * 📡 Check if configurations are loaded.
     * Delegates to `errorConfig.isConfigLoaded()`.
     * @returns {boolean} True if loaded or fallback active.
     * @see {ErrorConfigLoader.isConfigLoaded}
     */
    isConfigLoaded: (): boolean => {
        return errorConfig.isConfigLoaded();
    }
};

// Default export for better ESM compatibility or alternative import styles
export default UEM;