<?php

namespace Ultra\ErrorManager\Database\Seeders;

use Illuminate\Database\Seeder;
use Ultra\ErrorManager\Models\ErrorLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ErrorLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();

        // Svuotiamo prima la tabella
        DB::table('error_logs')->truncate();

        // Errori comuni da utilizzare
        $errorCodes = [
            'VIRUS_FOUND',
            'ERROR_DURING_FILE_UPLOAD',
            'INVALID_FILE_EXTENSION',
            'TEMP_FILE_NOT_FOUND',
            'MAX_FILE_SIZE',
            'SCAN_ERROR',
            'ERROR_GETTING_PRESIGNED_URL',
            'FILE_NOT_FOUND',
            'INVALID_FILE_NAME',
            'GENERIC_SERVER_ERROR',
            'UNEXPECTED_ERROR'
        ];

        // Tipi di errore
        $errorTypes = ['critical', 'error', 'warning', 'notice'];

        // Livelli di blocco
        $blockingLevels = ['blocking', 'semi-blocking', 'not'];

        // Modi di visualizzazione
        $displayModes = ['div', 'sweet-alert', 'toast', 'log-only'];

        // Creo un mix di errori risolti e non
        for ($i = 0; $i < 200; $i++) {
            $createdAt = $faker->dateTimeBetween('-3 months', 'now');
            $errorCode = $faker->randomElement($errorCodes);
            $errorType = $faker->randomElement($errorTypes);
            $resolved = $faker->boolean(40); // 40% di probabilità di essere risolto

            $errorLog = [
                'error_code' => $errorCode,
                'type' => $errorType,
                'blocking' => $faker->randomElement($blockingLevels),
                'message' => "Developer message for {$errorCode}",
                'user_message' => "User-friendly message for {$errorCode}",
                'http_status_code' => $errorType === 'critical' ? 500 : ($errorType === 'error' ? 400 : 200),
                'context' => json_encode([
                    'file_name' => $faker->word . '.' . $faker->fileExtension,
                    'user_id' => $faker->numberBetween(1, 100),
                    'url' => $faker->url,
                    'additional_info' => $faker->sentence,
                ]),
                'display_mode' => $faker->randomElement($displayModes),
                'exception_class' => $faker->boolean(70) ? 'App\\Exceptions\\' . ucfirst($faker->word) . 'Exception' : null,
                'exception_message' => $faker->sentence,
                'exception_file' => $faker->boolean(70) ? '/var/www/html/app/' . $faker->word . '.php' : null,
                'exception_line' => $faker->boolean(70) ? $faker->numberBetween(10, 500) : null,
                'exception_trace' => $faker->boolean(70) ? $faker->paragraphs(3, true) : null,
                'request_method' => $faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
                'request_url' => $faker->url,
                'user_agent' => $faker->userAgent,
                'ip_address' => $faker->ipv4,
                'user_id' => null,
                'resolved' => $resolved,
                'resolved_at' => $resolved ? Carbon::parse($createdAt)->addHours($faker->numberBetween(1, 48)) : null,
                'resolved_by' => $resolved ? $faker->name : null,
                'resolution_notes' => $resolved ? $faker->optional(0.7)->paragraph : null,
                'notified' => $faker->boolean(60),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            ErrorLog::create($errorLog);
        }

        // Aggiungiamo alcuni errori recenti
        for ($i = 0; $i < 20; $i++) {
            $createdAt = $faker->dateTimeBetween('-24 hours', 'now');
            $errorCode = $faker->randomElement($errorCodes);
            $errorType = $faker->randomElement($errorTypes);

            $errorLog = [
                'error_code' => $errorCode,
                'type' => $errorType,
                'blocking' => $faker->randomElement($blockingLevels),
                'message' => "Developer message for {$errorCode}",
                'user_message' => "User-friendly message for {$errorCode}",
                'http_status_code' => $errorType === 'critical' ? 500 : ($errorType === 'error' ? 400 : 200),
                'context' => json_encode([
                    'file_name' => $faker->word . '.' . $faker->fileExtension,
                    'user_id' => $faker->numberBetween(1, 100),
                    'url' => $faker->url,
                    'additional_info' => $faker->sentence,
                ]),
                'display_mode' => $faker->randomElement($displayModes),
                'exception_class' => $faker->boolean(70) ? 'App\\Exceptions\\' . ucfirst($faker->word) . 'Exception' : null,
                'exception_message' => $faker->sentence,
                'exception_file' => $faker->boolean(70) ? '/var/www/html/app/' . $faker->word . '.php' : null,
                'exception_line' => $faker->boolean(70) ? $faker->numberBetween(10, 500) : null,
                'exception_trace' => $faker->boolean(70) ? $faker->paragraphs(3, true) : null,
                'request_method' => $faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
                'request_url' => $faker->url,
                'user_agent' => $faker->userAgent,
                'ip_address' => $faker->ipv4,
                'user_id' => null,
                'resolved' => false,
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution_notes' => null,
                'notified' => $faker->boolean(60),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            ErrorLog::create($errorLog);
        }

        // Aggiungiamo errori critici recenti
        for ($i = 0; $i < 5; $i++) {
            $createdAt = $faker->dateTimeBetween('-12 hours', 'now');
            $errorCode = $faker->randomElement(['GENERIC_SERVER_ERROR', 'UNEXPECTED_ERROR', 'ERROR_DURING_FILE_UPLOAD']);

            $errorLog = [
                'error_code' => $errorCode,
                'type' => 'critical',
                'blocking' => 'blocking',
                'message' => "Critical error: {$errorCode}",
                'user_message' => "A system error has occurred. Our technical team has been notified.",
                'http_status_code' => 500,
                'context' => json_encode([
                    'file_name' => $faker->word . '.' . $faker->fileExtension,
                    'user_id' => $faker->numberBetween(1, 100),
                    'url' => $faker->url,
                    'additional_info' => $faker->sentence,
                ]),
                'display_mode' => 'sweet-alert',
                'exception_class' => 'App\\Exceptions\\' . ucfirst($faker->word) . 'Exception',
                'exception_message' => $faker->sentence,
                'exception_file' => '/var/www/html/app/' . $faker->word . '.php',
                'exception_line' => $faker->numberBetween(10, 500),
                'exception_trace' => $faker->paragraphs(3, true),
                'request_method' => $faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
                'request_url' => $faker->url,
                'user_agent' => $faker->userAgent,
                'ip_address' => $faker->ipv4,
                'user_id' => null,
                'resolved' => false,
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution_notes' => null,
                'notified' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            ErrorLog::create($errorLog);
        }

        \Log::info('Inseriti 225 log di errore di prova.');
    }
}
