<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * "Mulai Proses": records the Purchasing PIC (PRD §11.14). A second processor cannot take a request
 * over; the same PIC clicking twice changes nothing.
 */
class ClaimMaterialSourcingRequestAction
{
    public function __construct(private readonly TransitionMaterialSourcingRequestAction $transition) {}

    /** @throws AuthorizationException|ValidationException */
    public function execute(User $actor, MaterialSourcingRequest $request): MaterialSourcingRequest
    {
        return DB::transaction(function () use ($actor, $request): MaterialSourcingRequest {
            $locked = MaterialSourcingRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === MaterialSourcingRequestStatus::InReview && $locked->assigned_to === $actor->id) {
                return $locked;
            }
            if ($locked->status !== MaterialSourcingRequestStatus::Submitted) {
                throw ValidationException::withMessages(['status' => 'Request sudah diproses '.($locked->assignee?->name ?? 'Purchasing lain').' atau statusnya berubah.']);
            }
            Gate::forUser($actor)->authorize('claim', $locked);

            return $this->transition->execute($locked, MaterialSourcingRequestStatus::InReview, $actor, null, [
                'assigned_to' => $actor->id,
                'claimed_at' => now(),
            ], ['event' => 'claimed']);
        });
    }
}
