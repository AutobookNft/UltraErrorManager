<?php

namespace Ultra\ErrorManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * ErrorLog Model
 *
 * This model represents a logged error in the database.
 * It provides methods for querying, marking as resolved, and statistics.
 *
 * @package Ultra\ErrorManager\Models
 */
class ErrorLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'error_code',
        'type',
        'blocking',
        'message',
        'user_message',
        'http_status_code',
        'context',
        'display_mode',
        'exception_class',
        'exception_message',
        'exception_file',
        'exception_line',
        'exception_trace',
        'request_method',
        'request_url',
        'user_agent',
        'ip_address',
        'user_id',
        'resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'notified',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'context' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'notified' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($errorLog) {
            // Set default values if not provided
            if (!isset($errorLog->ip_address)) {
                $errorLog->ip_address = request()->ip();
            }

            if (!isset($errorLog->user_agent)) {
                $errorLog->user_agent = request()->userAgent();
            }

            if (!isset($errorLog->request_method)) {
                $errorLog->request_method = request()->method();
            }

            if (!isset($errorLog->request_url)) {
                $errorLog->request_url = request()->fullUrl();
            }

            if (!isset($errorLog->user_id) && auth()->check()) {
                $errorLog->user_id = auth()->id();
            }
        });
    }

    /**
     * Scope a query to only include unresolved errors.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('resolved', false);
    }

    /**
     * Scope a query to only include resolved errors.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('resolved', true);
    }

    /**
     * Scope a query to only include critical errors.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('type', 'critical');
    }

    /**
     * Scope a query to only include errors of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include errors with a specific code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $code
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCode(Builder $query, string $code): Builder
    {
        return $query->where('error_code', $code);
    }

    /**
     * Scope a query to only include errors that occurred after a given date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOccurredAfter(Builder $query, string $date): Builder
    {
        return $query->where('created_at', '>=', $date);
    }

    /**
     * Scope a query to only include errors that occurred before a given date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOccurredBefore(Builder $query, string $date): Builder
    {
        return $query->where('created_at', '<=', $date);
    }

    /**
     * Mark this error as resolved.
     *
     * @param  string|null  $resolvedBy
     * @param  string|null  $notes
     * @return bool
     */
    public function markAsResolved(?string $resolvedBy = null, ?string $notes = null): bool
    {
        $this->resolved = true;
        $this->resolved_at = now();
        $this->resolved_by = $resolvedBy;

        if ($notes) {
            $this->resolution_notes = $notes;
        }

        return $this->save();
    }

    /**
     * Mark this error as unresolved.
     *
     * @return bool
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
     * Mark this error as notified.
     *
     * @return bool
     */
    public function markAsNotified(): bool
    {
        $this->notified = true;
        return $this->save();
    }

    /**
     * Relationship to user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get similar errors based on error code.
     *
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSimilarErrors(int $limit = 10)
    {
        return static::withCode($this->error_code)
            ->where('id', '!=', $this->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get a summary of the error context.
     *
     * @param  int  $maxLength
     * @return string
     */
    public function getContextSummary(int $maxLength = 100): string
    {
        if (!$this->context) {
            return 'No context available';
        }

        $json = json_encode($this->context);

        if (strlen($json) <= $maxLength) {
            return $json;
        }

        return substr($json, 0, $maxLength - 3) . '...';
    }

    /**
     * Get error frequency over time.
     *
     * @param  string  $errorCode
     * @param  string  $period  daily|weekly|monthly
     * @param  int  $limit
     * @return array
     */
    public static function getErrorFrequency(string $errorCode, string $period = 'daily', int $limit = 30): array
    {
        $query = static::withCode($errorCode);

        // Group by the appropriate time period
        switch ($period) {
            case 'monthly':
                $format = 'Y-m';
                $dateFormat = '%Y-%m';
                break;
            case 'weekly':
                $format = 'Y-W';
                $dateFormat = '%Y-%u';
                break;
            case 'daily':
            default:
                $format = 'Y-m-d';
                $dateFormat = '%Y-%m-%d';
                break;
        }

        $rawSql = "DATE_FORMAT(created_at, '{$dateFormat}') as period";

        // Rimuovi la selezione di created_at
        $results = $query->selectRaw($rawSql)
            ->selectRaw('COUNT(*) as count')
            ->groupBy('period')  // Mantieni solo il raggruppamento su period
            ->orderBy('period', 'desc')
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'period' => $result->period,
                'count' => $result->count,
            ];
        }

        return array_reverse($data);
    }

    /**
     * Get top error codes by frequency.
     *
     * @param  int  $limit
     * @param  string|null  $fromDate
     * @param  string|null  $toDate
     * @return array
     */
    public static function getTopErrorCodes(int $limit = 10, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = static::select('error_code')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('error_code')
            ->orderBy('count', 'desc')
            ->limit($limit);

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        return $query->get()->toArray();
    }
}
