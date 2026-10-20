<?php

namespace App\Policies;

use App\Enums\KitchenProductionScheduleStatus;
use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\User;

/**
 * docs/kitchen-production-schedule-prd.md §6. Every ability needs its feature permission; viewing
 * additionally needs the record's Branch to be accessible (all-branch users and the
 * `view all branch kitchen production schedules` permission only widen the Branch scope), and
 * every mutation needs real access to the Branch via `canAccessBranch()`. No role is named here.
 *
 * `Gate::before` lets SUPERADMIN through every ability, including the state checks below, so the
 * Kitchen Actions enforce schedule state again on the locked row instead of relying on this class.
 */
class KitchenProductionSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view kitchen production schedules');
    }

    public function view(User $user, KitchenProductionSchedule $schedule): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->canAccessAllBranches()
            || $user->can('view all branch kitchen production schedules')
            || ($schedule->branch?->hasKitchenBrand() && $user->canAccessBranch($schedule->branch_id));
    }

    public function create(User $user): bool
    {
        return $user->can('create kitchen production schedules');
    }

    /**
     * A new schedule may only target an active Branch the user can actually access.
     */
    public function createForBranch(User $user, Branch $branch): bool
    {
        return $this->create($user)
            && $branch->is_active
            && ($user->canAccessAllBranches() || $branch->hasKitchenBrand())
            && $user->canAccessBranch($branch->id);
    }

    public function update(User $user, KitchenProductionSchedule $schedule): bool
    {
        return $user->can('update kitchen production schedules')
            && $schedule->status === KitchenProductionScheduleStatus::Scheduled
            && ($user->canAccessAllBranches() || $schedule->branch?->hasKitchenBrand())
            && $user->canAccessBranch($schedule->branch_id);
    }

    public function cancel(User $user, KitchenProductionSchedule $schedule): bool
    {
        return $user->can('cancel kitchen production schedules')
            && $schedule->status->canTransitionTo(KitchenProductionScheduleStatus::Cancelled)
            && ($user->canAccessAllBranches() || $schedule->branch?->hasKitchenBrand())
            && $user->canAccessBranch($schedule->branch_id);
    }
}
