<?php

namespace App\Models;

use App\Enums\MonitoringServiceStatus;
use App\Services\Monitoring\MonitoringContext;
use Database\Factories\MonitoringServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One monitored service with its editable check configuration and latest evaluated state
 * (docs/system-monitoring-prd.md §10, §18.1).
 */
class MonitoringService extends Model
{
    /** @use HasFactory<MonitoringServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'environment',
        'key',
        'name',
        'group',
        'is_enabled',
        'is_critical',
        'check_interval_seconds',
        'timeout_seconds',
        'failure_threshold',
        'recovery_threshold',
        'last_status',
        'last_observed_status',
        'last_checked_at',
        'status_changed_at',
        'last_response_time_ms',
        'last_http_status',
        'last_safe_message',
        'consecutive_failures',
        'consecutive_successes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_critical' => 'boolean',
            'check_interval_seconds' => 'integer',
            'timeout_seconds' => 'integer',
            'failure_threshold' => 'integer',
            'recovery_threshold' => 'integer',
            'last_status' => MonitoringServiceStatus::class,
            'last_observed_status' => MonitoringServiceStatus::class,
            'last_checked_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'last_response_time_ms' => 'integer',
            'last_http_status' => 'integer',
            'consecutive_failures' => 'integer',
            'consecutive_successes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(MonitoringCheckResult::class);
    }

    public function activeAlert(): HasOne
    {
        return $this->hasOne(MonitoringAlert::class)->whereNotNull('active_dedup_key');
    }

    /** @param Builder<MonitoringService> $query */
    public function scopeForCurrentEnvironment(Builder $query): void
    {
        $query->where('environment', MonitoringContext::environment());
    }

    public function isDue(): bool
    {
        return $this->last_checked_at === null
            || $this->last_checked_at->copy()->addSeconds(max(1, $this->check_interval_seconds - 5))->isPast();
    }

    /**
     * A snapshot older than three intervals is stale: the scheduler probably stopped checking.
     */
    public function isStale(): bool
    {
        return $this->last_checked_at === null
            || $this->last_checked_at->copy()->addSeconds($this->check_interval_seconds * 3)->isPast();
    }
}
