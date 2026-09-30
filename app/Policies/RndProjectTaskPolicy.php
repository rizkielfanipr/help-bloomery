<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\RndProjectTask;
use App\Models\User;

/**
 * Permission strings match docs/rnd-project-task-calendar-prd.md §7. Branch visibility falls
 * back to `Branch\Model::canAccessBranch()`/`canAccessAllBranches()` — the same mechanism every
 * other branch-scoped Policy in this app uses (e.g. `StockCardPolicy`) — plus the
 * `view all branch rnd project tasks` permission for roles that should see every Task without
 * holding blanket branch access (business rule #19).
 */
class RndProjectTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view rnd project tasks');
    }

    public function view(User $user, RndProjectTask $task): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->canAccessAllBranches() || $user->can('view all branch rnd project tasks')) {
            return true;
        }

        if ($task->assignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $task->branches->contains(fn (Branch $branch): bool => $user->canAccessBranch($branch->id));
    }

    public function create(User $user): bool
    {
        return $user->can('create rnd project tasks');
    }

    public function update(User $user, RndProjectTask $task): bool
    {
        return $user->can('update rnd project tasks');
    }

    public function assign(User $user, RndProjectTask $task): bool
    {
        return $user->can('assign rnd project tasks');
    }

    public function cancel(User $user, RndProjectTask $task): bool
    {
        return $user->can('cancel rnd project tasks') && ! $task->status->isTerminal();
    }
}
