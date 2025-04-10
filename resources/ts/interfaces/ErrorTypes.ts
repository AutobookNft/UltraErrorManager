/**
 * // Original Comment Block (Good start, let's refine)
 * /home/fabio/sandbox/UltraUploadSandbox/packages/ultra/errormanager/resources/ts/interfaces/ErrorTypes.ts
 * Ultra Error Manager - Error Type Definitions
 * This file defines the TypeScript interfaces used throughout the Ultra Error Manager system.
 * It provides type safety and documentation for error handling components and ensures
 * consistency across the client-side error management infrastructure.
 * The interfaces defined here correspond to the server-side error configurations and
 * provide a strongly-typed foundation for error handling on the client.
 * @module UltraErrorManager/Interfaces
 * @version 1.0.0 // Keep or update version as needed
 */

// --- Oracode Refined Module Doc ---
/**
 * 🎯 Purpose: Defines the core data structures and type contracts for the UEM client-side system.
 * Ensures type safety and consistency between client/server configurations and event payloads.
 * 🧱 Structure: Exports TypeScript interfaces and enums. Mirrors key server-side configuration structures.
 * 📡 Communicates: Acts as the shared vocabulary between different client-side UEM modules/classes.
 * 🛡️ GDPR: Defines structures that *may hold* PII passed from the server (e.g., messages in ErrorConfig).
 *        Consumers of these types are responsible for handling contained data appropriately.
 * @module UltraErrorManager/Interfaces
 * @version 1.0.1 // Example version increment
 */


/**
 * 🎯 Defines the structure of a single error code's configuration.
 * Mirrors `config/error-manager.php['errors'][errorCode]` structure.
 * 🛡️ Note: Message keys/strings might resolve to PII if not managed server-side.
 * @version 1.0.2 // Bump version after adding notify_slack
 */
export interface ErrorConfig {
   /** @see {ErrorSeverity} Error severity level (e.g., 'critical', 'error'). */
   type: string; // Or ErrorSeverity enum value

   /** @see {BlockingLevel} Impact on application flow (e.g., 'blocking', 'not'). */
   blocking: string; // Or BlockingLevel enum value

   /** @param {string} [dev_message_key] Translation key for the developer-facing message. */
   dev_message_key?: string;

   /** @param {string} [user_message_key] Translation key for the user-facing message. */
   user_message_key?: string;

   /** @param {number} http_status_code Default HTTP status code associated with this error. */
   http_status_code: number;

   /** @param {boolean} devTeam_email_need Flag indicating if backend should trigger dev team email notification. */
   devTeam_email_need: boolean;

   /** @param {boolean} [notify_slack] Flag indicating if backend should trigger Slack notification. Added for consistency. */
   notify_slack?: boolean; // <<<<<<< AGGIUNTO QUI (opzionale)

   /** @see {DisplayMode} Preferred UI display mode (e.g., 'div', 'sweet-alert'). */
   msg_to: string; // Or DisplayMode enum value

   /** @see {RecoveryActionType} Optional identifier for an automated recovery action. */
   recovery_action?: string; // Or RecoveryActionType enum value

   /** @param {string} [message] Direct developer message (use discouraged, prefer keys or dev_message). */
   message?: string; // Could be resolved dev message

   /** @param {string} [dev_message] Direct developer message (alternative to key). */
   dev_message?: string;

   /** @param {string} [user_message] Direct user message (alternative to key, should be safe/localized). */
   user_message?: string;
}

/**
* 🎯 Defines the structure for configuring an error *type* (severity level).
* Mirrors `config/error-manager.php['error_types'][errorType]` structure.
*/
export interface ErrorTypeConfig {
   /** @param {string} log_level - Corresponding PSR log level (e.g., 'critical', 'error'). */
   log_level: string;

   /** @param {boolean} notify_team - Default notification flag for this error type. */
   notify_team: boolean;

   /** @param {number} http_status - Default HTTP status for this error type. */
   http_status: number;
}

/**
* 🎯 Defines the structure for configuring a *blocking level*.
* Mirrors `config/error-manager.php['blocking_levels'][blockingLevel]` structure.
*/
export interface BlockingLevelConfig {
   /** @param {boolean} terminate_request - Flag indicating if the backend request might be terminated. */
   terminate_request: boolean;

   /** @param {boolean} [clear_session] - Flag indicating if the user session might be cleared (backend). */
   clear_session?: boolean;

   /** @param {boolean} [flash_session] - Flag indicating if messages are typically flashed to session (backend). */
   flash_session?: boolean;
}

/**
* 🎯 Defines the expected structure of a standard error response from the UEM backend API.
* Used when handling fetch errors or direct API error responses.
* 📥 @gdprInput Potentially receives user-facing `message`.
*/
export interface ServerErrorResponse {
   /** @param {string} error - The symbolic UEM error code (e.g., "VALIDATION_ERROR"). */
   error: string;

