<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** The requester withdraws a request Purchasing has not started (PRD §11.11), with a reason. */
class CancelMaterialSourcingRequestAction
{
    public function __construct(private readonly TransitionMaterialSourcingRequestAction $transition) {}

    /** @throws AuthorizationException|ValidationException */
    public function execute(User $actor, MaterialSourcingRequest $request, string $reason): MaterialSourcingRequest
    {
        if ($request->requester_id !== $actor->id) {
            throw new AuthorizationException('Request ini bukan milik Anda.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['cancelReason' => 'Alasan pembatalan minimal 5 karakter.']);
        }

        return DB::transaction(function () use ($actor, $request, $reason): MaterialSourcingRequest {
            $locked = MaterialSourcingRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === MaterialSourcingRequestStatus::Cancelled) {
                return $locked;
            }
            if (! $locked->status->canTransitionTo(MaterialSourcingRequestStatus::Cancelled)) {
                throw ValidationException::withMessages(['cancelReason' => 'Request sudah diproses Purchasing dan tidak dapat dibatalkan.']);
            }
            Gate::forUser($actor)->authorize('cancel', $locked);

            return $this->transition->execute($locked, MaterialSourcingRequestStatus::Cancelled, $actor, trim($reason), [
                'last_decision_reason' => trim($reason),
                'last_decision_by' => $actor->id,
                'last_decision_at' => now(),
            ], ['event' => 'cancelled']);
        });
    }
}
