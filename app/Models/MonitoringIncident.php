<?php

namespace App\Models;

use App\Enums\MonitoringIncidentSeverity;
use App\Enums\MonitoringIncidentStatus;
use App\Services\Monitoring\MonitoringContext;
use Database\Factories\MonitoringIncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Latest state of one incident; every change is written to the append-only timeline in
 * MonitoringIncidentEvent (docs/system-monitoring-prd.md §16). An incident never changes the
 * status of a business transaction.
 */
class MonitoringIncident extends Model
{
    /** @use HasFactory<MonitoringIncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'number',
        'environment',
        'title',
        'summary',
        'severity',
        'status',
        'affected_services',
        'owner_id',
        'erp_repair_request_id',
        'created_by',
        'is_automatic',
        'detected_at',
        'acknowledged_at',
        'resolved_at',
        'reopened_count',
        'root_cause',
        'resolution',
        'preventive_action',
    ];

    protected function casts(): array
    {
        return [
            'severity' => MonitoringIncidentSeverity::class,
            'status' => MonitoringIncidentStatus::class,
            'affected_services' => 'array',
            'is_automatic' => 'boolean',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'reopened_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function erpRepairRequest(): BelongsTo
    {
        return $this->belongsTo(ErpRepairRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MonitoringIncidentEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(MonitoringIncidentLink::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(MonitoringAlert::class);
    }

    /** @param Builder<MonitoringIncident> $query */
    public function scopeForCurrentEnvironment(Builder $query): void
    {
        $query->where('environment', MonitoringContext::environment());
    }

    /** @param Builder<MonitoringIncident> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', MonitoringIncidentStatus::Resolved->value);
    }
}
