<?php

namespace App\Actions\Monitoring;

use App\Models\MonitoringIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Root cause, resolution, preventive action, and Permintaan IT link. The ticket is only linked;
 * it is never copied or closed automatically.
 */
class UpdateMonitoringIncidentAnalysisAction
{
    use RecordsIncidentEvents;

    /**
     * @param  array{root_cause?: ?string, resolution?: ?string, preventive_action?: ?string, erp_repair_request_id?: ?int, severity?: string}  $data
     *
     * @throws ValidationException
     */
    public function execute(MonitoringIncident $incident, array $data, ?User $actor): MonitoringIncident
    {
        $this->authorizeManage($actor, $incident);
        $validated = Validator::make($data, [
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'preventive_action' => ['nullable', 'string', 'max:5000'],
            'erp_repair_request_id' => ['nullable', 'integer', 'exists:erp_repair_requests,id'],
            'severity' => ['sometimes', 'string', 'in:sev1,sev2,sev3,sev4'],
        ])->validate();

        return DB::transaction(function () use ($incident, $validated, $actor): MonitoringIncident {
            $incident->fill($validated);
            $changed = array_keys($incident->getDirty());
            $incident->save();
            if ($changed !== []) {
                $this->recordEvent($incident, 'analysis_updated', $actor, metadata: ['fields' => $changed]);
            }

            return $incident;
        });
    }
}
