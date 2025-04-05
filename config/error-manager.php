<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ultra Error Manager Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Ultra Error Manager system.
    | It defines error types, handlers, and default behaviors.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Handlers
    |--------------------------------------------------------------------------
    |
    | Default handlers that will be automatically registered with the ErrorManager.
    | These handlers will process all errors unless their shouldHandle method
    | returns false for specific error types.
    |
    */
    'default_handlers' => [
        Ultra\ErrorManager\Handlers\LogHandler::class,
        Ultra\ErrorManager\Handlers\EmailNotificationHandler::class,
        Ultra\ErrorManager\Handlers\UserInterfaceHandler::class,
        Ultra\ErrorManager\Handlers\RecoveryActionHandler::class,
        Ultra\ErrorManager\Handlers\SlackNotificationHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for email notifications sent to the development team
    | when critical errors occur.
    |
    */
    'email_notification' => [
        'enabled' => env('ERROR_EMAIL_NOTIFICATIONS_ENABLED', true),
        'to' => env('ERROR_EMAIL_RECIPIENT', 'devteam@example.com'),
        'from' => [
            'address' => env('ERROR_EMAIL_FROM_ADDRESS', 'noreply@example.com'),
            'name' => env('ERROR_EMAIL_FROM_NAME', 'Error Monitoring System'),
        ],
        'subject_prefix' => env('ERROR_EMAIL_SUBJECT_PREFIX', '[ERROR] '),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how errors are logged in the system.
    |
    */
    'logging' => [
        'channel' => env('ERROR_LOG_CHANNEL', 'stack'),
        'detailed_context' => env('ERROR_LOG_DETAILED_CONTEXT', true),
        'include_trace' => env('ERROR_LOG_INCLUDE_TRACE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Error Display
    |--------------------------------------------------------------------------
    |
    | Configure how errors are displayed to users in the UI.
    |
    */
    'ui' => [
        'default_display_mode' => 'div', // 'div', 'sweet-alert', 'toast', etc.
        'show_error_codes' => env('SHOW_ERROR_CODES_TO_USERS', false),
        'generic_error_message' => 'error-manager::messages.generic_error',
    ],

    'slack_notification' => [
        'enabled' => env('ERROR_SLACK_NOTIFICATIONS_ENABLED', false),
        'webhook_url' => env('ERROR_SLACK_WEBHOOK_URL'),
        'channel' => env('ERROR_SLACK_CHANNEL', '#errors'),
        'username' => env('ERROR_SLACK_USERNAME', 'Error Bot'),
        'icon_emoji' => env('ERROR_SLACK_ICON', ':warning:'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Type Definitions
    |--------------------------------------------------------------------------
    |
    | Define the different types of errors that can be handled by the system.
    | - critical: Severe errors that require immediate attention
    | - error: Standard errors that indicate a failure but not critical
    | - warning: Less severe problems that don't necessarily interrupt operation
    | - notice: Informational messages about potential issues
    |
    */
    'error_types' => [
        'critical' => [
            'log_level' => 'critical',
            'notify_team' => true,
            'http_status' => 500,
        ],
        'error' => [
            'log_level' => 'error',
            'notify_team' => false,
            'http_status' => 400,
        ],
        'warning' => [
            'log_level' => 'warning',
            'notify_team' => false,
            'http_status' => 400,
        ],
        'notice' => [
            'log_level' => 'notice',
            'notify_team' => false,
            'http_status' => 200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocking Level Definitions
    |--------------------------------------------------------------------------
    |
    | Define how different blocking levels affect the application flow.
    | - blocking: Completely stops the process, requiring a restart
    | - semi-blocking: Blocks current operation but allows others to continue
    | - not: Does not block any operations
    |
    */
    'blocking_levels' => [
        'blocking' => [
            'terminate_request' => true,
            'clear_session' => false,
        ],
        'semi-blocking' => [
            'terminate_request' => false,
            'flash_session' => true,
        ],
        'not' => [
            'terminate_request' => false,
            'flash_session' => true,
        ],
    ],


    'fallback_error' => [
        'type' => 'error',
        'blocking' => 'blocking',
        'dev_message_key' => 'error-manager::errors.dev.fallback_error',
        'user_message_key' => 'error-manager::errors.user.fallback_error',
        'http_status_code' => 500,
        'msg_to' => 'div',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Definitions
    |--------------------------------------------------------------------------
    |
    | Define all possible errors in the system with their configuration.
    | Each error has:
    | - type: The error type (critical, error, warning, notice)
    | - blocking: Blocking level (blocking, semi-blocking, not)
    | - dev_message_key: Translation key for developer message
    | - user_message_key: Translation key for user-facing message
    | - http_status_code: HTTP status code to return for this error
    | - devTeam_email_need: Whether to notify the dev team via email
    | - msg_to: Where to display the error (div, sweet-alert, etc.)
    | - recovery_action: Optional action to attempt automatic recovery
    |
    */
    'errors' => [
        'UNDEFINED_ERROR_CODE' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.undefined_error_code',
            'user_message_key' => 'error-manager::errors.user.undefined_error_code',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],
        
        // Authentication and Authorization Errors (100-199)
        'AUTHENTICATION_ERROR' => [
            'type' => 'error',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.authentication_error',
            'user_message_key' => 'error-manager::errors.user.authentication_error',
            'http_status_code' => 401,
            'devTeam_email_need' => false,
            'msg_to' => 'sweet-alert',
        ],

        // File Validation Errors (200-299)
        'INVALID_IMAGE_STRUCTURE' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_image_structure',
            'user_message_key' => 'error-manager::errors.user.invalid_image_structure',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],
        'MIME_TYPE_NOT_ALLOWED' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.mime_type_not_allowed',
            'user_message_key' => 'error-manager::errors.user.mime_type_not_allowed',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],
        'MAX_FILE_SIZE' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.max_file_size',
            'user_message_key' => 'error-manager::errors.user.max_file_size',
            'http_status_code' => 413,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],
        'INVALID_FILE_EXTENSION' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_extension',
            'user_message_key' => 'error-manager::errors.user.invalid_file_extension',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],
        'INVALID_FILE_NAME' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_name',
            'user_message_key' => 'error-manager::errors.user.invalid_file_name',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],
        'INVALID_FILE_PDF' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_pdf',
            'user_message_key' => 'error-manager::errors.user.invalid_file_pdf',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],

        // Virus and Security Related Errors (300-399)
        'VIRUS_FOUND' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.virus_found',
            'user_message_key' => 'error-manager::errors.user.virus_found',
            'http_status_code' => 422,
            'devTeam_email_need' => false,
            'msg_to' => 'sweet-alert',
        ],
        'SCAN_ERROR' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.scan_error',
            'user_message_key' => 'error-manager::errors.user.scan_error',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
            'recovery_action' => 'retry_scan',
        ],

        // File Storage and IO Errors (400-499)
        'TEMP_FILE_NOT_FOUND' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.temp_file_not_found',
            'user_message_key' => 'error-manager::errors.user.temp_file_not_found',
            'http_status_code' => 404,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],
        'FILE_NOT_FOUND' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.file_not_found',
            'user_message_key' => 'error-manager::errors.user.file_not_found',
            'http_status_code' => 404,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],
        'ERROR_GETTING_PRESIGNED_URL' => [
            'type' => 'critical',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.error_getting_presigned_url',
            'user_message_key' => 'error-manager::errors.user.error_getting_presigned_url',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'div',
            'recovery_action' => 'retry_presigned',
        ],
        'ERROR_DURING_FILE_UPLOAD' => [
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_during_file_upload',
            'user_message_key' => 'error-manager::errors.user.error_during_file_upload',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
            'recovery_action' => 'retry_upload',
        ],
        'ERROR_DELETING_LOCAL_TEMP_FILE' => [
            'type' => 'critical',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.error_deleting_local_temp_file',
            'user_message_key' => 'error-manager::errors.user.generic_internal_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'log-only',
            'recovery_action' => 'schedule_cleanup',
        ],
        'ERROR_DELETING_EXT_TEMP_FILE' => [
            'type' => 'critical',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.error_deleting_ext_temp_file',
            'user_message_key' => 'error-manager::errors.user.generic_internal_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'log-only',
            'recovery_action' => 'schedule_cleanup',
        ],
        'UNABLE_TO_SAVE_BOT_FILE' => [
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.unable_to_save_bot_file',
            'user_message_key' => 'error-manager::errors.user.unable_to_save_bot_file',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'div',
        ],
        'UNABLE_TO_CREATE_DIRECTORY' => [
            'type' => 'critical',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.unable_to_create_directory',
            'user_message_key' => 'error-manager::errors.user.generic_internal_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'log-only',
            'recovery_action' => 'create_temp_directory',
        ],
        'UNABLE_TO_CHANGE_PERMISSIONS' => [
            'type' => 'critical',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.unable_to_change_permissions',
            'user_message_key' => 'error-manager::errors.user.generic_internal_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'log-only',
        ],
        'IMPOSSIBLE_SAVE_FILE' => [
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.impossible_save_file',
            'user_message_key' => 'error-manager::errors.user.impossible_save_file',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],

        // Database and Record Management Errors (500-599)
        'ERROR_DURING_CREATE_EGI_RECORD' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_during_create_egi_record',
            'user_message_key' => 'error-manager::errors.user.error_during_create_egi_record',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],

        // Security and Encryption Errors (600-699)
        'ERROR_DURING_FILE_NAME_ENCRYPTION' => [
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_during_file_name_encryption',
            'user_message_key' => 'error-manager::errors.user.error_during_file_name_encryption',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'div',
        ],
        'ACL_SETTING_ERROR' => [
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.acl_setting_error',
            'user_message_key' => 'error-manager::errors.user.acl_setting_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'div',
        ],

        // System and Environment Errors (700-799)
        'IMAGICK_NOT_AVAILABLE' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.imagick_not_available',
            'user_message_key' => 'error-manager::errors.user.imagick_not_available',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],

        // Generic Error Categories (900-999)
        'UNEXPECTED_ERROR' => [
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.unexpected_error',
            'user_message_key' => 'error-manager::errors.user.unexpected_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],
        'GENERIC_SERVER_ERROR' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.generic_server_error',
            'user_message_key' => 'error-manager::errors.user.generic_server_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],
        'JSON_ERROR' => [
            'type' => 'critical',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.json_error',
            'user_message_key' => 'error-manager::errors.user.json_error',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'div',
        ],
        'INVALID_FILE' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file',
            'user_message_key' => 'error-manager::errors.user.invalid_file',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],

        'INVALID_FILE_VALIDATION' => [
            'type' => 'warning',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_file_validation',
            'user_message_key' => 'error-manager::errors.user.invalid_file_validation',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'div',
        ],

        'ERROR_SAVING_FILE_METADATA' => [
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.error_saving_file_metadata',
            'user_message_key' => 'error-manager::errors.user.error_saving_file_metadata',
            'http_status_code' => 500,
            'devTeam_email_need' => true, // Questo merita un'email al team
            'msg_to' => 'div',
            'recovery_action' => 'retry_metadata_save', // Azione di recupero che potremmo implementare
        ],

        'SERVER_LIMITS_RESTRICTIVE' => [
            'type' => 'warning',
            'blocking' => 'not',
            'dev_message_key' => 'error-manager::errors.dev.server_limits_restrictive',
            'user_message_key' => 'error-manager::errors.user.server_limits_restrictive',
            'http_status_code' => 200, // Non è un errore per l'utente
            'devTeam_email_need' => true, // Invia email al team di sviluppo
            'msg_to' => 'log-only', // Non mostrare all'utente
        ],

        'INVALID_INPUT' => [
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.invalid_input',
            'user_message_key' => 'error-manager::errors.user.invalid_input',
            'http_status_code' => 400,
            'devTeam_email_need' => false,
            'msg_to' => 'sweet-alert',
        ],

        // Errori specifici di UCM (aggiunti in precedenza)
        'UCM_DUPLICATE_KEY' => [
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_duplicate_key',
            'user_message_key' => 'error-manager::errors.user.ucm_duplicate_key',
            'http_status_code' => 422,
            'devTeam_email_need' => false,
            'msg_to' => 'sweet-alert',
        ],
        'UCM_CREATE_FAILED' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_create_failed',
            'user_message_key' => 'error-manager::errors.user.ucm_create_failed',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],
        'UCM_UPDATE_FAILED' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_update_failed',
            'user_message_key' => 'error-manager::errors.user.ucm_update_failed',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],
        'UCM_NOT_FOUND' => [
            'type' => 'error',
            'blocking' => 'semi-blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_not_found',
            'user_message_key' => 'error-manager::errors.user.ucm_not_found',
            'http_status_code' => 404,
            'devTeam_email_need' => false,
            'msg_to' => 'sweet-alert',
        ],

        'UCM_DELETE_FAILED' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_delete_failed',
            'user_message_key' => 'error-manager::errors.user.ucm_delete_failed',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],

        'FATAL_FALLBACK_FAILURE' => [
            'type' => 'critical',
            'blocking' => 'blocking',
            'dev_message_key' => 'error-manager::errors.dev.fatal_fallback_failure',
            'user_message_key' => 'error-manager::errors.user.fatal_fallback_failure',
            'http_status_code' => 500,
            'devTeam_email_need' => true,
            'msg_to' => 'sweet-alert',
        ],

        'UCM_AUDIT_NOT_FOUND' => [
            'type' => 'info',
            'blocking' => 'non-blocking',
            'dev_message_key' => 'error-manager::errors.dev.ucm_audit_not_found',
            'user_message_key' => 'error-manager::errors.user.ucm_audit_not_found',
            'http_status_code' => 404,
            'msg_to' => 'div',
        ],

    ],
];
