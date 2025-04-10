<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Se usi le factory
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Per la relazione user
use Illuminate\Support\Collection; // Per return type hint
use Illuminate\Support\Facades\DB; // Per query Raw
use Carbon\Carbon; // Per manipolazione date

/**
 * 🎯 ErrorLog – Oracoded Eloquent Model for Error Persistence
 *
 * Represents a logged error event within the database. Stores detailed information
 * about the error, its context, associated exception (if any), request details,
 * and resolution status. Designed to be independent of HTTP/Auth context, receiving
 * necessary data explicitly upon creation.
 *
 * 🧱 Structure:
 * - Standard Eloquent Model (`$fillable`, `$casts`, `$timestamps`).
 * - NO direct dependency on `request()` or `auth()` helpers in model events.
 * - Provides query scopes for common filtering (resolved, type, code, date).
 * - Includes methods for state management (`markAsResolved`, `markAsUnresolved`, `markAsNotified`).
 * - Offers utility methods for data retrieval used by the dashboard (`getSimilarErrors`, etc.).
 * - Defines `user()` relationship (optional, depends on app config).
 *
 * 📡 Communicates:
 * - Primarily with the Database via Eloquent ORM.
 * - Can be related to the User model via the `user()` relationship.
 *
 * 🧪 Testable:
 * - Can be tested using standard Eloquent testing techniques (factories, database transactions/mocking).
 * - Scopes and methods are testable units.
 * - Independence from HTTP/Auth context simplifies testing.
 *
 * 🛡️ GDPR Considerations:
 * - Stores potentially sensitive data (IP, User Agent, User ID, Context, Exception details).
 * - Context data should be sanitized *before* being passed to `create()`.
 * - Deletion/Anonymization must be handled externally (e.g., GDPR commands or policies).
 */
class ErrorLog extends Model // Non la rendiamo final di default, Eloquent a volte richiede estensione
{
    // Use HasFactory if you plan to create factories for testing this model
    // use HasFactory;

    /**
     * 🧱 Mass assignable attributes.
     * @var array<int, string>
     */
    protected $fillable = [
        'error_code',
        'type',
        'blocking',
        'message', // Dev message
        'user_message', // User message (potentially localized key or direct)
        'http_status_code',
        'context', // Should be JSON string or already sanitized array
        'display_mode',
        'exception_class',
        'exception_message',
        'exception_file',
        'exception_line',
        'exception_trace', // Potentially truncated
        'request_method',
        'request_url',
        'user_agent',
        'ip_address',
        'user_id', // Nullable foreign key
        'resolved',
        'resolved_at',
        'resolved_by', // User name or identifier string
        'resolution_notes',
        'notified', // Flag indicating if primary notification (e.g., email) was sent
    ];

    /**
     * 🧱 Attribute casting.
     * Ensures correct data types.
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array', // Automatically encode/decode JSON
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'notified' => 'boolean',
        'created_at' => 'datetime', // Handled by Eloquent
        'updated_at' => 'datetime', // Handled by Eloquent
        'exception_line' => 'integer',
        'http_status_code' => 'integer',
        'user_id' => 'integer', // Cast user_id for consistency
    ];

    /**
     * 🧱 The table associated with the model.
     * Explicitly defining is slightly more performant than relying on convention.
     *
     * @var string
     */
    protected $table = 'error_logs';

    /**
     * 🧱 Model booted event.
     * REMOVED automatic filling of request/auth data. This should be done
     * by the calling code (e.g., DatabaseLogHandler) passing the data explicitly.
     *
     * @return void
     */
    protected static function booted(): void
    {
        // No longer setting request/auth data automatically here.
        // static::creating(function ($errorLog) {
        //     // Logic removed
        // });
    }

    // --- Query Scopes ---

