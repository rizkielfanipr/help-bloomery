<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only incident timeline entry (docs/operations/monitoring/prd.md §18.5). Updating or deleting
 * an existing event is refused at model level.
 */
class MonitoringIncidentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'monitoring_incident_id',
        'event_type',
        'from_status',
        'to_status',
        'actor_id',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Incident timeline events are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Incident timeline events are append-only.'));
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(MonitoringIncident::class, 'monitoring_incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
