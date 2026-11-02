<?php

namespace App\Policies;

use App\Enums\StockCardStatus;
use App\Models\StockCard;
use App\Models\User;

class StockCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view stock cards');
    }

    public function view(User $user, StockCard $stockCard): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch($stockCard->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('create stock cards');
    }

    public function update(User $user, StockCard $stockCard): bool
    {
        return $user->can('edit stock cards') && $user->canAccessBranch($stockCard->branch_id);
    }

    public function createDraft(User $user): bool
    {
        return $user->is_active
            && $user->branch_id !== null
            && $user->can('access employee app stock card')
            && $this->create($user);
    }

    /** Staff can only change a report that is still a draft on their own branch. */
    public function editDraft(User $user, StockCard $stockCard): bool
    {
        return $user->is_active
            && $user->branch_id === $stockCard->branch_id
            && $user->can('access employee app stock card')
            && $stockCard->status === StockCardStatus::Draft
            && $this->update($user, $stockCard);
    }

    public function submit(User $user, StockCard $stockCard): bool
    {
        return $this->editDraft($user, $stockCard);
    }

    public function delete(User $user, StockCard $stockCard): bool
    {
        return $user->can('delete stock cards') && $user->canAccessBranch($stockCard->branch_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete stock cards');
    }

    public function restore(User $user, StockCard $stockCard): bool
    {
        return $this->update($user, $stockCard);
    }

    public function forceDelete(User $user, StockCard $stockCard): bool
    {
        return $this->delete($user, $stockCard);
    }

    public function reviewAsSupervisor(User $user, StockCard $stockCard): bool
    {
        return $this->view($user, $stockCard)
            && $stockCard->status === StockCardStatus::PendingSupervisor
            && $user->can('review stock cards as supervisor')
            && ($user->canAccessAllBranches() || $user->id !== $stockCard->submitted_by);
    }

    public function reviewAsFinance(User $user, StockCard $stockCard): bool
    {
        return $this->view($user, $stockCard)
            && $stockCard->status === StockCardStatus::PendingFinance
            && $user->can('review stock cards as finance')
            && ($user->canAccessAllBranches() || $user->id !== $stockCard->submitted_by);
    }

    public function refreshEsb(User $user, StockCard $stockCard): bool
    {
        return $this->view($user, $stockCard)
            && ($user->canAccessAllBranches() || $user->id !== $stockCard->submitted_by)
            && (($stockCard->status === StockCardStatus::PendingSupervisor && $user->can('review stock cards as supervisor'))
                || ($stockCard->status === StockCardStatus::PendingFinance && $user->can('review stock cards as finance')));
    }
}
