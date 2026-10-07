<?php

namespace App\Services\Rnd\ProjectTask;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Resolves which users are eligible to be a Task's PIC for a given Branch
 * (docs/rnd-project-task-calendar-prd.md §6). Eligibility is: active, can access the Branch, holds
 * `respond rnd project tasks` (otherwise they could never follow the Task up), and not a blanket
 * all-branch/SUPERADMIN user (business rules #6, #8) — those users may still view and manage
 * Tasks, but are never offered as a PIC candidate.
 */
class ProjectTaskAssigneeResolver
{
    public const RESPOND_PERMISSION = 'respond rnd project tasks';

    /** @return Collection<int, User> */
    public function eligibleUsersForBranch(int $branchId, string $search = ''): Collection
    {
        if (! Permission::query()->where('name', self::RESPOND_PERMISSION)->exists()) {
            return collect();
        }

        $search = trim($search);

        return User::query()
            ->where('is_active', true)
            ->where('access_all_branches', false)
            ->permission(self::RESPOND_PERMISSION)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%");
            }))
            ->get()
            ->filter(fn (User $user): bool => $this->canWorkInBranch($user, $branchId))
            ->values();
    }

    public function isEligible(User $user, int $branchId): bool
    {
        return $this->canWorkInBranch($user, $branchId) && $user->can(self::RESPOND_PERMISSION);
    }

    private function canWorkInBranch(User $user, int $branchId): bool
    {
        return $user->is_active
            && ! $user->canAccessAllBranches()
            && $user->canAccessBranch($branchId);
    }

    /**
     * Active users who can review follow-ups for a Branch — anyone holding the review permission
     * who can access it, including all-branch/SUPERADMIN users (unlike PIC eligibility, reviewers
     * are not restricted to branch-only staff).
     *
     * @return Collection<int, User>
     */
    public function reviewersForBranch(int $branchId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission('review rnd project task follow ups')
            ->get()
            ->filter(fn (User $user): bool => $user->canAccessBranch($branchId))
            ->values();
    }
}
