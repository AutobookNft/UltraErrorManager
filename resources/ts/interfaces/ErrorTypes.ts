/**
* /home/fabio/sandbox/UltraUploadSandbox/packages/ultra/errormanager/resources/ts/interfaces/ErrorTypes.ts
*
* Ultra Error Manager - Error Type Definitions
*
* This file defines the TypeScript interfaces used throughout the Ultra Error Manager system.
* It provides type safety and documentation for error handling components and ensures
* consistency across the client-side error management infrastructure.
*
* The interfaces defined here correspond to the server-side error configurations and
* provide a strongly-typed foundation for error handling on the client.
*
* @module UltraErrorManager/Interfaces
* @version 1.0.0
*/

/**
* Defines the structure of an error configuration
* This mirrors the server-side error configuration structure
*/
export interface ErrorConfig {
    /** The error type (critical, error, warning, notice) */
    type: string;

    /** The blocking level (blocking, semi-blocking, not) */
    blocking: string;

    /** Translation key for developer message */
    dev_message_key?: string;

    /** Translation key for user-facing message */
    user_message_key?: string;

    /** HTTP status code associated with this error */
    http_status_code: number;

    /** Whether the development team should be notified */
    devTeam_email_need: boolean;

    /** How to display the error (div, sweet-alert, toast, log-only) */
    msg_to: string;

    /** Optional action to attempt automatic recovery */
    recovery_action?: string;

    /**  */
    message?: string;

    /**  */
    dev_message?: string;
 }

 /**
 * Defines the structure of an error type configuration
 * Corresponds to the server-side error_types configuration
 */
 export interface ErrorTypeConfig {
    /** The log level to use for this error type */
    log_level: string;

    /** Whether to notify the team for errors of this type */
    notify_team: boolean;

    /** The default HTTP status for this error type */
    http_status: number;
 }

 /**
 * Defines the structure of a blocking level configuration
 * Corresponds to the server-side blocking_levels configuration
 */
 export interface BlockingLevelConfig {
    /** Whether to terminate the current request/operation */
    terminate_request: boolean;

    /** Whether to clear the session */
    clear_session?: boolean;

    /** Whether to flash the error message to the session */
    flash_session?: boolean;
 }

 /**
 * Interface for server error responses
 * Defines the expected structure of error responses from the server
 */
 export interface ServerErrorResponse {
    /** The error code (e.g., "VALIDATION_ERROR") */
    error: string;

    /** The user-facing error message */
    message: string;

    /** The blocking level of the error */
    blocking: string;

    /** How the error should be displayed to the user */
    display_mode: string;

    /** Additional error details */
    details?: any;
 }

 /**
 * Context data for an error
 * Used to provide additional information about the error
 */
 export interface ErrorContext {
    /** Any contextual data for the error */
    [key: string]: any;
 }

 /**
 * Interface that all error handlers must implement
 * Follows the Handler pattern for extensible error processing
 */
 export interface ErrorHandler {
    /**
     * Determines if this handler should process the given error
     *
     * @param {string} errorCode - The error code
     * @param {ErrorConfig|null} config - The error configuration if available
     * @returns {boolean} True if this handler should process the error
     */
    shouldHandle(errorCode: string, config: ErrorConfig | null): boolean;

    /**
     * Handles the error
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
    ): void;
 }

 /**
 * Interface for error events dispatched by the ErrorManager
 */
 export interface UltraErrorEvent extends CustomEvent {
    detail: {
        /** The error code */
        errorCode: string;

        /** The error message */
        message: string;

        /** The blocking level */
        blocking: string;

        /** Additional context */
        context: ErrorContext;

        /** The original error if available */
        originalError?: {
            message: string;
            name: string;
            stack?: string;
        };

        /** Timestamp of when the error occurred */
        timestamp: string;
    };
 }

 /**
 * Types of recovery actions that can be attempted
 */
 export enum RecoveryActionType {
    RETRY_UPLOAD = 'retry_upload',
    RETRY_SCAN = 'retry_scan',
    RETRY_PRESIGNED = 'retry_presigned',
    CREATE_TEMP_DIRECTORY = 'create_temp_directory',
    SCHEDULE_CLEANUP = 'schedule_cleanup',
    RETRY_METADATA_SAVE = 'retry_metadata_save'
 }

 /**
 * Error severity levels
 */
 export enum ErrorSeverity {
    CRITICAL = 'critical',
    ERROR = 'error',
    WARNING = 'warning',
    NOTICE = 'notice'
 }

 /**
 * Error blocking levels
 */
 export enum BlockingLevel {
    BLOCKING = 'blocking',
    SEMI_BLOCKING = 'semi-blocking',
    NOT_BLOCKING = 'not'
 }

 /**
 * Error display modes
 */
 export enum DisplayMode {
    DIV = 'div',
    SWEET_ALERT = 'sweet-alert',
    TOAST = 'toast',
    LOG_ONLY = 'log-only'
 }
