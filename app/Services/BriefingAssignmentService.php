<?php

namespace App\Services;

use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * docs/daily-briefing-remediation-prd.md §9. Single source of truth for Daily Briefing
 * participation and task assignment, used by the submission page, scheduler, calendars,
 * monitoring, scoring, and report/export so every consumer evaluates the same user/task/period/date
 * input to the same result. Framework-agnostic: no Filament/Livewire dependency.
 */
class BriefingAssignmentService
{
    /**
     * §6/§8.2: a participant is an active STORE_STAFF user with a branch whose branch is an
     * explicit, enabled participant on $date. Task availability never implies participation.
     */
    public function isParticipant(User $user, CarbonInterface $date): bool
    {
        if (! $user->is_active || ! $user->hasRole('STORE_STAFF') || ! $user->branch_id) {
            return false;
        }

        return $this->isBranchParticipant((int) $user->branch_id, $date);
    }

    public function isBranchParticipant(int $branchId, CarbonInterface $date): bool
    {
        return BriefingBranchSetting::query()
            ->where('branch_id', $branchId)
            ->where('is_enabled', true)
            ->whereDate('started_at', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date))
            ->exists();
    }

    /**
     * §14.1: whether the branch was a participant on any day within [$start, $end], used to
     * decide whether a period (e.g. a scoring month) has any obligation for the branch at all —
     * per-day gating within that range is still needed since participation may start or end
     * partway through it.
     */
    public function hasParticipationOverlapping(int $branchId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return BriefingBranchSetting::query()
            ->where('branch_id', $branchId)
            ->where('is_enabled', true)
            ->whereDate('started_at', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $start))
            ->exists();
    }

    /**
     * §9.1: empty when the user is not a participant; otherwise the branch's own active tasks for
     * the period, falling back to the active global tasks when the branch has none.
     *
     * @return Collection<int, BriefingTask>
     */
    public function tasksFor(User $user, BriefingPeriod $period, CarbonInterface $recordDate): Collection
    {
        if (! $this->isParticipant($user, $recordDate)) {
            return collect();
        }

        return BriefingTask::forPeriod($period, (int) $user->branch_id);
    }

    public function taskIsAssignedTo(User $user, BriefingTask $task, CarbonInterface $recordDate): bool
    {
        return $this->tasksFor($user, $task->period, $recordDate)->contains('key', $task->key);
    }

    /**
     * §9: active STORE_STAFF users whose own branch is $branch, when that branch is a participant
     * on $date. Supervisors/HR are never participants regardless of accessibleBranchIds().
     *
     * @return Collection<int, User>
     */
    public function participantsForBranch(Branch $branch, CarbonInterface $date): Collection
    {
        if (! $this->isBranchParticipant((int) $branch->id, $date)) {
            return collect();
        }

        return User::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->role('STORE_STAFF')
            ->get();
    }

    /**
     * @return Collection<int, BriefingTask>
     */
    public function taskAssignmentsForBranch(Branch $branch, BriefingPeriod $period, CarbonInterface $recordDate): Collection
    {
        if (! $this->isBranchParticipant((int) $branch->id, $recordDate)) {
            return collect();
        }

        return BriefingTask::forPeriod($period, (int) $branch->id);
    }
}
