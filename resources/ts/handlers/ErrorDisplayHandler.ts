// /home/fabio/libraries/UltraErrorManager/resources/ts/handlers/ErrorDisplayHandler.ts
// Oracode/GDPR Refactoring by Padmin D. Curtis

// --- Dependency Imports ---
import {
    ErrorHandler,
    ErrorConfig,
    ErrorContext,
    DisplayMode, // Use Enum
    BlockingLevel // Use Enum
 } from '../interfaces/ErrorTypes'; // Assuming path is correct

 // --- SweetAlert2 Dynamic Import Type ---
 // Allows type checking for the dynamically imported library
 type SwalType = typeof import('sweetalert2').default;


// --- Oracode Refined Class Doc ---
/**
 * 🎯 Purpose: Implements the `ErrorHandler` interface to visually display processed errors
 *    to the end-user. Acts as the primary presentation layer handler within the client-side UEM,
 *    supporting various UI mechanisms like DOM manipulation, SweetAlert2 modals, and toast notifications.
 *
 * 🧱 Structure:
 *    - Implements `ErrorHandler`.
 *    - Caches references to potential DOM target elements (`domErrorContainer`, `statusMessageElement`) in the constructor.
 *    - `shouldHandle`: Determines applicability based on `msg_to` config (skips `LOG_ONLY`).
 *    - `handle`: Core logic dispatcher. Logs to console first, then calls specific display methods based on `displayMode`.
 *    - Private display methods (`displayWithSweetAlert`, `displayWithToast`, `displayInDomElement`): Implement strategy pattern for different UI outputs.
 *    - Helper methods for translation lookup (`getTranslatedTitle`, `getTranslatedButtonText`), CSS class generation (`getStatusMessageClasses`, etc.), and HTML escaping (`escapeHtml`).
 *
 * 📡 Communicates:
 *    - Reads global `document` to find DOM elements and dispatch events (indirectly via ErrorManager).
 *    - Writes error messages to specific DOM elements (`#status`, `#error-container`, `#status-message`, `#error-message`) via `innerText`.
 *    - Calls browser `console` methods for mandatory internal logging.
 *    - Dynamically imports and calls `SweetAlert2` library if `msg_to` requires it.
 *    - Calls global `window.showToast` function if available and `msg_to` requires it.
 *    - Reads global `window.translations` for localized UI strings (titles, buttons).
 *
 * 🧪 Testable:
 *    - Heavy dependency on global browser objects: `document`, `console`, `window` (for translations, toast), and external `SweetAlert2`.
 *    - Unit testing requires a robust DOM environment (like JSDOM) and extensive mocking/spying of these globals and dynamic imports.
 *    - **Suggestion:** Injecting dependencies (e.g., a DOM manipulation service, a notification service interface) would significantly improve isolated unit testability. Currently marked as needing enhancement for testing.
 *    - `shouldHandle` and helper methods (`escapeHtml`, CSS class getters) are more easily testable in isolation.
 *
 * 🛡️ GDPR Considerations:
 *    - Primarily handles `user_message` (`@data-input`/`📥` from ErrorManager), which *should* already be PII-safe and localized. The handler itself assumes this.
 *    - **Outputs data** (`@data-output`/`📤`) directly to the user interface (DOM via `innerText`, SweetAlert modals, Toasts).
 *    - Uses HTML escaping (`escapeHtml` -> `@sanitizer`/`🧼`) when generating alert titles/content to prevent XSS (`@privacy-safe`/`🛡️`).
 *    - Uses `innerText` for DOM manipulation, inherently preventing HTML/script injection (`@privacy-safe`/`🛡️`).
 *    - Logs potentially sensitive `context` and `originalError` details to the developer `console` (`@log`/`🪵`). Acceptable for dev, review for production log transport. Avoids displaying this raw data directly in the user-facing UI elements.
 *
 * @module UltraErrorManager/Handlers
 * @version 1.0.1 // Or align with ErrorManager version
 */
 export class ErrorDisplayHandler implements ErrorHandler {
    /** @private Reference to the main DOM container for multiple/appended errors. */
    private domErrorContainer: HTMLElement | null = null;
    /** @private Reference to a DOM element for displaying the single latest status/error. */
    private statusMessageElement: HTMLElement | null = null;

    /**
     * 🎯 Constructor: Initializes the handler by querying the DOM for potential target elements.
     * 📡 Reads `document.getElementById`.
     * 🪵 Logs a warning if standard elements are not found.
     */
    constructor() {
        // Ensure running in a browser context
        if (typeof document !== 'undefined') {
            // Try to find common target elements using specific IDs
            this.domErrorContainer = document.getElementById('error-container') // Prefer semantic ID
                                  || document.getElementById('status'); // Fallback legacy ID?

            this.statusMessageElement = document.getElementById('error-message') // Prefer semantic ID
                                     || document.getElementById('status-message'); // Fallback related ID?

            // Log a warning during initialization if target elements aren't found.
            // This helps diagnose why errors might not appear visually.
            if (!this.domErrorContainer) {
                 console.warn("[UEM DisplayHandler] Could not find 'error-container' or 'status' DOM element for appending messages.");
            }
            if (!this.statusMessageElement) {
                 console.warn("[UEM DisplayHandler] Could not find 'error-message' or 'status-message' DOM element for single status display.");
            }
             console.info('[UEM DisplayHandler] Initialized.');

        } else {
             console.warn('[UEM DisplayHandler] Cannot initialize DOM elements in non-browser environment.');
        }
    }

    /**
     * 🧠 Determine if this handler should process the given error.
     * Handles all errors *unless* explicitly marked as `DisplayMode.LOG_ONLY`.
     *
     * @param {string} _errorCode - The UEM error code (unused directly for this decision).
     * @param {ErrorConfig | null} config - The resolved error configuration.
     * @returns {boolean} True if the error should be displayed visually (not LOG_ONLY).
     */
    shouldHandle(_errorCode: string, config: ErrorConfig | null): boolean {
        // Determine display mode using config or fallback (defaulting to DIV if completely unspecified)
        const displayMode = config?.msg_to ?? DisplayMode.DIV;
        // Only skip if explicitly configured as LOG_ONLY
        return displayMode !== DisplayMode.LOG_ONLY;
    }

    /**
     * ✨ Handle displaying the error to the user based on the specified display mode.
     * 🚨 Logs the error details to the console first (for debugging), then dispatches to the appropriate display method.
     * 📥 @data-input Receives `message` (assumed user-safe) and `context` (potentially sensitive).
     * 📤 @data-output Writes `message` to UI (DOM, Alert, Toast).
     * 🪵 @log Logs error details (including potentially sensitive context/originalError) to developer console.
     *
     * @param {string} errorCode - The UEM error code.
     * @param {string} message - The prepared user-facing message (assumed PII-safe).
     * @param {string} displayMode - How to display the error (should map to `DisplayMode` enum).
     * @param {string} blocking - The blocking level (should map to `BlockingLevel` enum).
     * @param {ErrorContext} context - Additional context (logged to console).
     * @param {Error} [originalError] - The original JavaScript Error object (logged to console).
     */
    handle(
        errorCode: string,
        message: string,
        displayMode: string, // Consider enforcing DisplayMode type here in future?
        blocking: string, // Consider enforcing BlockingLevel type here in future?
        context: ErrorContext,
        originalError?: Error
    ): void {
        // 1. Log Internally (Always, before UI attempts)
        this.logErrorToConsole(errorCode, message, displayMode, blocking, context, originalError);

        // 2. Dispatch to UI Strategy
        switch (displayMode) {
            case DisplayMode.SWEET_ALERT:
                this.displayWithSweetAlert(errorCode, message, blocking);
                break;
            case DisplayMode.TOAST:
                this.displayWithToast(errorCode, message, blocking);
                break;
            case DisplayMode.DIV:
                this.displayInDomElement(errorCode, message, blocking);
                break;
            // case DisplayMode.LOG_ONLY: // Already handled by shouldHandle and logged above
            //    break;
            default:
                // Handle unknown modes by logging a warning and falling back to DOM display
                console.warn(`[UEM DisplayHandler] Unknown display mode '${displayMode}' for error '${errorCode}'. Falling back to DOM display.`);
                this.displayInDomElement(errorCode, message, blocking);
                break;
        }
    }

    /**
     * 🪵 Internal: Logs the error details to the browser console for debugging.
     * Includes context and originalError details, intended for developers.
     * Uses appropriate console level (error, warn, info) based on blocking level.
     * 🛡️ Logs potentially sensitive `context` and `originalError`.
     * @private
     */
    private logErrorToConsole(
         errorCode: string, message: string, displayMode: string,
         blocking: string, context: ErrorContext, originalError?: Error
    ): void {
        const logLevel = blocking === BlockingLevel.BLOCKING ? 'error' :
                        (blocking === BlockingLevel.SEMI_BLOCKING ? 'warn' : 'info');

        // Construct log message and data object
        const logMessage = `[UEM Display|${logLevel.toUpperCase()}] Code: ${errorCode}, Mode: ${displayMode}, Blocking: ${blocking} | Msg: ${message}`;
        const logData: Record<string, any> = { // Use a more specific type if possible
             context: context, // Include full context for debugging
             timestamp: new Date().toISOString()
         };

        if (originalError) {
            // Include simplified original error info in console log
            logData.originalError = {
                name: originalError.name,
                message: originalError.message,
                // Optionally include a few lines of stack trace for console debugging
                // stack_snippet: originalError.stack?.split('\n').slice(0, 5).join('\n')
            };
        }

        // Use console[level] syntax for dynamic level logging
        if (console[logLevel]) {
             console[logLevel](logMessage, logData);
        } else {
             console.log(`[${logLevel.toUpperCase()}] ${logMessage}`, logData); // Fallback to console.log
        }
    }

    /**
     * ✨ Internal: Displays the error using SweetAlert2 (dynamically imported).
     * 📡 Dynamically imports `sweetalert2`. Reads `window.translations`.
     * 🛡️ @privacy-safe Uses `escapeHtml()` for title and text.
     * 🧼 @sanitizer Uses `escapeHtml()`.
     * @private
     */
    private displayWithSweetAlert(errorCode: string, message: string, blocking: string): void {
        // Dynamic import for lazy loading SweetAlert2
        import('sweetalert2').then(module => {
            const Swal: SwalType = module.default; // Access the default export
            const iconType = blocking === BlockingLevel.BLOCKING ? 'error' :
                            (blocking === BlockingLevel.SEMI_BLOCKING ? 'warning' : 'info');

            // Attempt to get localized title, provide sensible defaults
            const title = this.getTranslatedTitle(errorCode, blocking)
                          ?? (blocking === BlockingLevel.BLOCKING ? 'Error' : 'Notification'); // Simple defaults

            const confirmButtonText = this.getTranslatedButtonText() ?? 'OK'; // Default button text

            Swal.fire({
                title: this.escapeHtml(title), // Sanitize title
                text: this.escapeHtml(message), // Sanitize main message (double-check if message might contain intentional HTML)
                icon: iconType,
                confirmButtonText: this.escapeHtml(confirmButtonText), // Sanitize button text
                allowOutsideClick: blocking !== BlockingLevel.BLOCKING, // Prevent dismissal for blocking errors
                // customClass: { // Optional: Add custom classes for styling
                //    popup: 'uem-swal-popup',
                //    confirmButton: 'uem-swal-confirm'
                // }
            });
        }).catch(err => {
            console.error('[UEM DisplayHandler] Failed to load SweetAlert2 dynamically. Falling back to DOM display.', err);
            // Fallback strategy if dynamic import fails
            this.displayInDomElement(errorCode, message, blocking);
        });
    }

    /**
     * ✨ Internal: Displays the error using a toast notification.
     * 🧠 Prefers global `window.showToast`; falls back to SweetAlert2 toast.
     * 📡 Reads `window.showToast`. Dynamically imports `sweetalert2` for fallback.
     * 🛡️ @privacy-safe Assumes `window.showToast` handles its own escaping. Uses `escapeHtml()` for SweetAlert2 fallback.
     * 🧼 @sanitizer Uses `escapeHtml()` for fallback.
     * @private
     */
    private displayWithToast(errorCode: string, message: string, blocking: string): void {
        // 1. Check for globally defined custom toast function
        if (typeof window !== 'undefined' && typeof window.showToast === 'function') {
            const toastType = blocking === BlockingLevel.BLOCKING ? 'error' :
                              (blocking === BlockingLevel.SEMI_BLOCKING ? 'warning' : 'info');
            try {
                 // Assuming window.showToast handles necessary sanitization internally
                 window.showToast(message, toastType);
                 console.debug('[UEM DisplayHandler] Displayed error via window.showToast.');
            } catch (e) {
                 console.error("[UEM DisplayHandler] Error calling external window.showToast function. Falling back to DOM display.", e);
                 this.displayInDomElement(errorCode, message, blocking); // Fallback on error
            }
        } else {
            // 2. Fallback to SweetAlert2 toast
            console.debug('[UEM DisplayHandler] window.showToast not found. Attempting SweetAlert2 toast fallback.');
            import('sweetalert2').then(module => {
                const Swal: SwalType = module.default;
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end', // Common toast position
                    showConfirmButton: false, // Toasts usually don't have confirm buttons
                    timer: blocking === BlockingLevel.BLOCKING ? 6000 : 4000, // Longer timer for blocking, shorter for others
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });
                const iconType = blocking === BlockingLevel.BLOCKING ? 'error' :
                               (blocking === BlockingLevel.SEMI_BLOCKING ? 'warning' : 'info');

                Toast.fire({
                    icon: iconType,
                    // Use title field for the main message in toasts
                    title: this.escapeHtml(message) // Sanitize message
                });
            }).catch(err => {
                // 3. Ultimate Fallback if SweetAlert2 also fails
                console.error('[UEM DisplayHandler] Failed to load SweetAlert2 for toast fallback. Falling back to DOM display.', err);
                this.displayInDomElement(errorCode, message, blocking);
            });
        }
    }

    /**
     * ✨ Internal: Displays the error by updating/appending to specific DOM elements.
     * 📡 Interacts with `document` via cached element references.
     * 🛡️ @privacy-safe Assigns message to `innerText`, which prevents HTML/script injection.
     * @private
     */
    private displayInDomElement(errorCode: string, message: string, blocking: string): void {
        let displayed = false; // Track if message was displayed anywhere

        // 1. Update Single Status Element (if found)
        if (this.statusMessageElement) {
            this.statusMessageElement.innerText = message; // Use innerText for XSS safety
            // Apply styling classes based on blocking level
            this.statusMessageElement.className = this.getStatusMessageClasses(blocking);
            // Optional: Remove loading/progress indicators if used
            this.statusMessageElement.classList.remove('loading', 'animate-pulse'); // Example classes
            console.debug(`[UEM DisplayHandler] Updated status element (#${this.statusMessageElement.id}) for error ${errorCode}.`);
            displayed = true;
        }

        // 2. Append to Error Container (if found) - Good for showing multiple non-blocking errors
        if (this.domErrorContainer) {
            const errorElement = document.createElement('p'); // Or 'div' if more structure needed

            // Add semantic and styling classes (adjust based on your CSS framework/setup)
             errorElement.className = `uem-error-entry ${this.getEntryColorClass(blocking)} ${this.getEntryBackgroundClass(blocking)}`; // Example base + dynamic classes
             // Apply some basic styling inline if no CSS framework is assumed
             // errorElement.style.padding = '8px 12px';
             // errorElement.style.marginTop = '4px';
             // errorElement.style.borderRadius = '4px';
             // errorElement.style.fontWeight = '500';

            errorElement.innerText = message; // Use innerText for safety
            // Add data attributes for potential debugging or e2e testing hooks
            errorElement.dataset.errorCode = errorCode;
            errorElement.dataset.timestamp = new Date().toISOString();

            this.domErrorContainer.appendChild(errorElement);
            // Auto-scroll to show the latest message
            this.domErrorContainer.scrollTop = this.domErrorContainer.scrollHeight;
             console.debug(`[UEM DisplayHandler] Appended message to container (#${this.domErrorContainer.id}) for error ${errorCode}.`);

            // Optional: Limit number of entries in the container to prevent memory issues/DOM bloat
            // const MAX_ENTRIES = 50;
            // while (this.domErrorContainer.children.length > MAX_ENTRIES) {
            //    this.domErrorContainer.removeChild(this.domErrorContainer.firstChild!); // Use non-null assertion if sure
            // }
            displayed = true;
        }

        // 3. Log Warning if No Element Found
        if (!displayed) {
            // If neither the single status nor the container element was found, the error won't be visible.
             console.warn(`[UEM DisplayHandler] Could not display error message in DOM. No target elements found (tried #error-message, #status-message, #error-container, #status). Error Code: ${errorCode}`);
        }
    }

    /**
     * 🧱 Internal: Retrieves a translated title for alerts/modals from `window.translations`.
     * 📡 Reads global `window.translations`. Provides fallbacks.
     * @private
     */
    private getTranslatedTitle(errorCode: string, blocking: string): string | null {
        // Safely access potentially nested translation object
        try {
             const titles = window?.translations?.js?.errors?.titles;
             if (titles && typeof titles === 'object' && titles[errorCode]) {
                 return titles[errorCode]; // Return specific title if found
             }
             const errors = window?.translations?.js?.errors;
             if (blocking === BlockingLevel.BLOCKING && errors?.error_title) return errors.error_title;
             if (blocking === BlockingLevel.SEMI_BLOCKING && errors?.warning_title) return errors.warning_title;
             // Default for 'not' blocking or if others missing
             if (errors?.notice_title) return errors.notice_title;
        } catch (e) {
             console.warn('[UEM DisplayHandler] Error accessing window.translations for title:', e);
        }
        return null; // Return null if no suitable title found
    }

    /**
     * 🧱 Internal: Retrieves translated text for the confirmation button from `window.translations`.
     * 📡 Reads global `window.translations`.
     * @private
     */
     private getTranslatedButtonText(): string | null {
         try {
             return window?.translations?.js?.ok_button || null; // Example key, adjust as needed
         } catch (e) {
              console.warn('[UEM DisplayHandler] Error accessing window.translations for button text:', e);
              return null;
         }
     }


    /**
     * 💅 Internal: Gets CSS classes for the single status message element. Adjust based on CSS setup.
     * @private
     */
    private getStatusMessageClasses(blocking: string): string {
        const base = 'uem-status-message'; // Base class for easier selection
        switch (blocking) {
            case BlockingLevel.BLOCKING:      return `${base} uem-status-blocking text-red-700 dark:text-red-400 font-bold`; // Example Tailwind
            case BlockingLevel.SEMI_BLOCKING: return `${base} uem-status-semi-blocking text-yellow-700 dark:text-yellow-400 font-semibold`;
            default:                          return `${base} uem-status-not-blocking text-blue-700 dark:text-blue-400`; // Notice/Not Blocking
        }
    }

    /**
     * 💅 Internal: Gets text color CSS class for appended error entries. Adjust based on CSS setup.
     * @private
     */
    private getEntryColorClass(blocking: string): string {
        switch (blocking) {
            case BlockingLevel.BLOCKING:      return 'text-red-800 dark:text-red-100';
            case BlockingLevel.SEMI_BLOCKING: return 'text-yellow-800 dark:text-yellow-100';
            default:                          return 'text-blue-800 dark:text-blue-100';
        }
    }

    /**
     * 💅 Internal: Gets background color CSS class for appended error entries. Adjust based on CSS setup.
     * @private
     */
    private getEntryBackgroundClass(blocking: string): string {
        switch (blocking) {
            case BlockingLevel.BLOCKING:      return 'bg-red-100 dark:bg-red-900/50'; // Example Tailwind with dark opacity
            case BlockingLevel.SEMI_BLOCKING: return 'bg-yellow-100 dark:bg-yellow-900/50';
            default:                          return 'bg-blue-100 dark:bg-blue-900/50';
        }
    }

    /**
     * 🛡️ @privacy-safe Internal: Escapes HTML special characters to prevent XSS when inserting content
     *    into contexts that might interpret HTML (like SweetAlert title/text).
     * 🧼 @sanitizer
     * @param {string} unsafeHtml - The potentially unsafe string.
     * @returns {string} The escaped string, safe for insertion as text content or basic HTML attributes.
     * @private
     */
    private escapeHtml(unsafeHtml: string): string {
        // Use a robust method, preferably browser-native if available
        if (typeof document !== 'undefined' && typeof document.createElement === 'function') {
             // Create a temporary element, set its textContent (which escapes), then read innerHTML
             const tempElement = document.createElement('div');
             tempElement.textContent = unsafeHtml;
             return tempElement.innerHTML;
        } else {
            // Basic fallback for non-browser environments (less comprehensive)
            return unsafeHtml
                 .replace(/&/g, "&amp;") // Must be first
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#39;"); // Or &apos;
        }
    }
 }

 // --- Ensure Global Window Interface Augmentation is present ---
 // (Copied from original index.ts for completeness if this file is used standalone)
 declare global {
    interface Window {
        showToast?: (message: string, type: 'info' | 'warning' | 'error' | 'success') => void;
        translations?: {
            js?: {
                 ok_button?: string;
                 errors?: {
                     titles?: Record<string, string>;
                     error_title?: string;
                     warning_title?: string;
                     notice_title?: string;
                 };
                 [key: string]: any;
            };
        };
        // Add other potential window properties if needed
        envMode?: 'local' | 'development' | 'staging' | 'production';
        csrfToken?: string;
    }
 }