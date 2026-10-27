<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Link from an incident to a monitoring record. `linkable_type` is one of TYPES (a short key,
 * not a class name); there is no polymorphic foreign key so pruning detail rows never touches
 * incident history (docs/operations/monitoring/prd.md §16).
 */
class MonitoringIncidentLink extends Model
{
    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'alert' => MonitoringAlert::class,
        'error' => MonitoringError::class,
        'transaction' => MonitoringTransaction::class,
        'deployment' => MonitoringDeployment::class,
    ];

    protected $fillable = [
        'monitoring_incident_id',
        'linkable_type',
        'linkable_id',
        'label',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'linkable_id' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(MonitoringIncident::class, 'monitoring_incident_id');
    }

    /**
     * Resolves the linked record, or null when it was pruned by retention.
     */
    public function linkedRecord(): ?Model
    {
        $class = self::TYPES[$this->linkable_type] ?? null;

        return $class ? $class::query()->find($this->linkable_id) : null;
    }
}
