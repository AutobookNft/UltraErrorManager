<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error Messages - English
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for error messages displayed to the user
    | and to developers. You are free to modify these language lines according to your
    | application's requirements.
    |
    */

    'dev' => [
        // Authentication and Authorization
        'authentication_error' => 'Unauthenticated access attempt.',

        // File Validation
        'invalid_image_structure' => 'The structure of the image file is invalid.',
        'mime_type_not_allowed' => 'The MIME type of the file is not allowed.',
        'max_file_size' => 'The file exceeds the maximum allowed size.',
        'invalid_file_extension' => 'The file has an invalid extension.',
        'invalid_file_name' => 'Invalid file name received during upload process.',
        'invalid_file_pdf' => 'The PDF file is invalid.',

        // Virus and Security
        'virus_found' => 'A virus was detected in the file.',
        'scan_error' => 'An error occurred during the virus scan.',

        // File Storage and IO
        'temp_file_not_found' => 'Temporary file not found.',
        'file_not_found' => 'The requested file was not found.',
        'error_getting_presigned_url' => 'An error occurred while retrieving the presigned URL.',
        'error_during_file_upload' => 'An error occurred during the file upload process.',
        'error_deleting_local_temp_file' => 'Failed to delete the local temporary file.',
        'error_deleting_ext_temp_file' => 'Failed to delete the external temporary file.',
        'unable_to_save_bot_file' => 'Unable to save the file for the bot.',
        'unable_to_create_directory' => 'Failed to create directory for file upload.',
        'unable_to_change_permissions' => 'Failed to change file permissions.',
        'impossible_save_file' => 'It was impossible to save the file.',

        // Database and Record Management
        'error_during_create_egi_record' => 'An error occurred while creating the EGI record in the database.',

        // Security and Encryption
        'error_during_file_name_encryption' => 'An error occurred during the file name encryption process.',
        'acl_setting_error' => 'An error occurred while setting the ACL.',

        // System and Environment
        'imagick_not_available' => 'The Imagick extension is not available.',

        // Generic Error Categories
        'unexpected_error' => 'Unexpected error in the system.',
        'generic_server_error' => 'A generic server error occurred.',
        'json_error' => 'JSON error in the dispatcher.',
    ],

    'user' => [
        // Authentication and Authorization
        'authentication_error' => 'You are not authorized to perform this operation.',

        // File Validation
        'invalid_image_structure' => 'The image you uploaded is invalid. Please try with another image.',
        'mime_type_not_allowed' => 'The file type you uploaded is not supported. Allowed types are: :allowed_types.',
        'max_file_size' => 'The file is too large. The maximum allowed size is :max_size MB.',
        'invalid_file_extension' => 'The file extension is not supported. Allowed extensions are: :allowed_extensions.',
        'invalid_file_name' => 'The file name contains invalid characters. Use only letters, numbers, spaces, hyphens, and underscores.',
        'invalid_file_pdf' => 'The uploaded PDF is invalid or might be corrupted.',

        // Virus and Security
        'virus_found' => 'The file ":fileName" contains threats and has been blocked for your security.',
        'scan_error' => 'We could not verify the security of the file. Please try again later.',

        // File Storage and IO
        'temp_file_not_found' => 'There was an issue with the file :file',
        'file_not_found' => 'The requested file was not found.',
        'error_getting_presigned_url' => 'There was an issue preparing for upload. Please try again later.',
        'error_during_file_upload' => 'An error occurred during the upload. Please try again or contact support if the problem persists.',
        'generic_internal_error' => 'An internal error has occurred. The technical team has been notified.',
        'unable_to_save_bot_file' => 'We were unable to save the file. Please try again later.',
        'impossible_save_file' => 'Unable to save the file. Please try again or contact support.',

        // Database and Record Management
        'error_during_create_egi_record' => 'An error occurred during database saving. The technical team has been notified.',

        // Security and Encryption
        'error_during_file_name_encryption' => 'A security error occurred. Please try again later.',
        'acl_setting_error' => 'We could not set the correct permissions on the file. Please try again or contact support.',

        // System and Environment
        'imagick_not_available' => 'The system is not configured correctly to process images. Please contact the administrator.',

        // Generic Error Categories
        'unexpected_error' => 'An unexpected error has occurred. The technical team has been notified.',
        'generic_server_error' => 'A server error has occurred. Please try again later or contact support.',
        'json_error' => 'A data processing error occurred. Please try again or contact support.',
    ],

    // Generic messages
    'generic_error' => 'An error has occurred. Please try again later.',
];
