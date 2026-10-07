<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\RndProject;
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
        // A PIC can always view their own assignment's Task, regardless of whether their role
        // otherwise holds `view rnd project tasks` (business rule: "PIC dapat melihat dan
        // merespons assignment miliknya").
        if ($task->assignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->canAccessAllBranches() || $user->can('view all branch rnd project tasks')) {
            return true;
        }

        return $task->branches->contains(fn (Branch $branch): bool => $user->canAccessBranch($branch->id));
    }

    public function create(User $user): bool
    {
        return $user->can('create rnd project tasks');
    }

    /**
     * Creating a Task inside a specific Project — archived (soft-deleted) Projects are read-only
     * (docs/rnd-project-checkpoint-calendar-prd.md §11.3).
     */
    public function createForProject(User $user, RndProject $project): bool
    {
        return $this->create($user) && ! $project->trashed();
    }

    /**
     * "Gunakan Template" needs both the apply and the create permission (§8.2).
     */
    public function applyTemplate(User $user, RndProject $project): bool
    {
        return $user->can('apply rnd project task templates') && $this->createForProject($user, $project);
    }

    /**
     * "Copy Task" needs the copy and create permission, read access to the source Task, and a
     * writable Project (§8.2, §14.3.2). Copies stay in the source Project on the MVP.
     */
    public function copy(User $user, RndProjectTask $task): bool
    {
        return $user->can('copy rnd project tasks')
            && $this->create($user)
            && $this->view($user, $task)
            && ! $task->belongsToArchivedProject();
    }

    public function update(User $user, RndProjectTask $task): bool
    {
        return $user->can('update rnd project tasks') && ! $task->belongsToArchivedProject();
    }

    public function assign(User $user, RndProjectTask $task): bool
    {
        return $user->can('assign rnd project tasks') && ! $task->belongsToArchivedProject();
    }

    public function cancel(User $user, RndProjectTask $task): bool
    {
        return $user->can('cancel rnd project tasks') && ! $task->status->isTerminal();
    }
}
