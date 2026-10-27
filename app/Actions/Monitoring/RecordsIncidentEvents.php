<?php

namespace App\Actions\Monitoring;

use App\Models\MonitoringIncident;
use App\Models\MonitoringIncidentEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Shared helpers for incident Actions: server-side authorization and append-only timeline
 * events (docs/operations/monitoring/prd.md §16, §18.5).
 */
trait RecordsIncidentEvents
{
    /**
     * @throws AuthorizationException
     */
    protected function authorizeManage(?User $actor, ?MonitoringIncident $incident = null): void
    {
        if ($actor === null) {
            return;
        }

        $allowed = $incident ? Gate::forUser($actor)->allows('manage', $incident) : Gate::forUser($actor)->allows('create', MonitoringIncident::class);
        if (! $allowed) {
            throw new AuthorizationException('Anda tidak memiliki izin mengelola incident.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordEvent(MonitoringIncident $incident, string $type, ?User $actor, ?string $note = null, ?string $from = null, ?string $to = null, array $metadata = []): MonitoringIncidentEvent
    {
        return $incident->events()->create([
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => $note,
            'metadata' => $metadata ?: null,
        ]);
    }
}
