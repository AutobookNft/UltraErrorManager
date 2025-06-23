    <?php

    use Ultra\UploadManager\Logging\CustomizeFormatter;

    return [
        'upload' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UltraUploadManager.log'),
            'level' => 'debug',
            'days' => 7,  // Numero di giorni per cui conservare i log
        ],

        'logging' => [
            'detailed_context_in_log' => env('ERROR_LOG_DETAILED_CONTEXT', true),

            // ✅ Nuovo flag per attivare il formatter leggibile
            'log_with_formatter' => env('ERROR_LOG_WITH_FORMATTER', true),
        ],

    ];