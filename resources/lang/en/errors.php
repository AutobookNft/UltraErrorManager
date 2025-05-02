<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error Messages - English
    |--------------------------------------------------------------------------
    */

    'dev' => [
        // == Existing Entries ==
        'authentication_error' => 'Unauthenticated access attempt.',
        'ucm_delete_failed' => 'Failed to delete configuration with key :key: :message',
        'undefined_error_code' => 'Undefined error code encountered: :errorCode. Original code was [:_original_code].',
        'invalid_input' => 'Invalid input provided for parameter :param.',
        'invalid_image_structure' => 'The structure of the image file is invalid.',
        'mime_type_not_allowed' => 'The MIME type of the file (:mime) is not allowed.',
        'max_file_size' => 'The file size (:size) exceeds the maximum allowed size (:max_size).',
        'invalid_file_extension' => 'The file has an invalid extension (:extension).',
        'invalid_file_name' => 'Invalid file name received during upload process: :filename.',
        'invalid_file_pdf' => 'The PDF file provided is invalid or corrupted.',
        'virus_found' => 'A virus was detected in the file: :filename.',
        'scan_error' => 'An error occurred during the virus scan for file: :filename.',
        'temp_file_not_found' => 'Temporary file not found at path: :path.',
        'file_not_found' => 'The requested file was not found: :path.',
        'error_getting_presigned_url' => 'An error occurred while retrieving the presigned URL for :object.',
        'error_during_file_upload' => 'An error occurred during the file upload process for :filename.',
        'error_deleting_local_temp_file' => 'Failed to delete the local temporary file: :path.',
        'error_deleting_ext_temp_file' => 'Failed to delete the external temporary file: :path.',
        'unable_to_save_bot_file' => 'Unable to save the file for the bot: :filename.',
        'unable_to_create_directory' => 'Failed to create directory for file upload: :directory.',
        'unable_to_change_permissions' => 'Failed to change permissions for file/directory: :path.',
        'impossible_save_file' => 'It was impossible to save the file: :filename to disk :disk.',
        'error_during_create_egi_record' => 'An error occurred while creating the EGI record in the database.',
        'error_during_file_name_encryption' => 'An error occurred during the file name encryption process.',
        'acl_setting_error' => 'An error occurred while setting the ACL (:acl) for object :object.',
        'imagick_not_available' => 'The Imagick PHP extension is not available or configured correctly.',
        'unexpected_error' => 'An unexpected error occurred in the system. Check logs for details.',
        'generic_server_error' => 'A generic server error occurred. Details: :details',
        'json_error' => 'JSON processing error. Type: :type, Message: :message',
        'fallback_error' => 'An error occurred but no specific error configuration was found for code [:_original_code].',
        'fatal_fallback_failure' => 'FATAL: Fallback configuration missing or invalid. System cannot respond.',
        'ucm_audit_not_found' => 'No audit records found for the given configuration ID: :id.',
        'ucm_duplicate_key' => 'Attempted to create a configuration with a duplicate key: :key.',
        'ucm_create_failed' => 'Failed to create configuration entry: :key. Reason: :reason',
        'ucm_update_failed' => 'Failed to update configuration entry: :key. Reason: :reason',
        'ucm_not_found' => 'Configuration key not found: :key.',
        'invalid_file' => 'Invalid file provided: :reason',
        'invalid_file_validation' => 'File validation failed for field :field. Reason: :reason',
        'error_saving_file_metadata' => 'Failed to save metadata for file ID :file_id. Reason: :reason',
        'server_limits_restrictive' => 'Server limits might be too restrictive. Check :limit_name (:limit_value).',
        'egi_auth_required' => 'Authentication required for EGI upload.',
        'egi_file_input_error' => "Invalid or missing 'file' input. Upload error code: :code",
        'egi_validation_failed' => 'EGI metadata validation failed. Check validation errors in context.',
        'egi_collection_init_error' => 'Critical error initializing default collection for user :user_id.',
        'egi_crypto_error' => 'Failed to encrypt filename: :filename',
        'egi_db_error' => 'Database error processing EGI :egi_id for collection :collection_id.',
        'egi_storage_critical_failure' => 'Critical failure saving EGI :egi_id file to disk(s): :disks',
        'egi_storage_config_error' => "'local' storage disk required for fallback is not configured.",
        'egi_unexpected_error' => 'Unexpected error during EGI processing for file :original_filename.',

        // == New Entries ==
        'authorization_error' => 'Authorization denied for the requested action: :action.',
        'csrf_token_mismatch' => 'CSRF token mismatch detected.',
        'route_not_found' => 'The requested route or resource was not found: :url.',
        'method_not_allowed' => 'HTTP method :method not allowed for this route: :url.',
        'too_many_requests' => 'Too many requests hitting the rate limiter.',
        'database_error' => 'A database query or connection error occurred. Details: :details',
        'record_not_found' => 'The requested database record was not found (Model: :model, ID: :id).',
        'validation_error' => 'Input validation failed. Check context for specific errors.', // Generic dev message
        'utm_load_failed' => 'Failed to load translation file: :file for locale :locale.',
        'utm_invalid_locale' => 'Attempted to use an invalid or unsupported locale: :locale.',
        'uem_email_send_failed' => 'EmailNotificationHandler failed to send notification for :errorCode. Reason: :reason',
        'uem_slack_send_failed' => 'SlackNotificationHandler failed to send notification for :errorCode. Reason: :reason',
        'uem_recovery_action_failed' => 'Recovery action :action failed for error :errorCode. Reason: :reason',

        'role_user_not_found' => 'User ID :user_id not found during role operation for role ":role"',
        'role_assignment_failed' => 'Failed to assign role ":role" to user :user_id: :error_message',
        'role_check_failed' => 'Error checking if user :user_id has role ":role": :error_message',
        'role_users_retrieval_failed' => 'Error retrieving users with role ":role": :error_message',
        'role_not_found' => 'Role ":role" not found during user permission operation',

        'wallet_creation_failed' => 'Failed to create wallet for collection :collection_id, user :user_id: :error_message',
        'wallet_quota_check_error' => 'Error checking wallet quota for user :user_id, collection :collection_id: :error_message',
        'wallet_insufficient_quota' => 'User :user_id has insufficient quota for collection :collection_id. Required: mint=:required_mint_quota, rebind=:required_rebind_quota. Available: mint=:current_mint_quota, rebind=:current_rebind_quota.',
        'wallet_address_invalid' => 'Invalid wallet address format provided for user :user_id: :wallet_address',
        'wallet_not_found' => 'Wallet not found for user :user_id and collection :collection_id',
        'wallet_already_exists' => 'Wallet already exists for user :user_id and collection :collection_id with ID :wallet_id',
    ],

    'user' => [
        // == Existing Entries ==
        'authentication_error' => 'You are not authorized to perform this operation.',
        'ucm_delete_failed' => 'An error occurred while deleting the configuration. Please try again later.',
        'undefined_error_code' => 'An unexpected error occurred. Please contact support if the issue persists. [Ref: UNDEFINED]',
        'invalid_input' => 'The provided value for :param is invalid. Please check your input and try again.',
        'invalid_image_structure' => 'The image you uploaded appears to be invalid. Please try a different image.',
        'mime_type_not_allowed' => 'The type of file you uploaded is not supported. Allowed types are: :allowed_types.',
        'max_file_size' => 'The file is too large. The maximum allowed size is :max_size.',
        'invalid_file_extension' => 'The file extension is not supported. Allowed extensions are: :allowed_extensions.',
        'invalid_file_name' => 'The file name contains invalid characters. Please use only letters, numbers, spaces, hyphens, and underscores.',
        'invalid_file_pdf' => 'The uploaded PDF is invalid or might be corrupted. Please try again.',
        'virus_found' => 'The file ":fileName" contains potential threats and has been blocked for your security.',
        'scan_error' => 'We could not verify the security of the file at this time. Please try again later.',
        'temp_file_not_found' => 'There was a temporary issue processing your file :file. Please try again.',
        'file_not_found' => 'The requested file could not be found.',
        'error_getting_presigned_url' => 'There was a temporary issue preparing the file upload. Please try again.',
        'error_during_file_upload' => 'An error occurred while uploading your file. Please try again or contact support if the problem persists.',
        'generic_internal_error' => 'An internal error has occurred. Our technical team has been notified and is working on it.',
        'unable_to_save_bot_file' => 'We were unable to save the generated file at this time. Please try again later.',
        'impossible_save_file' => 'We were unable to save your file due to a system error. Please try again or contact support.',
        'error_during_create_egi_record' => 'An error occurred while saving your information. Our technical team has been notified.',
        'error_during_file_name_encryption' => 'A security error occurred while processing your file. Please try again.',
        'acl_setting_error' => 'We could not set the correct permissions for your file. Please try again or contact support.',
        'imagick_not_available' => 'The system is currently unable to process images. Please contact the administrator if this issue persists.',
        'unexpected_error' => 'An unexpected error has occurred. Our technical team has been notified. Please try again later. [Ref: UNEXPECTED]',
        'generic_server_error' => 'A server error has occurred. Please try again later or contact support if the problem continues. [Ref: SERVER]',
        'json_error' => 'A data processing error occurred. Please check your input or try again later. [Ref: JSON]',
        'fallback_error' => 'An unexpected system issue occurred. Please try again later or contact support. [Ref: FALLBACK]',
        'fatal_fallback_failure' => 'A critical system error occurred. Please contact support immediately. [Ref: FATAL]',
        'ucm_audit_not_found' => 'No history information is available for this item.',
        'ucm_duplicate_key' => 'This configuration setting already exists.',
        'ucm_create_failed' => 'Failed to save the new configuration setting. Please try again.',
        'ucm_update_failed' => 'Failed to update the configuration setting. Please try again.',
        'ucm_not_found' => 'The requested configuration setting was not found.',
        'invalid_file' => 'The file provided is invalid. Please check the file and try again.',
        'invalid_file_validation' => 'Please check the file in the :field field. It did not pass validation.',
        'error_saving_file_metadata' => 'An error occurred while saving file details. Please try the upload again.',
        'server_limits_restrictive' => 'The server configuration may prevent this operation. Please contact support if this persists.',
        'egi_auth_required' => 'Please log in to upload an EGI.',
        'egi_file_input_error' => 'Please select a valid file to upload.',
        'egi_validation_failed' => 'Please correct the highlighted fields in the form.',
        'egi_collection_init_error' => 'Could not prepare your collection. Please contact support if the problem persists.',
        'egi_storage_failure' => 'Failed to securely store the EGI file. Please try again or contact support.',
        'egi_unexpected_error' => 'An unexpected error occurred while processing your EGI. Please try again later.',

        // == New Entries ==
        'authorization_error' => 'You do not have permission to perform this action.',
        'csrf_token_mismatch' => 'Your session has expired or is invalid. Please refresh the page and try again.',
        'route_not_found' => 'The page or resource you requested could not be found.',
        'method_not_allowed' => 'The action you tried to perform is not allowed on this resource.',
        'too_many_requests' => 'You are performing actions too quickly. Please wait a moment and try again.',
        'database_error' => 'A database error occurred. Please try again later or contact support. [Ref: DB]',
        'record_not_found' => 'The item you requested could not be found.',
        'validation_error' => 'Please correct the errors highlighted in the form and try again.', // Generic user message
        'utm_load_failed' => 'The system encountered an issue loading language settings. Functionality may be limited.', // Generic internal error for user
        'utm_invalid_locale' => 'The requested language setting is not available.', // Slightly more specific internal issue
        // Internal UEM failures below generally shouldn't have specific user messages, map to generic ones if needed.
        'uem_email_send_failed' => null, // Use generic_internal_error
        'uem_slack_send_failed' => null, // Use generic_internal_error
        'uem_recovery_action_failed' => null, // Use generic_internal_error
        
        'role_user_not_found' => 'The specified user could not be found.',
        'role_assignment_failed' => 'There was a problem updating user permissions. Please try again or contact support.',
        'role_users_retrieval_failed' => 'Unable to load user list. Please try again later.',
        'role_not_found' => 'The requested permission level is not available in the system.',

        'wallet_creation_failed' => 'We encountered a problem setting up the wallet for this collection. Our team has been notified and will resolve this issue.',
        'wallet_insufficient_quota' => 'You do not have sufficient royalty quota available for this operation. Please adjust your royalty values and try again.',
        'wallet_address_invalid' => 'The wallet address provided is not valid. Please check the format and try again.',
        'wallet_not_found' => 'The requested wallet could not be found. Please verify your information and try again.',
        'wallet_already_exists' => 'A wallet is already configured for this collection. Please use the existing wallet or contact support for assistance.',
    ],

    // Generic message (used by UserInterfaceHandler if no specific message found)
    'generic_error' => 'An error has occurred. Please try again later or contact support.',
];