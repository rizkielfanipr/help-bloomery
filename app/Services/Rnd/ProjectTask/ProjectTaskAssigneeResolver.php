<?php

namespace App\Services\Rnd\ProjectTask;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves which users are eligible to be a Task's PIC for a given Branch
 * (docs/rnd-project-task-calendar-prd.md §6). Eligibility is: active, can access the Branch, and
 * not a blanket all-branch/SUPERADMIN user (business rules #6, #8) — those users may still view
 * and manage Tasks, but are never offered as a PIC candidate.
 */
class ProjectTaskAssigneeResolver
{
    /** @return Collection<int, User> */
    public function eligibleUsersForBranch(int $branchId, string $search = ''): Collection
    {
        $search = trim($search);

        return User::query()
            ->where('is_active', true)
            ->where('access_all_branches', false)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%");
            }))
            ->get()
            ->filter(fn (User $user): bool => $this->isEligible($user, $branchId))
            ->values();
    }

    public function isEligible(User $user, int $branchId): bool
    {
        return $user->is_active
            && ! $user->canAccessAllBranches()
            && $user->canAccessBranch($branchId);
    }
}
