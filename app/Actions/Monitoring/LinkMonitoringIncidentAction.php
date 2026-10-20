<?php

namespace App\Actions\Monitoring;

use App\Models\MonitoringIncident;
use App\Models\MonitoringIncidentLink;
use App\Models\MonitoringTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Link or unlink monitoring records (alert, error, transaction, deployment). Linking never changes
 * the linked record, in particular never the status of a business transaction.
 */
class LinkMonitoringIncidentAction
{
    use RecordsIncidentEvents;

    /**
     * @throws ValidationException
     */
    public function link(MonitoringIncident $incident, string $type, string $reference, ?User $actor): MonitoringIncidentLink
    {
        $this->authorizeManage($actor, $incident);
        $class = MonitoringIncidentLink::TYPES[$type] ?? null;
        if (! $class) {
            throw ValidationException::withMessages(['type' => 'Jenis tautan tidak dikenal.']);
        }

        $query = $class::query();
        $record = in_array('uuid', (new $class)->getFillable(), true)
            ? $query->where('uuid', $reference)->orWhere('id', ctype_digit($reference) ? (int) $reference : 0)->first()
            : $query->find(ctype_digit($reference) ? (int) $reference : 0);
        if (! $record || ($record instanceof MonitoringTransaction && ! $record->isParent())) {
            throw ValidationException::withMessages(['reference' => 'Record yang ditautkan tidak ditemukan.']);
        }

        return DB::transaction(function () use ($incident, $type, $record, $actor): MonitoringIncidentLink {
            $link = $incident->links()->firstOrCreate(
                ['linkable_type' => $type, 'linkable_id' => $record->getKey()],
                ['label' => mb_substr((string) ($record->title ?? $record->exception_class ?? $record->uuid ?? $record->getKey()), 0, 255), 'created_by' => $actor?->id],
            );
            if ($link->wasRecentlyCreated) {
                $this->recordEvent($incident, 'linked', $actor, metadata: ['type' => $type, 'id' => $record->getKey()]);
            }

            return $link;
        });
    }

    public function unlink(MonitoringIncident $incident, MonitoringIncidentLink $link, ?User $actor): void
    {
        $this->authorizeManage($actor, $incident);
        abort_unless($link->monitoring_incident_id === $incident->id, 404);

        DB::transaction(function () use ($incident, $link, $actor): void {
            $link->delete();
            $this->recordEvent($incident, 'unlinked', $actor, metadata: ['type' => $link->linkable_type, 'id' => $link->linkable_id]);
        });
    }
}
