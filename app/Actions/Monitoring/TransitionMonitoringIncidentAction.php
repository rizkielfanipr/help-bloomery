<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitoringIncidentStatus;
use App\Models\MonitoringIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Acknowledge, start investigation, enter monitoring, resolve, and reopen through one validated
 * state machine (MonitoringIncidentStatus::allowedTransitions()). Every transition is written to
 * the append-only timeline in the same database transaction.
 */
class TransitionMonitoringIncidentAction
{
    use RecordsIncidentEvents;

    /**
     * @param  array{note?: ?string, resolution?: ?string, root_cause?: ?string, preventive_action?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function execute(MonitoringIncident $incident, MonitoringIncidentStatus $target, ?User $actor, array $details = []): MonitoringIncident
    {
        $this->authorizeManage($actor, $incident);

        return DB::transaction(function () use ($incident, $target, $actor, $details): MonitoringIncident {
            $incident = MonitoringIncident::query()->lockForUpdate()->findOrFail($incident->id);
            $from = $incident->status;

            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => "Status {$from->getLabel()} tidak dapat diubah menjadi {$target->getLabel()}."]);
            }
            if ($target === MonitoringIncidentStatus::Resolved && blank($details['resolution'] ?? $incident->resolution)) {
                throw ValidationException::withMessages(['resolution' => 'Resolution wajib diisi sebelum incident diselesaikan.']);
            }
            if ($target === MonitoringIncidentStatus::Open && blank($details['note'] ?? null)) {
                throw ValidationException::withMessages(['note' => 'Alasan membuka ulang wajib diisi.']);
            }

            $changes = ['status' => $target];
            match ($target) {
                MonitoringIncidentStatus::Acknowledged => $changes['acknowledged_at'] = $incident->acknowledged_at ?? now(),
                MonitoringIncidentStatus::Resolved => $changes += [
                    'resolved_at' => now(),
                    'resolution' => $details['resolution'] ?? $incident->resolution,
                    'root_cause' => $details['root_cause'] ?? $incident->root_cause,
                    'preventive_action' => $details['preventive_action'] ?? $incident->preventive_action,
                ],
                MonitoringIncidentStatus::Open => $changes += ['resolved_at' => null, 'reopened_count' => $incident->reopened_count + 1],
                default => null,
            };
            if (in_array($target, [MonitoringIncidentStatus::Investigating, MonitoringIncidentStatus::Monitoring], true) && $incident->acknowledged_at === null) {
                $changes['acknowledged_at'] = now();
            }

            $incident->forceFill($changes)->save();
            $this->recordEvent(
                $incident,
                $target === MonitoringIncidentStatus::Open ? 'reopened' : 'status_changed',
                $actor,
                $details['note'] ?? ($target === MonitoringIncidentStatus::Resolved ? ($details['resolution'] ?? null) : null),
                $from->value,
                $target->value,
            );

            return $incident;
        });
    }
}