    /**
     * 🔎 Scope: Filter for unresolved errors.
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('resolved', false);
    }

    /**
     * 🔎 Scope: Filter for resolved errors.
     * @param Builder $query
     * @return Builder
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('resolved', true);
    }

    /**
     * 🔎 Scope: Filter for critical errors.
     * @param Builder $query
     * @return Builder
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('type', 'critical');
    }

    /**
     * 🔎 Scope: Filter by error type.
     * @param Builder $query
     * @param string $type Error type ('critical', 'error', etc.)
     * @return Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * 🔎 Scope: Filter by specific error code.
     * @param Builder $query
     * @param string $code Symbolic error code.
     * @return Builder
     */
    public function scopeWithCode(Builder $query, string $code): Builder
    {
        return $query->where('error_code', $code);
    }

    /**
     * 🔎 Scope: Filter errors created on or after a specific date.
     * @param Builder $query
     * @param string|Carbon $date Date string (Y-m-d) or Carbon instance.
     * @return Builder
     */
    public function scopeOccurredAfter(Builder $query, string|Carbon $date): Builder
    {
        return $query->where('created_at', '>=', Carbon::parse($date)->startOfDay());
    }

    /**
     * 🔎 Scope: Filter errors created on or before a specific date.
     * @param Builder $query
     * @param string|Carbon $date Date string (Y-m-d) or Carbon instance.
     * @return Builder
     */
    public function scopeOccurredBefore(Builder $query, string|Carbon $date): Builder
    {
        return $query->where('created_at', '<=', Carbon::parse($date)->endOfDay());
    }

    // --- State Management Methods ---

    /**
     * 🔧 Mark this error instance as resolved.
     * Sets resolution details and saves the model.
     *
     * @param string|null $resolvedBy Identifier of the resolver (e.g., user name, 'System').
     * @param string|null $notes Optional resolution notes.
     * @return bool True on success, false on failure.
     */
    public function markAsResolved(?string $resolvedBy = null, ?string $notes = null): bool
    {
        $this->resolved = true;
        $this->resolved_at = now();
        $this->resolved_by = $resolvedBy;
        $this->resolution_notes = $notes; // Update notes as well

        return $this->save();
    }

    /**
     * 🔧 Mark this error instance as unresolved.
     * Clears resolution details and saves the model.
     *
     * @return bool True on success, false on failure.
     */
    public function markAsUnresolved(): bool
    {
        $this->resolved = false;
        $this->resolved_at = null;
        $this->resolved_by = null;
        $this->resolution_notes = null;

        return $this->save();
    }

    /**
     * 🔧 Mark this error instance as having been notified (e.g., email sent).
     *
     * @return bool True on success, false on failure.
     */
    public function markAsNotified(): bool
    {
        $this->notified = true;
        return $this->save();
    }

    // --- Relationships ---

    /**
     * 🔗 Relationship: Get the user associated with this error log (if any).
     * Assumes the user model is configured in `config/auth.php`.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        // Ensure the config key points to the correct user model provider
        $userModel = config('auth.providers.users.model');
        if (!$userModel) {
            // Fallback or throw exception if user model isn't configured?
            // For now, let Eloquent handle the potential null relationship.
            return $this->belongsTo(User::class); // Default guess if config missing
        }
        return $this->belongsTo($userModel);
    }

    // --- Utility / Dashboard Methods ---

    /**
     * 📡 Get similar errors based on the same error code.
     * Excludes the current instance.
     *
     * @param int $limit Maximum number of similar errors to retrieve.
     * @return \Illuminate\Database\Eloquent\Collection<int, ErrorLog>
     */
    public function getSimilarErrors(int $limit = 10): Collection
    {
        return static::withCode($this->error_code)
            ->where('id', '!=', $this->id) // Exclude self
            ->latest() // Order by most recent
            ->limit($limit)
            ->get();
    }

    /**
     * 📡 Get a potentially truncated summary of the error context.
     * Useful for display in lists where full context is too large.
     *
     * @param int $maxLength Maximum length of the JSON string summary.
     * @return string JSON summary or 'No context available'.
     */
    public function getContextSummary(int $maxLength = 100): string
    {
        $context = $this->context; // Access the decoded array via casts
        if (empty($context)) {
            return 'No context available';
        }

        // Encode back to JSON for summary
        $json = json_encode($context);
        if ($json === false || strlen($json) === 0) { // Handle encoding errors
             return 'Context encoding error';
        }

        if (mb_strlen($json) <= $maxLength) {
            return $json;
        }

        // Truncate multibyte safe
        return mb_substr($json, 0, $maxLength - 3) . '...';
    }

