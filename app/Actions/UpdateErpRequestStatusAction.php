<?php

namespace App\Actions;

use App\Enums\ItRequestStatus;
use App\Models\ErpRepairRequest;
use App\Models\User;
use App\Services\ErpRequestSlaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateErpRequestStatusAction
{
    public function __construct(public ErpRequestSlaService $sla) {}

    public function execute(ErpRepairRequest $request, ItRequestStatus $status, ?string $notes, User $actor, string $statusErrorKey = 'status', string $notesErrorKey = 'itNotes'): ErpRepairRequest
    {
        return DB::transaction(function () use ($request, $status, $notes, $actor, $statusErrorKey, $notesErrorKey): ErpRepairRequest {
            $request = ErpRepairRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if (! $request->status->canTransitionTo($status)) {
                throw ValidationException::withMessages([$statusErrorKey => 'Status must follow the configured workflow sequence.']);
            }

            $notes = trim($notes ?? '');
            if ($status === ItRequestStatus::Rejected && blank($notes)) {
                throw ValidationException::withMessages([$notesErrorKey => 'Alasan penolakan wajib diisi.']);
            }

            $previousStatus = $request->status;
            if ($previousStatus === ItRequestStatus::Submitted && $status === ItRequestStatus::Review && ! $request->first_responded_at) {
                $request->first_responded_at = now();
            }

            if ($status === ItRequestStatus::Completed && $previousStatus !== ItRequestStatus::Completed) {
                $request->resolved_at = now();
                $request->closed_by = $actor->getKey();
            }

            $request->status = $status;
            $request->it_notes = $notes ?: null;
            $this->sla->calculate($request);
            $request->save();
            $request->activities()->create([
                'actor_id' => $actor->getKey(),
                'action' => $previousStatus === $status ? 'follow_up_updated' : 'status_changed',
                'from_status' => $previousStatus->value,
                'to_status' => $status->value,
                'notes' => $request->it_notes,
            ]);

            return $request;
        });
    }
}
