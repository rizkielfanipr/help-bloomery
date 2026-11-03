<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use App\Services\Purchasing\MaterialSourcingRequestNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The PIC returns a request for revision or rejects it (PRD §11.15). Both need a reason, which the
 * requester sees in their history.
 */
class DecideMaterialSourcingRequestAction
{
    public function __construct(
        private readonly TransitionMaterialSourcingRequestAction $transition,
        private readonly MaterialSourcingRequestNotifier $notifier,
    ) {}

    /** @throws AuthorizationException|ValidationException */
    public function requestRevision(User $actor, MaterialSourcingRequest $request, string $reason): MaterialSourcingRequest
    {
        $updated = $this->decide($actor, $request, $reason, MaterialSourcingRequestStatus::RevisionRequested, 'requestRevision', 'revision_requested');
        $this->notifier->revisionRequested($updated);

        return $updated;
    }

    /** @throws AuthorizationException|ValidationException */
    public function reject(User $actor, MaterialSourcingRequest $request, string $reason): MaterialSourcingRequest
    {
        $updated = $this->decide($actor, $request, $reason, MaterialSourcingRequestStatus::Rejected, 'reject', 'rejected');
        $this->notifier->rejected($updated);

        return $updated;
    }

    private function decide(User $actor, MaterialSourcingRequest $request, string $reason, MaterialSourcingRequestStatus $to, string $ability, string $event): MaterialSourcingRequest
    {
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi minimal 5 karakter.']);
        }

        return DB::transaction(function () use ($actor, $request, $reason, $to, $ability, $event): MaterialSourcingRequest {
            $locked = MaterialSourcingRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== MaterialSourcingRequestStatus::InReview) {
                throw ValidationException::withMessages(['reason' => 'Request tidak sedang diproses Purchasing.']);
            }
            Gate::forUser($actor)->authorize($ability, $locked);

            return $this->transition->execute($locked, $to, $actor, trim($reason), [
                'last_decision_reason' => trim($reason),
                'last_decision_by' => $actor->id,
                'last_decision_at' => now(),
            ], ['event' => $event]);
        });
    }
}
