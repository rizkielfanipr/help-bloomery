<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitoringIncidentSeverity;
use App\Enums\MonitoringIncidentStatus;
use App\Models\MonitoringAlert;
use App\Models\MonitoringIncident;
use App\Models\User;
use App\Services\Monitoring\MonitoringContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateMonitoringIncidentAction
{
    use RecordsIncidentEvents;

    /**
     * @param  array{title: string, summary?: ?string, severity: string, affected_services?: list<string>, owner_id?: ?int, erp_repair_request_id?: ?int, detected_at?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data, ?User $actor, bool $automatic = false): MonitoringIncident
    {
        $this->authorizeManage($actor);
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', Rule::enum(MonitoringIncidentSeverity::class)],
            'affected_services' => ['nullable', 'array'],
            'affected_services.*' => ['string', 'max:60'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'erp_repair_request_id' => ['nullable', 'integer', 'exists:erp_repair_requests,id'],
            'detected_at' => ['nullable', 'date'],
        ])->validate();

        return DB::transaction(function () use ($validated, $actor, $automatic): MonitoringIncident {
            $incident = MonitoringIncident::query()->create([
                'uuid' => (string) Str::uuid(),
                'environment' => MonitoringContext::environment(),
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'severity' => $validated['severity'],
                'status' => MonitoringIncidentStatus::Open,
                'affected_services' => array_values($validated['affected_services'] ?? []),
                'owner_id' => $validated['owner_id'] ?? null,
                'erp_repair_request_id' => $validated['erp_repair_request_id'] ?? null,
                'created_by' => $actor?->id,
                'is_automatic' => $automatic,
                'detected_at' => $validated['detected_at'] ?? now(),
            ]);
            $incident->forceFill(['number' => sprintf('INC-%s-%05d', now()->format('Ymd'), $incident->id)])->save();
            $this->recordEvent($incident, 'created', $actor, $automatic ? 'Dibuat otomatis dari alert.' : null, to: MonitoringIncidentStatus::Open->value, metadata: ['severity' => $validated['severity']]);

            return $incident;
        });
    }

    /**
     * Automatic incident for an alert, only when enabled in settings. The alert is linked; the
     * affected business transactions are never changed.
     */
    public function fromAlert(MonitoringAlert $alert): MonitoringIncident
    {
        return DB::transaction(function () use ($alert): MonitoringIncident {
            $incident = $this->execute([
                'title' => $alert->title,
                'summary' => $alert->safe_message,
                'severity' => $alert->severity->value,
                'affected_services' => $alert->source_type === 'service' ? [$alert->source_key] : [],
                'detected_at' => $alert->opened_at->toDateTimeString(),
            ], null, automatic: true);

            $alert->forceFill(['monitoring_incident_id' => $incident->id])->save();
            $incident->links()->create(['linkable_type' => 'alert', 'linkable_id' => $alert->id, 'label' => $alert->title]);

            return $incident;
        });
    }
}
