<?php

declare(strict_types=1); // Aggiungi strict types

namespace Ultra\ErrorManager\Database\Seeders;

use Illuminate\Database\Seeder;
use Ultra\ErrorManager\Models\ErrorLog;
use Illuminate\Support\Facades\DB; // Facade OK per truncate
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Log; // Facade OK per info alla fine

class ErrorLogSeeder extends Seeder
{
    /**
     * Run the database seeds to populate error_logs table with sample data.
     *
     * @return void
     */
    public function run(): void // Aggiungi void return type
    {
        $faker = Faker::create();

        // Clean the table before seeding
        DB::table('error_logs')->truncate();

        // --- Configuration for Fake Data ---
        $errorCodes = [
            'VIRUS_FOUND', 'ERROR_DURING_FILE_UPLOAD', 'INVALID_FILE_EXTENSION',
            'TEMP_FILE_NOT_FOUND', 'MAX_FILE_SIZE', 'SCAN_ERROR',
            'ERROR_GETTING_PRESIGNED_URL', 'FILE_NOT_FOUND', 'INVALID_FILE_NAME',
            'GENERIC_SERVER_ERROR', 'UNEXPECTED_ERROR', 'AUTHENTICATION_ERROR',
            'VALIDATION_ERROR', 'UCM_NOT_FOUND', 'DATABASE_ERROR' // Aggiunti alcuni comuni
        ];
        $errorTypes = ['critical', 'error', 'warning', 'notice'];
        $blockingLevels = ['blocking', 'semi-blocking', 'not'];
        $displayModes = ['div', 'sweet-alert', 'toast', 'log-only'];
        $httpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $commonExceptions = [
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \PDOException::class,
            \RuntimeException::class,
            null // Possibilità di non avere eccezione
        ];

        $totalCreated = 0;

        // --- Loop 1: Generate a mix of historical errors (resolved/unresolved) ---
        $historicalCount = 200;
        $this->command?->getOutput()?->writeln("<info>Seeding {$historicalCount} historical error logs...</info>"); // Output per console
        for ($i = 0; $i < $historicalCount; $i++) {
            $createdAt = $faker->dateTimeBetween('-3 months', '-1 day'); // Assicura siano nel passato
            $errorCode = $faker->randomElement($errorCodes);
            $errorType = $faker->randomElement($errorTypes);
            $resolved = $faker->boolean(40); // ~40% resolved
            $exceptionClass = $faker->optional(0.7)->randomElement($commonExceptions); // ~70% hanno eccezione

            ErrorLog::create([
                'error_code' => $errorCode,
                'type' => $errorType,
                'blocking' => $faker->randomElement($blockingLevels),
                'message' => "{$faker->sentence()} Developer message for {$errorCode}", // Messaggi più vari
                'user_message' => "{$faker->sentence()} User-friendly message for {$errorCode}",
                'http_status_code' => $errorType === 'critical' ? 500 : ($errorType === 'error' ? 400 : 200),
                // Passa l'array direttamente, Eloquent lo codifica
                'context' => [
                    'file_name' => $faker->word . '.' . $faker->fileExtension,
                    'user_id' => $faker->optional(0.8)->numberBetween(1, 100), // ~80% hanno user_id
                    'url_context' => $faker->url, // Chiave più specifica
                    'additional_info' => $faker->sentence,
                    'trace_id' => $faker->uuid, // Esempio di altro contesto
                ],
                'display_mode' => $faker->randomElement($displayModes),
                'exception_class' => $exceptionClass,
                'exception_message' => $exceptionClass ? $faker->sentence(10) : null,
                'exception_file' => $exceptionClass ? '/var/www/html/app/' . $faker->word . '.php' : null,
                'exception_line' => $exceptionClass ? $faker->numberBetween(10, 500) : null,
                'exception_trace' => $exceptionClass ? $faker->paragraphs(3, true) : null,
                'request_method' => $faker->randomElement($httpMethods),
                'request_url' => $faker->url,
                'user_agent' => $faker->userAgent,
                'ip_address' => $faker->ipv4,
                'user_id' => $faker->optional(0.8)->numberBetween(1, 1000), // User ID anche qui per coerenza
                'resolved' => $resolved,
                'resolved_at' => $resolved ? Carbon::parse($createdAt)->addHours($faker->numberBetween(1, 72)) : null, // Range più ampio
                'resolved_by' => $resolved ? $faker->name : null,
                'resolution_notes' => $resolved ? $faker->optional(0.7)->paragraph : null,
                'notified' => $faker->boolean(60),
                'created_at' => $createdAt,
                'updated_at' => $resolved ? Carbon::parse($createdAt)->addHours($faker->numberBetween(1, 72)) : $createdAt, // Updated_at cambia se risolto
            ]);
            $totalCreated++;
        }

        // --- Loop 2: Generate recent, mostly unresolved errors ---
        $recentCount = 20;
         $this->command?->getOutput()?->writeln("<info>Seeding {$recentCount} recent error logs...</info>");
        for ($i = 0; $i < $recentCount; $i++) {
            $createdAt = $faker->dateTimeBetween('-24 hours', 'now');
            $errorCode = $faker->randomElement($errorCodes);
            $errorType = $faker->randomElement($errorTypes);
            $exceptionClass = $faker->optional(0.6)->randomElement($commonExceptions);

            ErrorLog::create([
                'error_code' => $errorCode,
                'type' => $errorType,
                'blocking' => $faker->randomElement($blockingLevels),
                'message' => "Recent: Dev message for {$errorCode}",
                'user_message' => "Recent: User message for {$errorCode}",
                'http_status_code' => $errorType === 'critical' ? 500 : ($errorType === 'error' ? 400 : 200),
                'context' => [ /* ... come sopra ... */ ],
                'display_mode' => $faker->randomElement($displayModes),
                'exception_class' => $exceptionClass,
                'exception_message' => $exceptionClass ? $faker->sentence(8) : null,
                'exception_file' => $exceptionClass ? '/var/www/html/app/' . $faker->word . '.php' : null,
                'exception_line' => $exceptionClass ? $faker->numberBetween(10, 500) : null,
                'exception_trace' => $exceptionClass ? $faker->paragraphs(2, true) : null,
                'request_method' => $faker->randomElement($httpMethods),
                'request_url' => $faker->url,
                'user_agent' => $faker->userAgent,
                'ip_address' => $faker->ipv4,
                'user_id' => $faker->optional(0.9)->numberBetween(1, 1000), // Più probabile avere user ID
                'resolved' => false, // Maggior parte non risolti
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution_notes' => null,
                'notified' => $faker->boolean(30), // Meno probabile notificati
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
             $totalCreated++;
        }

        // --- Loop 3: Generate very recent critical, unresolved errors ---
        $criticalCount = 5;
         $this->command?->getOutput()?->writeln("<info>Seeding {$criticalCount} critical recent error logs...</info>");
        for ($i = 0; $i < $criticalCount; $i++) {
            $createdAt = $faker->dateTimeBetween('-12 hours', 'now');
            $errorCode = $faker->randomElement(['GENERIC_SERVER_ERROR', 'UNEXPECTED_ERROR', 'DATABASE_ERROR', 'AUTHENTICATION_ERROR']);
            $exceptionClass = $faker->randomElement($commonExceptions); // Critici hanno spesso eccezione

            ErrorLog::create([
                'error_code' => $errorCode,
                'type' => 'critical', // Sempre critico
                'blocking' => $faker->randomElement(['blocking', 'semi-blocking']), // Spesso bloccante
                'message' => "Critical: Dev message for {$errorCode}",
                'user_message' => "A critical system error occurred. Ref: {$faker->uuid}", // Messaggio utente più generico/utile
                'http_status_code' => 500,
                'context' => [ /* ... come sopra ... */ ],
                'display_mode' => $faker->randomElement(['sweet-alert', 'log-only']), // Spesso alert o solo log backend
                'exception_class' => $exceptionClass,
                'exception_message' => $exceptionClass ? $faker->sentence(12) : null,
                'exception_file' => $exceptionClass ? '/var/www/html/app/' . $faker->word . '.php' : null,
                'exception_line' => $exceptionClass ? $faker->numberBetween(10, 500) : null,
                'exception_trace' => $exceptionClass ? $faker->paragraphs(4, true) : null, // Trace più probabile
                'request_method' => $faker->randomElement($httpMethods),
                'request_url' => $faker->url,
                'user_agent' => $faker->userAgent,
                'ip_address' => $faker->ipv4,
                'user_id' => $faker->optional(0.7)->numberBetween(1, 1000),
                'resolved' => false, // Non risolti
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution_notes' => null,
                'notified' => $faker->boolean(80), // Altamente probabile notificati
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
             $totalCreated++;
        }

        // Usa il logger standard di Laravel (Facade è OK qui)
        Log::info("UEM Seeder: Created {$totalCreated} sample error log entries.");
         $this->command?->getOutput()?->writeln("<comment>Seeded {$totalCreated} sample error logs.</comment>"); // Output finale
    }
}