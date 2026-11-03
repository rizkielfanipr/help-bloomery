<?php

namespace App\Policies;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\User;

/**
 * docs/products/purchasing/employee-sourcing-request/prd.md §9/§16: requesters only reach their own
 * requests; Purchasing reaches the queue by permission, and only the PIC who started processing
 * decides. Actions repeat these checks and the status invariants.
 */
class MaterialSourcingRequestPolicy
{
    public function accessEmployeeApp(User $user): bool
    {
        return $user->can('access employee app sourcing request');
    }

    public function create(User $user): bool
    {
        return $this->accessEmployeeApp($user);
    }

    public function viewOwn(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->accessEmployeeApp($user) && $request->requester_id === $user->id;
    }

    public function resubmit(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->viewOwn($user, $request) && $request->status === MaterialSourcingRequestStatus::RevisionRequested;
    }

    public function cancel(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->viewOwn($user, $request) && $request->status->canTransitionTo(MaterialSourcingRequestStatus::Cancelled);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view material sourcing requests');
    }

    public function view(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->viewAny($user) && $request->company_code === MaterialSourcingRequest::COMPANY_CODE;
    }

    public function downloadAttachment(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->viewOwn($user, $request) || $this->view($user, $request);
    }

    public function claim(User $user, MaterialSourcingRequest $request): bool
    {
        return $user->can('process material sourcing requests') && $request->status === MaterialSourcingRequestStatus::Submitted;
    }

    public function requestRevision(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->decidesAsPic($user, $request);
    }

    public function reject(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->decidesAsPic($user, $request);
    }

    public function convert(User $user, MaterialSourcingRequest $request): bool
    {
        return $this->decidesAsPic($user, $request) && $user->can('submit material sourcing');
    }

    public function update(User $user, MaterialSourcingRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, MaterialSourcingRequest $request): bool
    {
        return false;
    }

    private function decidesAsPic(User $user, MaterialSourcingRequest $request): bool
    {
        return $user->can('process material sourcing requests')
            && $request->status === MaterialSourcingRequestStatus::InReview
            && $request->assigned_to === $user->id;
    }
}
