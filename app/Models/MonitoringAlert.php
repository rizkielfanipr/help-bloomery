<?php

namespace App\Models;

use App\Enums\MonitoringAlertStatus;
use App\Enums\MonitoringIncidentSeverity;
use App\Services\Monitoring\MonitoringContext;
use Database\Factories\MonitoringAlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deduplicated alert: at most one Active alert per dedup key (docs/system-monitoring-prd.md §17).
 */
class MonitoringAlert extends Model
{
    /** @use HasFactory<MonitoringAlertFactory> */
    use HasFactory;

    protected $fillable = [
        'environment',
        'dedup_key',
        'active_dedup_key',
        'source_type',
        'source_key',
        'title',
        'severity',
        'status',
        'safe_message',
        'monitoring_service_id',
        'monitoring_incident_id',
        'opened_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'duration_seconds',
        'last_notified_at',
        'notification_count',
        'suppressed',
    ];

    protected function casts(): array
    {
        return [
            'status' => MonitoringAlertStatus::class,
            'severity' => MonitoringIncidentSeverity::class,
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'duration_seconds' => 'integer',
            'notification_count' => 'integer',
            'suppressed' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MonitoringService::class, 'monitoring_service_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(MonitoringIncident::class, 'monitoring_incident_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @param Builder<MonitoringAlert> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('active_dedup_key');
    }

    /** @param Builder<MonitoringAlert> $query */
    public function scopeForCurrentEnvironment(Builder $query): void
    {
        $query->where('environment', MonitoringContext::environment());
    }
}
