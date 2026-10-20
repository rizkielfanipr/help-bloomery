<?php

namespace App\Actions\Monitoring;

use App\Models\MonitoringIncident;
use App\Models\MonitoringIncidentEvent;
use App\Models\User;
use App\Services\Monitoring\MonitoringSanitizer;
use Illuminate\Validation\ValidationException;

class AddMonitoringIncidentNoteAction
{
    use RecordsIncidentEvents;

    public function __construct(private readonly MonitoringSanitizer $sanitizer) {}

    /**
     * @throws ValidationException
     */
    public function execute(MonitoringIncident $incident, string $note, ?User $actor): MonitoringIncidentEvent
    {
        $this->authorizeManage($actor, $incident);
        if (mb_strlen(trim($note)) < 3 || mb_strlen($note) > 5000) {
            throw ValidationException::withMessages(['note' => 'Catatan wajib 3–5000 karakter.']);
        }

        return $this->recordEvent($incident, 'note', $actor, $this->sanitizer->message($note, 5000));
    }
}
