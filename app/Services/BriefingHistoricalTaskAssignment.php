<?php

namespace App\Services;

use App\Enums\BriefingPeriod;
use App\Models\BriefingItem;
use App\Models\BriefingTask;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What a branch was assigned on a past occurrence, for legacy grouping. Legacy days were never
 * snapshotted and points were added and retired over time, so today's assignment is the wrong
 * yardstick: it would demand points that did not exist yet (turning complete days into drafts)
 * and reject points that were retired since (unassigned_task). The repository keeps no assignment
 * history, so it is rebuilt from the tasks table and the day's own items:
 *
 *  - scope: the branch's own tasks if any of them existed by then, otherwise the global ones —
 *    the same rule as BriefingTask::forPeriod();
 *  - a scoped task counts once it existed before the period's first day began (a point added
 *    during the day or mid-period is not required retroactively) and while it is still active or
 *    the day holds a submitted item for it;
 *  - any task the day holds a submitted item for counts, whatever its scope or state, because the
 *    submission is evidence it was assigned then. Empty rows the system created do not count, so
 *    a retired duplicate point is not revived by its placeholder slots.
 */
class BriefingHistoricalTaskAssignment
{
    /**
     * @param  Collection<int, BriefingItem>  $items  every item of the occurrence's legacy rows
     * @return Collection<int, BriefingTask>
     */
    public function tasksFor(int $branchId, BriefingPeriod $period, CarbonInterface $periodStart, Collection $items): Collection
    {
        $cutoff = CarbonImmutable::instance($periodStart)->startOfDay();
        $evidence = $items->filter(fn (BriefingItem $item): bool => $item->hasSubmittedContent())->pluck('task_key')->filter()->unique();
        $periodTasks = BriefingTask::cached()->where('period', $period);
        $existed = fn (BriefingTask $task): bool => $task->created_at === null || $task->created_at->lessThan($cutoff);

        $branchTasks = $periodTasks->filter(fn (BriefingTask $task): bool => $task->branch_id === $branchId && $existed($task));
        $scoped = ($branchTasks->isNotEmpty() ? $branchTasks : $periodTasks->filter(fn (BriefingTask $task): bool => $task->branch_id === null && $existed($task)))
            ->filter(fn (BriefingTask $task): bool => (bool) $task->is_active || $evidence->contains($task->key));

        $evidenced = $periodTasks
            ->filter(fn (BriefingTask $task): bool => $evidence->contains($task->key) && ($task->branch_id === null || $task->branch_id === $branchId))
            ->sortBy(fn (BriefingTask $task): int => $task->branch_id === $branchId ? 0 : 1);

        return $scoped->concat($evidenced)
            ->unique('key')
            ->sortBy('sort_order')
            ->values();
    }
}
