/**
* /home/fabio/sandbox/UltraUploadSandbox/packages/ultra/errormanager/resources/ts/handlers/ErrorDisplayHandler.ts
*
* Ultra Error Manager - Error Display Handler
*
* This handler is responsible for displaying errors to the user through various UI mechanisms.
* It implements the ErrorHandler interface and supports multiple display modes including:
* - div (DOM elements)
* - sweet-alert (SweetAlert2 modals)
* - toast (Toast notifications)
* - log-only (Console logging only)
*
* The handler uses the Strategy pattern to select the appropriate display method based on
* the error configuration and context. It ensures consistent presentation of errors
* throughout the application while respecting the server-side display preferences.
*
* Security considerations:
* - All output is properly escaped to prevent XSS attacks
* - Error messages are sanitized before display
* - No sensitive information is leaked to the user interface
*
* @module UltraErrorManager/Handlers
* @version 1.0.0
*/

import {
    ErrorHandler,
    ErrorConfig,
    ErrorContext,
    DisplayMode,
    BlockingLevel
 } from '../interfaces/ErrorTypes';

 /**
 * Implements the ErrorHandler interface for displaying errors to the user
 */
 export class ErrorDisplayHandler implements ErrorHandler {
    private domErrorContainer: HTMLElement | null = null;
    private statusMessageElement: HTMLElement | null = null;

    /**
     * Constructor sets up the DOM error containers if available
     */
    constructor() {
        // Try to find the standard error containers in the DOM
        this.domErrorContainer = document.getElementById('status') ||
                                 document.getElementById('error-container');
        this.statusMessageElement = document.getElementById('status-message') ||
                                    document.getElementById('error-message');
    }

    /**
     * Determines if this handler should process the given error
     *
     * @param {string} errorCode - The error code
     * @param {ErrorConfig|null} config - The error configuration if available
     * @returns {boolean} True if this handler should process the error
     */
    shouldHandle(errorCode: string, config: ErrorConfig | null): boolean {
        // This handler should process all errors except those with msg_to: 'log-only'
        // or those that explicitly opt out of display
        return !(config && config.msg_to === DisplayMode.LOG_ONLY);
    }

    /**
     * Handles displaying the error to the user
     *
     * @param {string} errorCode - The error code
     * @param {string} message - The error message
     * @param {string} displayMode - How to display the error
     * @param {string} blocking - The blocking level
     * @param {ErrorContext} context - Additional context for the error
     * @param {Error} [originalError] - The original error object if available
     */
    handle(
        errorCode: string,
        message: string,
        displayMode: string,
        blocking: string,
        context: ErrorContext,
        originalError?: Error
    ): void {
        // Always log the error to the console for debugging
        this.logErrorToConsole(errorCode, message, displayMode, blocking, context, originalError);

        // Display the error based on the specified mode
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

            case DisplayMode.LOG_ONLY:
                // Already logged to console above
                break;

            default:
                // Default to DOM display
                this.displayInDomElement(errorCode, message, blocking);
                break;
        }
    }

    /**
     * Logs the error to the console for debugging purposes
     *
     * @param {string} errorCode - The error code
     * @param {string} message - The error message
     * @param {string} displayMode - How the error is being displayed
     * @param {string} blocking - The blocking level
     * @param {ErrorContext} context - Additional context for the error
     * @param {Error} [originalError] - The original error object if available
     */
    private logErrorToConsole(
        errorCode: string,
        message: string,
        displayMode: string,
        blocking: string,
        context: ErrorContext,
        originalError?: Error
    ): void {
        const logLevel = blocking === BlockingLevel.BLOCKING ? 'error' :
                        (blocking === BlockingLevel.SEMI_BLOCKING ? 'warn' : 'info');

        const logMessage = `[${errorCode}] ${message}`;
        const logData = {
            displayMode,
            blocking,
            context,
            timestamp: new Date().toISOString()
        };

        if (originalError) {
            logData['originalError'] = {
                message: originalError.message,
                name: originalError.name,
                stack: originalError.stack
            };
        }

        // Use the appropriate console method based on the log level
        switch (logLevel) {
            case 'error':
                console.error(logMessage, logData);
                break;
            case 'warn':
                console.warn(logMessage, logData);
                break;
            default:
                console.info(logMessage, logData);
                break;
        }
    }

    /**
     * Displays the error using SweetAlert2
     *
     * @param {string} errorCode - The error code
     * @param {string} message - The error message
     * @param {string} blocking - The blocking level
     */
    private displayWithSweetAlert(errorCode: string, message: string, blocking: string): void {
        // Dynamically import SweetAlert2 to avoid unnecessary loading if not used
        import('sweetalert2').then(Swal => {
            const iconType = blocking === BlockingLevel.BLOCKING ? 'error' :
                            (blocking === BlockingLevel.SEMI_BLOCKING ? 'warning' : 'info');

            // Get appropriate title from translations or use defaults
            const title = this.getTranslatedTitle(errorCode, blocking) ||
                        (blocking === BlockingLevel.BLOCKING ? 'Error' :
                        (blocking === BlockingLevel.SEMI_BLOCKING ? 'Warning' : 'Notice'));

            // Get button text from translations or use default
            const confirmButtonText = window.translations?.js?.ok_button || 'OK';

            // Show the alert
            Swal.default.fire({
                title: this.escapeHtml(title),
                text: this.escapeHtml(message),
                icon: iconType,
                confirmButtonText: this.escapeHtml(confirmButtonText)
            });
        }).catch(err => {
            console.error('Failed to load SweetAlert2:', err);
            // Fallback to DOM display if SweetAlert fails to load
            this.displayInDomElement(errorCode, message, blocking);
        });
    }

    /**
     * Displays the error using a toast notification
     *
     * @param {string} errorCode - The error code
     * @param {string} message - The error message
     * @param {string} blocking - The blocking level
     */
    private displayWithToast(errorCode: string, message: string, blocking: string): void {
        // Check if a toast function is available in the window object
        if (typeof window.showToast === 'function') {
            const type = blocking === BlockingLevel.BLOCKING ? 'error' :
                       (blocking === BlockingLevel.SEMI_BLOCKING ? 'warning' : 'info');

            window.showToast(message, type);
        } else {
            // Fallback to SweetAlert with toast configuration
            import('sweetalert2').then(Swal => {
                const Toast = Swal.default.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: blocking === BlockingLevel.BLOCKING ? 0 : 5000,
                    timerProgressBar: blocking !== BlockingLevel.BLOCKING
                });

                const iconType = blocking === BlockingLevel.BLOCKING ? 'error' :
                               (blocking === BlockingLevel.SEMI_BLOCKING ? 'warning' : 'info');

                Toast.fire({
                    icon: iconType,
                    title: this.escapeHtml(message)
                });
            }).catch(err => {
                console.error('Failed to load SweetAlert2 for toast:', err);
                // Fallback to DOM display
                this.displayInDomElement(errorCode, message, blocking);
            });
        }
    }

    /**
     * Displays the error in a DOM element
     *
     * @param {string} errorCode - The error code
     * @param {string} message - The error message
     * @param {string} blocking - The blocking level
     */
    private displayInDomElement(errorCode: string, message: string, blocking: string): void {
        // Update the status message element if available
        if (this.statusMessageElement) {
            this.statusMessageElement.innerText = message;

            // Set appropriate classes based on blocking level
            this.statusMessageElement.className = this.getStatusMessageClasses(blocking);

            // Remove animation class if present
            this.statusMessageElement.classList.remove('animate-pulse');
        }

        // Add the error to the status/error container if available
        if (this.domErrorContainer) {
            const colorClass = this.getColorClassForError(blocking);
            const bgClass = this.getBackgroundClassForError(blocking);

            const errorElement = document.createElement('p');
            errorElement.className = `font-bold ${colorClass} ${bgClass} px-4 py-2 rounded-lg shadow-md mt-2`;
            errorElement.innerText = message;

            // Add a data attribute for the error code (useful for testing/debugging)
            errorElement.dataset.errorCode = errorCode;

            this.domErrorContainer.appendChild(errorElement);

            // Scroll the container to show the new error
            this.domErrorContainer.scrollTop = this.domErrorContainer.scrollHeight;
        }
    }

    /**
     * Gets the title for an error from translations
     *
     * @param {string} errorCode - The error code
     * @param {string} blocking - The blocking level
     * @returns {string|null} The translated title or null if not found
     */
    private getTranslatedTitle(errorCode: string, blocking: string): string | null {
        // Try to get a specific title for this error code
        if (window.translations?.js?.errors?.titles?.[errorCode]) {
            return window.translations.js.errors.titles[errorCode];
        }

        // Fall back to generic titles based on blocking level
        if (blocking === BlockingLevel.BLOCKING && window.translations?.js?.errors?.error_title) {
            return window.translations.js.errors.error_title;
        }

        if (blocking === BlockingLevel.SEMI_BLOCKING && window.translations?.js?.errors?.warning_title) {
            return window.translations.js.errors.warning_title;
        }

        if (window.translations?.js?.errors?.notice_title) {
            return window.translations.js.errors.notice_title;
        }

        return null;
    }

    /**
     * Gets the CSS classes for the status message based on blocking level
     *
     * @param {string} blocking - The blocking level
     * @returns {string} The CSS classes
     */
    private getStatusMessageClasses(blocking: string): string {
        switch (blocking) {
            case BlockingLevel.BLOCKING:
                return 'font-bold text-red-700';
            case BlockingLevel.SEMI_BLOCKING:
                return 'font-bold text-yellow-700';
            default:
                return 'font-bold text-blue-700';
        }
    }

    /**
     * Gets the text color CSS class based on blocking level
     *
     * @param {string} blocking - The blocking level
     * @returns {string} The CSS class
     */
    private getColorClassForError(blocking: string): string {
        switch (blocking) {
            case BlockingLevel.BLOCKING:
                return 'text-red-700';
            case BlockingLevel.SEMI_BLOCKING:
                return 'text-yellow-700';
            default:
                return 'text-blue-700';
        }
    }

    /**
     * Gets the background color CSS class based on blocking level
     *
     * @param {string} blocking - The blocking level
     * @returns {string} The CSS class
     */
    private getBackgroundClassForError(blocking: string): string {
        switch (blocking) {
            case BlockingLevel.BLOCKING:
                return 'bg-red-200';
            case BlockingLevel.SEMI_BLOCKING:
                return 'bg-yellow-200';
            default:
                return 'bg-blue-200';
        }
    }

    /**
     * Escapes HTML special characters to prevent XSS attacks
     *
     * @param {string} html - The string to escape
     * @returns {string} The escaped string
     */
    private escapeHtml(html: string): string {
        const div = document.createElement('div');
        div.textContent = html;
        return div.innerHTML;
    }
 }

 // Add to window interface for type safety
 declare global {
    interface Window {
        showToast?: (message: string, type: string) => void;
        translations?: {
            js?: {
                errors?: {
                    titles?: Record<string, string>;
                    error_title?: string;
                    warning_title?: string;
                    notice_title?: string;
                };
                ok_button?: string;
            };
        };
    }
 }