    /**
     * 📊 Static: Get error frequency count over time for a specific error code.
     * Used by the statistics dashboard.
     *
     * @param string $errorCode The specific error code to analyze.
     * @param string $period Grouping period ('daily', 'weekly', 'monthly').
     * @param int $limit Number of periods to retrieve.
     * @param Carbon|null $endDate Optional end date for the analysis period (defaults to now).
     * @return array<int, array{'period': string, 'count': int}> Array of {period, count} maps.
     */
    public static function getErrorFrequency(string $errorCode, string $period = 'daily', int $limit = 30, ?Carbon $endDate = null): array
    {
        $query = static::query()->withCode($errorCode); // Start query with code scope
        $endDate = $endDate ?? now(); // Default to now

        // Determine date format and truncation logic based on period
        switch (strtolower($period)) {
            case 'monthly':
                $dateFormat = '%Y-%m'; // Group by year-month
                $startDate = $endDate->copy()->subMonthsNoOverflow($limit - 1)->startOfMonth();
                break;
            case 'weekly':
                 // Use ISO 8601 week date format (%G-%V) for consistency
                 // Note: %u gives week number (1-53), %Y year. %G-%V is ISO week year-week.
                 $dateFormat = '%x-%v'; // ISO Year-Week (e.g., 2023-42)
                 $startDate = $endDate->copy()->subWeeks($limit - 1)->startOfWeek();
                 break;
            case 'daily':
            default:
                $dateFormat = '%Y-%m-%d'; // Group by year-month-day
                $startDate = $endDate->copy()->subDays($limit - 1)->startOfDay();
                break;
        }

        // Ensure query considers the date range
        $query->whereBetween('created_at', [$startDate, $endDate->endOfDay()]);

        // Build the raw select expression for grouping
        $rawSelect = DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period, COUNT(*) as count");

        $results = $query->select($rawSelect)
            ->groupBy('period')
            ->orderBy('period', 'asc') // Order chronologically before returning
            ->get()
            ->keyBy('period'); // Key by period for easy lookup

        // Generate all periods in the range to ensure zero counts are included
        $periodData = [];
        $currentPeriod = $startDate->copy();

        for ($i = 0; $i < $limit; $i++) {
            $periodKey = $currentPeriod->format(match (strtolower($period)) {
                 'monthly' => 'Y-m',
                 'weekly' => 'o-W', // Use 'o' for ISO Year, 'W' for ISO week number
                 default => 'Y-m-d',
            });

            $periodData[$periodKey] = [
                'period' => $periodKey,
                'count' => $results->get($periodKey)?->count ?? 0, // Use lookup, default 0
            ];

            // Increment period
            match (strtolower($period)) {
                'monthly' => $currentPeriod->addMonthNoOverflow(),
                'weekly' => $currentPeriod->addWeek(),
                default => $currentPeriod->addDay(),
            };

             // Stop if we go past the end date significantly (safety)
             if ($currentPeriod->isAfter($endDate->copy()->addDay())) break;
        }

        // Return only the values (array of maps)
        return array_values($periodData);
    }


    /**
     * 📊 Static: Get top N most frequent error codes within a date range.
     * Used by the dashboard.
     *
     * @param int $limit Number of top codes to return.
     * @param string|Carbon|null $fromDate Start date (inclusive).
     * @param string|Carbon|null $toDate End date (inclusive).
     * @return array<int, array{'error_code': string, 'count': int}>
     */
    public static function getTopErrorCodes(int $limit = 10, string|Carbon|null $fromDate = null, string|Carbon|null $toDate = null): array
    {
        $query = static::select('error_code', DB::raw('COUNT(*) as count')) // Use DB::raw
            ->groupBy('error_code')
            ->orderByDesc('count') // Use orderByDesc for clarity
            ->limit($limit);

        if ($fromDate) {
             // Use scope for consistency
            $query->occurredAfter($fromDate);
        }

        if ($toDate) {
             // Use scope for consistency
            $query->occurredBefore($toDate);
        }

        return $query->get()->toArray(); // Return as array
    }
}