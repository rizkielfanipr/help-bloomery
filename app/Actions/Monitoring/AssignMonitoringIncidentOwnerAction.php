<?php

namespace App\Actions\Monitoring;

use App\Models\MonitoringIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignMonitoringIncidentOwnerAction
{
    use RecordsIncidentEvents;

    public function execute(MonitoringIncident $incident, User $owner, ?User $actor): MonitoringIncident
    {
        $this->authorizeManage($actor, $incident);

        return DB::transaction(function () use ($incident, $owner, $actor): MonitoringIncident {
            $previousOwner = $incident->owner_id;
            $incident->forceFill(['owner_id' => $owner->id])->save();
            $this->recordEvent($incident, 'owner_assigned', $actor, 'PIC: '.$owner->name, metadata: ['previous_owner_id' => $previousOwner, 'owner_id' => $owner->id]);

            return $incident;
        });
    }
}
