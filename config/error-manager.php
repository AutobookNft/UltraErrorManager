<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Ultra Error Manager Configuration
    |--------------------------------------------------------------------------
    |
    | Defines error types, handlers, default behaviors, and specific error codes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Handlers
    |--------------------------------------------------------------------------
    | Handlers automatically registered. Order can matter for some logic.
    | Assumes handlers have been refactored for DI.
    */
    'default_handlers' => [
        // Order Suggestion: Log first, then notify, then prepare UI/recovery
        \Ultra\ErrorManager\Handlers\LogHandler::class,
        \Ultra\ErrorManager\Handlers\DatabaseLogHandler::class, // Log to DB
        \Ultra\ErrorManager\Handlers\EmailNotificationHandler::class, // Notify Devs
        \Ultra\ErrorManager\Handlers\SlackNotificationHandler::class, // Notify Slack
        \Ultra\ErrorManager\Handlers\UserInterfaceHandler::class, // Prepare UI flash messages
        \Ultra\ErrorManager\Handlers\RecoveryActionHandler::class, // Attempt recovery
        // Simulation handler (conditionally added by Service Provider if not production)
        // \Ultra\ErrorManager\Handlers\ErrorSimulationHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Notification Settings
    |--------------------------------------------------------------------------
    */
    'email_notification' => [
        'enabled' => env('ERROR_EMAIL_NOTIFICATIONS_ENABLED', true),
        'to' => env('ERROR_EMAIL_RECIPIENT', 'devteam@example.com'),
        'from' => [ /* ... */ ],
        'subject_prefix' => env('ERROR_EMAIL_SUBJECT_PREFIX', '[UEM Error] '),

        // --- NUOVE OPZIONI GDPR ---
        'include_ip_address' => env('ERROR_EMAIL_INCLUDE_IP', false),        // Default: NO
        'include_user_agent' => env('ERROR_EMAIL_INCLUDE_UA', false),       // Default: NO
        'include_user_details' => env('ERROR_EMAIL_INCLUDE_USER', false),    // Default: NO (Include ID, Name, Email)
        'include_context' => env('ERROR_EMAIL_INCLUDE_CONTEXT', true),       // Default: YES (ma verrà sanitizzato)
        'include_trace' => env('ERROR_EMAIL_INCLUDE_TRACE', false),         // Default: NO (Le tracce possono essere lunghe/sensibili)
        'context_sensitive_keys' => [ // Lista specifica per email, può differire da DB
            'password', 'secret', 'token', 'auth', 'key', 'credentials', 'authorization',
            'php_auth_user', 'php_auth_pw', 'credit_card', 'creditcard', 'card_number',
            'cvv', 'cvc', 'api_key', 'secret_key', 'access_token', 'refresh_token',
            // Aggiungere chiavi specifiche se necessario
        ],
        'trace_max_lines' => env('ERROR_EMAIL_TRACE_LINES', 30), // Limita lunghezza trace inviata
    ],

     /*
    |--------------------------------------------------------------------------
    | Slack Notification Settings
    |--------------------------------------------------------------------------
    */
     'slack_notification' => [
        'enabled' => env('ERROR_SLACK_NOTIFICATIONS_ENABLED', false),
        'webhook_url' => env('ERROR_SLACK_WEBHOOK_URL'),
        'channel' => env('ERROR_SLACK_CHANNEL', '#error-alerts'),
        'username' => env('ERROR_SLACK_USERNAME', 'UEM Error Bot'),
        'icon_emoji' => env('ERROR_SLACK_ICON', ':boom:'),

        // --- NUOVE OPZIONI GDPR ---
        'include_ip_address' => env('ERROR_SLACK_INCLUDE_IP', false),       // Default: NO
        'include_user_details' => env('ERROR_SLACK_INCLUDE_USER', false),   // Default: NO (Just ID maybe?)
        'include_context' => env('ERROR_SLACK_INCLUDE_CONTEXT', true),      // Default: YES (sanitized)
        'include_trace_snippet' => env('ERROR_SLACK_INCLUDE_TRACE', false), // Default: NO (Trace can be very long for Slack)
        'context_sensitive_keys' => [ // Lista per Slack
            'password', 'secret', 'token', 'auth', 'key', 'credentials', 'authorization',
            'php_auth_user', 'php_auth_pw', 'credit_card', 'creditcard', 'card_number',
            'cvv', 'cvc', 'api_key', 'secret_key', 'access_token', 'refresh_token',
            // Aggiungere chiavi specifiche se necessario
        ],
        'context_max_length' => env('ERROR_SLACK_CONTEXT_LENGTH', 1500), // Limit context length in Slack message
        'trace_max_lines' => env('ERROR_SLACK_TRACE_LINES', 10), // Limit trace lines in Slack message
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration (UEM Specific)
    |--------------------------------------------------------------------------
    | Settings affecting logging handlers (LogHandler, DatabaseLogHandler).
    */
    'logging' => [
         // Note: Main log channel is configured in ULM, not here.
         // 'channel' => env('ERROR_LOG_CHANNEL', 'stack'), // Redundant if using ULM properly
        'detailed_context_in_log' => env('ERROR_LOG_DETAILED_CONTEXT', true), // Affects standard LogHandler context
    ],

     /*
     |--------------------------------------------------------------------------
     | Database Logging Configuration
     |--------------------------------------------------------------------------
     */
     'database_logging' => [
         'enabled' => env('ERROR_DB_LOGGING_ENABLED', true), // Enable DB logging by default
         'include_trace' => env('ERROR_DB_LOG_INCLUDE_TRACE', true), // Log stack traces to DB
         'max_trace_length' => env('ERROR_DB_LOG_MAX_TRACE_LENGTH', 10000), // Max chars for DB trace
        
         /**
         * 🛡️ Sensitive Keys for Context Redaction.
         * Keys listed here (case-insensitive) will have their values
         * replaced with '[REDACTED]' before the context is saved to the database log.
         * Add any application-specific keys containing PII or secrets.
         */
        'sensitive_keys' => [
            // Defaults (from DatabaseLogHandler)
            'password',
            'secret',
            'token',
            'auth',
            'key',
            'credentials',
            'authorization',
            'php_auth_user',
            'php_auth_pw',
            'credit_card',
            'creditcard', // Variations
            'card_number',
            'cvv',
            'cvc',
            'api_key',
            'secret_key',
            'access_token',
            'refresh_token',
            // Aggiungi qui chiavi specifiche di FlorenceEGI se necessario
            // 'wallet_private_key',
            // 'user_personal_identifier',
            // 'financial_details',
        ],
     
    ],


    /*
    |--------------------------------------------------------------------------
    | UI Error Display
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'default_display_mode' => env('ERROR_UI_DEFAULT_DISPLAY', 'sweet-alert'), // 'div', 'sweet-alert', 'toast'
        'show_error_codes' => env('ERROR_UI_SHOW_CODES', false), // Show codes like [E_...] to users?
        'generic_error_message' => 'error-manager::errors.user.generic_error', // Translation key for generic messages
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Type Definitions
    |--------------------------------------------------------------------------
    | Defines behavior associated with error severity levels.
    */
    'error_types' => [
        'critical' => [
            'log_level' => 'critical', // Maps to PSR LogLevel
            'notify_team' => true, // Default: Should Email/Slack handlers trigger?
            'http_status' => 500, // Default HTTP status
        ],
        'error' => [
            'log_level' => 'error',
            'notify_team' => false,
            'http_status' => 400, // Often client errors or recoverable server issues
        ],
        'warning' => [
            'log_level' => 'warning',
            'notify_team' => false,
            'http_status' => 400, // Often user input validation
        ],
        'notice' => [
            'log_level' => 'notice',
            'notify_team' => false,
            'http_status' => 200, // Not typically an "error" status
        ],
        // Consider adding 'info' if needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocking Level Definitions
    |--------------------------------------------------------------------------
    | Defines impact on application flow.
    */
    'blocking_levels' => [
        'blocking' => [
            'terminate_request' => true, // Should middleware stop request propagation? (UEM itself doesn't enforce this directly)
            'clear_session' => false, // Example: Should session be cleared?
        ],
        'semi-blocking' => [
            'terminate_request' => false, // Allows request to potentially complete
            'flash_session' => true, // Should UI handler flash message?
        ],
        'not' => [ // Non-blocking
            'terminate_request' => false,
            'flash_session' => true, // Still might want to inform user
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Error Configuration
    |--------------------------------------------------------------------------
    | Used if 'UNDEFINED_ERROR_CODE' itself is not defined. Should always exist.
    */
    'fallback_error' => [
        'type' => 'critical', // Treat any fallback situation as critical
        'blocking' => 'blocking',
        'dev_message_key' => 'error-manager::errors.dev.fatal_fallback_failure', // Use the fatal key here
        'user_message_key' => 'error-manager::errors.user.fatal_fallback_failure',
        'http_status_code' => 500,
        'devTeam_email_need' => true,
        'msg_to' => 'sweet-alert', // Show prominent alert
        'notify_slack' => true, // Also notify slack if configured
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Definitions (Code => Configuration)
    |--------------------------------------------------------------------------
    */
    'errors' => [

        // ====================================================
        // META / Generic Fallbacks
        // ====================================================
        'UNDEFINED_ERROR_CODE' => [
            'type' => 'critical',
            'blocking' => 'blocking', // Treat undefined code as blocking
            'dev_message_key' => 'error-manager::errors.dev.undefined_error_code',
            'user_message_key' => 'error-manager::errors.user.undefined_error_code',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true, // Notify Slack too
            'msg_to' => 'sweet-alert',
        ],
        'FATAL_FALLBACK_FAILURE' => [ // Only used if fallback_error itself fails
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.fatal_fallback_failure',
            'user_message_key' => 'error-manager::errors.user.fatal_fallback_failure',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
        // FALLBACK_ERROR is defined above in 'fallback_error' key
        'UNEXPECTED_ERROR' => [ // Generic catch-all from middleware mapping
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.unexpected_error',
            'user_message_key' => 'error-manager::errors.user.unexpected_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
        'GENERIC_SERVER_ERROR' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.generic_server_error',
            'user_message_key' => 'error-manager::errors.user.generic_server_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
        'JSON_ERROR' => [
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.json_error',
            'user_message_key' => 'error-manager::errors.user.json_error',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
         'INVALID_INPUT' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_input',
            'user_message_key' => 'error-manager::errors.user.invalid_input',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],

        // ====================================================
        // Authentication & Authorization Errors (Mapped from Middleware)
        // ====================================================
        'AUTHENTICATION_ERROR' => [
            'type' => 'error',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.authentication_error',
            'user_message_key' => 'error-manager::errors.user.authentication_error',
            'http_status_code' => 401,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'sweet-alert', // Or redirect
        ],
        'AUTHORIZATION_ERROR' => [
            'type' => 'error',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.authorization_error',
            'user_message_key' => 'error-manager::errors.user.authorization_error',
            'http_status_code' => 403,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'sweet-alert',
        ],
         'CSRF_TOKEN_MISMATCH' => [
             'type' => 'error',
             'blocking' => 'blocking',
             'dev_message_key' => 'error-manager::errors.dev.csrf_token_mismatch',
             'user_message_key' => 'error-manager::errors.user.csrf_token_mismatch',
             'http_status_code' => 419,
             'devTeam_email_need' => false,
             'notify_slack' => false,
             'msg_to' => 'sweet-alert', // Inform user to refresh
         ],

        // ====================================================
        // Routing & Request Errors (Mapped from Middleware)
        // ====================================================
         'ROUTE_NOT_FOUND' => [
             'type' => 'error',
             'blocking' => 'blocking',
             'dev_message_key' => 'error-manager::errors.dev.route_not_found',
             'user_message_key' => 'error-manager::errors.user.route_not_found',
             'http_status_code' => 404,
             'devTeam_email_need' => false,
             'notify_slack' => false,
             'msg_to' => 'log-only', // Let Laravel handle 404 page
         ],
         'METHOD_NOT_ALLOWED' => [
             'type' => 'error',
             'blocking' => 'blocking',
             'dev_message_key' => 'error-manager::errors.dev.method_not_allowed',
             'user_message_key' => 'error-manager::errors.user.method_not_allowed',
             'http_status_code' => 405,
             'devTeam_email_need' => false,
             'notify_slack' => false,
             'msg_to' => 'log-only', // Let Laravel handle 405 page
         ],
         'TOO_MANY_REQUESTS' => [
            'type' => 'error',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.too_many_requests',
            'user_message_key' => 'error-manager::errors.user.too_many_requests',
            'http_status_code' => 429,
            'devTeam_email_need' => false,
            'notify_slack' => true, // Might indicate an attack or config issue
            'msg_to' => 'sweet-alert',
        ],

        // ====================================================
        // Database / Model Errors (Mapped + Specifics)
        // ====================================================
        'DATABASE_ERROR' => [
             'type' => 'critical',
             'blocking' => 'blocking',
             'dev_message_key' => 'error-manager::errors.dev.database_error',
             'user_message_key' => 'error-manager::errors.user.database_error',
             'http_status_code' => 500,
             'devTeam_email_need' => true,
             'notify_slack' => true,
             'msg_to' => 'sweet-alert',
         ],
         'RECORD_NOT_FOUND' => [
             'type' => 'error', // Or warning depending on context
             'blocking' => 'blocking', // Usually stops the current action
             'dev_message_key' => 'error-manager::errors.dev.record_not_found',
             'user_message_key' => 'error-manager::errors.user.record_not_found',
             'http_status_code' => 404,
             'devTeam_email_need' => false,
             'notify_slack' => false,
             'msg_to' => 'sweet-alert',
         ],
        'ERROR_DURING_CREATE_EGI_RECORD' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_during_create_egi_record',
            'user_message_key' => 'error-manager::errors.user.error_during_create_egi_record',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],

        // ====================================================
        // Validation Errors (Mapped + Specifics)
        // ====================================================
        'VALIDATION_ERROR' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.validation_error',
            'user_message_key' => 'error-manager::errors.user.validation_error',
            'http_status_code' => 422,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div', // Usually shown inline with form fields
        ],
        'INVALID_IMAGE_STRUCTURE' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_image_structure',
            'user_message_key' => 'error-manager::errors.user.invalid_image_structure',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
        'MIME_TYPE_NOT_ALLOWED' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.mime_type_not_allowed',
            'user_message_key' => 'error-manager::errors.user.mime_type_not_allowed',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
        'MAX_FILE_SIZE' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.max_file_size',
            'user_message_key' => 'error-manager::errors.user.max_file_size',
            'http_status_code' => 413, // Payload Too Large
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
        'INVALID_FILE_EXTENSION' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_extension',
            'user_message_key' => 'error-manager::errors.user.invalid_file_extension',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
        'INVALID_FILE_NAME' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_name',
            'user_message_key' => 'error-manager::errors.user.invalid_file_name',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
        'INVALID_FILE_PDF' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_pdf',
            'user_message_key' => 'error-manager::errors.user.invalid_file_pdf',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
         'INVALID_FILE' => [ // More generic file issue?
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file',
            'user_message_key' => 'error-manager::errors.user.invalid_file',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
        'INVALID_FILE_VALIDATION' => [ // Specific validation context
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_validation',
            'user_message_key' => 'error-manager::errors.user.invalid_file_validation',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],

        // ====================================================
        // UUM (Upload) Related Errors (Esistenti - verified/adjusted)
        // ====================================================
        'VIRUS_FOUND' => [
            'type' => 'error', // Changed from warning, this is a security event
            'blocking' => 'blocking', // Stop processing this file
            'dev_message_key' => 'error-manager::errors.dev.virus_found',
            'user_message_key' => 'error-manager::errors.user.virus_found',
            'http_status_code' => 422, // Unprocessable Entity
            'devTeam_email_need' => false, // May become true if frequent/unexpected
            'notify_slack' => true, // Good to know about virus alerts
            'msg_to' => 'sweet-alert',
        ],
        'SCAN_ERROR' => [
            'type' => 'warning', // Scan failed, not necessarily insecure
            'blocking' => 'semi-blocking', // Allow retry potentially
            'dev_message_key' => 'error-manager::errors.dev.scan_error',
            'user_message_key' => 'error-manager::errors.user.scan_error',
            'http_status_code' => 500, // Service unavailable?
            'devTeam_email_need' => true, // If scanner service is down
            'notify_slack' => true,
            'msg_to' => 'div',
            'recovery_action' => 'retry_scan', // Defined recovery
        ],
        'TEMP_FILE_NOT_FOUND' => [
            'type' => 'error', // Changed from warning, indicates logic flaw
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.temp_file_not_found',
            'user_message_key' => 'error-manager::errors.user.temp_file_not_found',
            'http_status_code' => 404,
            'devTeam_email_need' => true, // Investigate why temp file is missing
            'notify_slack' => true,
            'msg_to' => 'div',
        ],
        'FILE_NOT_FOUND' => [ // Generic file not found
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.file_not_found',
            'user_message_key' => 'error-manager::errors.user.file_not_found',
            'http_status_code' => 404,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div',
        ],
        'ERROR_GETTING_PRESIGNED_URL' => [
            'type' => 'error', // Changed from critical, maybe recoverable network issue
            'blocking' => 'semi-blocking', // Allow retry
            'dev_message_key' => 'error-manager::errors.dev.error_getting_presigned_url',
            'user_message_key' => 'error-manager::errors.user.error_getting_presigned_url',
            'http_status_code' => 500,
            'devTeam_email_need' => true, // If storage provider is down
            'notify_slack' => true,
            'msg_to' => 'div',
            'recovery_action' => 'retry_presigned',
        ],
        'ERROR_DURING_FILE_UPLOAD' => [
            'type' => 'error', // Changed from critical, network issues happen
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_during_file_upload',
            'user_message_key' => 'error-manager::errors.user.error_during_file_upload',
            'http_status_code' => 500, // Or maybe client-related? Needs context.
            'devTeam_email_need' => true, // If persistent
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
            'recovery_action' => 'retry_upload',
        ],
        'ERROR_DELETING_LOCAL_TEMP_FILE' => [
            'type' => 'warning', // Changed from critical, cleanup can be retried
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.error_deleting_local_temp_file',
            'user_message_key' => null, // Internal issue
            'http_status_code' => 500,
            'devTeam_email_need' => false, // Unless very frequent
            'notify_slack' => false,
            'msg_to' => 'log-only',
            'recovery_action' => 'schedule_cleanup',
        ],
        'ERROR_DELETING_EXT_TEMP_FILE' => [
            'type' => 'warning',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.error_deleting_ext_temp_file',
            'user_message_key' => null,
            'http_status_code' => 500,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'log-only',
            'recovery_action' => 'schedule_cleanup',
        ],
        'UNABLE_TO_SAVE_BOT_FILE' => [
            'type' => 'critical', // If bot relies on this, it's critical
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.unable_to_save_bot_file',
            'user_message_key' => 'error-manager::errors.user.unable_to_save_bot_file',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'div',
        ],
        'UNABLE_TO_CREATE_DIRECTORY' => [
            'type' => 'critical', // Filesystem permission issue?
            'blocking' => 'blocking', // Uploads likely blocked
            'dev_message_key' => 'error-manager::errors.dev.unable_to_create_directory',
            'user_message_key' => 'error-manager::errors.user.generic_internal_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'log-only',
            'recovery_action' => 'create_temp_directory',
        ],
        'UNABLE_TO_CHANGE_PERMISSIONS' => [
            'type' => 'critical',
            'blocking' => 'not', // May not block immediately but needs fixing
            'dev_message_key' => 'error-manager::errors.dev.unable_to_change_permissions',
            'user_message_key' => 'error-manager::errors.user.generic_internal_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'log-only',
        ],
        'IMPOSSIBLE_SAVE_FILE' => [
            'type' => 'critical', // File saving failed entirely
            'blocking' => 'semi-blocking', // User needs to know
            'dev_message_key' => 'error-manager::errors.dev.impossible_save_file',
            'user_message_key' => 'error-manager::errors.user.impossible_save_file',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
         'ERROR_SAVING_FILE_METADATA' => [
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_saving_file_metadata',
            'user_message_key' => 'error-manager::errors.user.error_saving_file_metadata',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'div',
            'recovery_action' => 'retry_metadata_save',
        ],
        'ACL_SETTING_ERROR' => [
            'type' => 'critical', // Security related
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.acl_setting_error',
            'user_message_key' => 'error-manager::errors.user.acl_setting_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'div',
        ],
        'ERROR_DURING_FILE_NAME_ENCRYPTION' => [
            'type' => 'critical', // Security related
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_during_file_name_encryption',
            'user_message_key' => 'error-manager::errors.user.error_during_file_name_encryption',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'div',
        ],

        // ====================================================
        // UCM (Config) Related Errors (Esistenti - verified/adjusted)
        // ====================================================
        'UCM_DUPLICATE_KEY' => [
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_duplicate_key',
            'user_message_key' => 'error-manager::errors.user.ucm_duplicate_key',
            'http_status_code' => 422, // Unprocessable entity seems appropriate
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'sweet-alert',
        ],
        'UCM_CREATE_FAILED' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_create_failed',
            'user_message_key' => 'error-manager::errors.user.ucm_create_failed',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
        'UCM_UPDATE_FAILED' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_update_failed',
            'user_message_key' => 'error-manager::errors.user.ucm_update_failed',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
        'UCM_NOT_FOUND' => [
            'type' => 'error', // Could be expected if key is optional
            'blocking' => 'not', // Changed to non-blocking, logic should handle null
            'dev_message_key' => 'error-manager::errors.dev.ucm_not_found',
            'user_message_key' => 'error-manager::errors.user.ucm_not_found', // Maybe a generic "setting not found"?
            'http_status_code' => 404,
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'log-only', // Log it, but don't bother user usually
        ],
        'UCM_DELETE_FAILED' => [
            'type' => 'critical',
            'blocking' => 'blocking', // Failed to delete, might leave inconsistent state
            'dev_message_key' => 'error-manager::errors.dev.ucm_delete_failed',
            'user_message_key' => 'error-manager::errors.user.ucm_delete_failed',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
        'UCM_AUDIT_NOT_FOUND' => [
            'type' => 'notice', // Changed from info, less noisy
            'blocking' => 'not', // Non-blocking
            'dev_message_key' => 'error-manager::errors.dev.ucm_audit_not_found',
            'user_message_key' => 'error-manager::errors.user.ucm_audit_not_found',
            'http_status_code' => 404, // Consistent not found
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'div', // Show in context if needed
        ],

        // ====================================================
        // UTM (Translation) Errors (Nuovi - Verified/Adjusted)
        // ====================================================
        'UTM_LOAD_FAILED' => [
            'type' => 'error', // Changed from warning, failure to load lang file is an error
            'blocking' => 'not', // But might fallback to default language
            'dev_message_key' => 'error-manager::errors.dev.utm_load_failed',
            'user_message_key' => null, // Internal issue, user sees fallback lang
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'notify_slack' => true, // Let devs know quickly
            'msg_to' => 'log-only',
        ],
        'UTM_INVALID_LOCALE' => [
            'type' => 'warning', // Invalid locale requested
            'blocking' => 'not', // System likely falls back to default
            'dev_message_key' => 'error-manager::errors.dev.utm_invalid_locale',
            'user_message_key' => null, // User sees default language content
            'http_status_code' => 400, // Bad request potentially (depending on source)
            'devTeam_email_need' => false,
            'notify_slack' => false,
            'msg_to' => 'log-only',
        ],

        // ====================================================
        // UEM Internal Handler Errors (Nuovi - Verified/Adjusted)
        // ====================================================
        'UEM_EMAIL_SEND_FAILED' => [
            'type' => 'critical', // Changed from error - failure to notify IS critical
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.uem_email_send_failed',
            'user_message_key' => null,
            'http_status_code' => 500,
            'devTeam_email_need' => false, // Avoid loop - logged by handler
            'notify_slack' => true, // Try alternative notification
            'msg_to' => 'log-only',
        ],
        'UEM_SLACK_SEND_FAILED' => [
            'type' => 'critical', // Changed from error
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.uem_slack_send_failed',
            'user_message_key' => null,
            'http_status_code' => 500,
            'devTeam_email_need' => true, // Try email if slack failed
            'notify_slack' => false, // Avoid loop
            'msg_to' => 'log-only',
        ],
        'UEM_RECOVERY_ACTION_FAILED' => [
            'type' => 'error', // Changed from warning - recovery failure IS an error
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.uem_recovery_action_failed',
            'user_message_key' => null, // User sees original error message
            'http_status_code' => 500,
            'devTeam_email_need' => true, // Need to know why recovery failed
            'notify_slack' => true,
            'msg_to' => 'log-only',
        ],

        // ====================================================
        // System / Environment Errors (Esistenti - Verified/Adjusted)
        // ====================================================
        'IMAGICK_NOT_AVAILABLE' => [
            'type' => 'critical',
            'blocking' => 'blocking', // If image processing is core
            'dev_message_key' => 'error-manager::errors.dev.imagick_not_available',
            'user_message_key' => 'error-manager::errors.user.imagick_not_available', // Inform user nicely
            'http_status_code' => 500, // Misconfiguration
            'devTeam_email_need' => true,
            'notify_slack' => true,
            'msg_to' => 'sweet-alert',
        ],
        'SERVER_LIMITS_RESTRICTIVE' => [ // Example: PHP memory limit, upload size etc. detected low
            'type' => 'warning',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.server_limits_restrictive', // E.g., "PHP memory_limit is low (:limit)"
            'user_message_key' => null, // Not a user error
            'http_status_code' => 500, // Reflects potential future issue
            'devTeam_email_need' => true, // Ops/Dev team needs to adjust server config
            'notify_slack' => true,
            'msg_to' => 'log-only',
        ],
    ],
];