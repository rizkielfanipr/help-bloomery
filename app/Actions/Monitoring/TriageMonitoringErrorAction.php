<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitoringErrorStatus;
use App\Models\MonitoringError;
use App\Models\User;
use App\Services\Monitoring\AlertManager;
use App\Services\Monitoring\MonitoringSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Error triage (docs/system-monitoring-prd.md §13.3). Ignored requires a reason; the actor and
 * time are recorded. Resolving or ignoring closes the error's active alert.
 */
class TriageMonitoringErrorAction
{
    public function __construct(private readonly MonitoringSanitizer $sanitizer, private readonly AlertManager $alerts) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(MonitoringError $error, MonitoringErrorStatus $status, ?string $note, User $actor): MonitoringError
    {
        if (! Gate::forUser($actor)->allows('triage', $error)) {
            throw new AuthorizationException('Anda tidak memiliki izin melakukan triage error.');
        }
        if ($status === MonitoringErrorStatus::Ignored && mb_strlen(trim((string) $note)) < 5) {
            throw ValidationException::withMessages(['note' => 'Alasan wajib diisi untuk status Ignored.']);
        }

        $error->forceFill([
            'status' => $status,
            'triaged_by' => $actor->id,
            'triaged_at' => now(),
            'triage_note' => $this->sanitizer->message($note, 1000),
        ])->save();
        $this->alerts->resolveForError($error);

        return $error;
    }
}