   /** @param {string} message - The user-facing error message (should be pre-localized and PII-safe). */
   message: string;

   /** @param {string} blocking - The determined blocking level for this error instance. */
   blocking: string; // Consider BlockingLevel enum type?

   /** @param {string} display_mode - The determined display mode for this error instance. */
   display_mode: string; // Consider DisplayMode enum type?

   /** @param {any} [details] - Optional additional details (use with caution, might contain sensitive info). */
   details?: any;
}

/**
* 🎯 Defines the structure for additional contextual data passed during error handling.
* Can be extended with application-specific key-value pairs.
* 🛡️ GDPR: Keys and values within this object might contain PII depending on usage.
*        Sanitization should happen before logging/displaying if necessary.
*/
export interface ErrorContext {
   /** @param {any} [key: string] - Any contextual data for the error. */
   [key: string]: any;
}

/**
* 🎯 Defines the contract for all client-side error handlers.
* Enables the Handler/Strategy pattern for processing errors.
*/
export interface ErrorHandler {
   /**
    * 🧠 Determines if this handler should process the given error.
    * @param {string} errorCode - The UEM error code.
    * @param {ErrorConfig | null} config - The resolved error configuration, if available.
    * @returns {boolean} True if this handler should execute `handle`.
    */
   shouldHandle(errorCode: string, config: ErrorConfig | null): boolean;

   /**
    * ✨ Executes the handler's specific logic for the error.
    * 📥 @gdprInput Receives potentially sensitive `message` and `context`.
    * @param {string} errorCode - The UEM error code.
    * @param {string} message - The prepared user-facing message (should be safe).
    * @param {string} displayMode - The determined display mode.
    * @param {string} blocking - The determined blocking level.
    * @param {ErrorContext} context - Additional context (handle with care).
    * @param {Error} [originalError] - The original JavaScript Error object, if available.
    */
   handle(
       errorCode: string,
       message: string, // Assumed user-safe message
       displayMode: string, // Consider DisplayMode enum type?
       blocking: string, // Consider BlockingLevel enum type?
       context: ErrorContext,
       originalError?: Error
   ): void;
}

/**
* 🎯 Defines the structure of the `detail` payload for the `ultraError` CustomEvent.
* This event is dispatched by ErrorManager for observation by other parts of the application.
* 📤 @gdprOutput Transmits error details within the browser client-side. Low risk unless misused.
*/
export interface UltraErrorEventDetail {
    /** @param {string} errorCode - The UEM error code. */
    errorCode: string;

    /** @param {string} message - The prepared user-facing message. */
    message: string;

    /** @param {string} blocking - The determined blocking level. */
    blocking: string; // Consider BlockingLevel enum type?

    /** @param {ErrorContext} context - Additional context data (handle with care). */
    context: ErrorContext;

    /** @param {object} [originalError] - Simplified details from the original JS Error, if available. */
    originalError?: {
        message: string;
        name: string;
        stack?: string; // Stack trace can be sensitive, included optionally.
    };

    /** @param {string} timestamp - ISO 8601 timestamp of when the error was handled client-side. */
    timestamp: string;
}

/**
 * @typedef {CustomEvent<UltraErrorEventDetail>} UltraErrorEvent
 * Type alias for the custom event dispatched by ErrorManager.
 */
export type UltraErrorEvent = CustomEvent<UltraErrorEventDetail>;


/**
* 🧱 Enum defining possible recovery action identifiers.
* Should align with `recovery_action` values in `error-manager.php`.
*/
export enum RecoveryActionType {
   RETRY_UPLOAD = 'retry_upload',
   RETRY_SCAN = 'retry_scan',
   RETRY_PRESIGNED = 'retry_presigned',
   CREATE_TEMP_DIRECTORY = 'create_temp_directory',
   SCHEDULE_CLEANUP = 'schedule_cleanup',
   RETRY_METADATA_SAVE = 'retry_metadata_save'
   // Add others as needed
}

/**
* 🧱 Enum defining error severity levels.
* Should align with keys in `error-manager.php['error_types']`.
*/
export enum ErrorSeverity {
   CRITICAL = 'critical',
   ERROR = 'error',
   WARNING = 'warning',
   NOTICE = 'notice'
   // INFO = 'info' ?
}

/**
* 🧱 Enum defining error blocking levels.
* Should align with keys in `error-manager.php['blocking_levels']`.
*/
export enum BlockingLevel {
   BLOCKING = 'blocking',
   SEMI_BLOCKING = 'semi-blocking',
   NOT_BLOCKING = 'not' // 'not' is slightly awkward, maybe 'non-blocking'? Needs backend consistency.
}

/**
* 🧱 Enum defining UI display modes.
* Should align with `msg_to` values in `error-manager.php`.
*/
export enum DisplayMode {
   DIV = 'div',
   SWEET_ALERT = 'sweet-alert',
   TOAST = 'toast',
   LOG_ONLY = 'log-only'
}