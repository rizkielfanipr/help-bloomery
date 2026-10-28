<?php

namespace App\Services;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\Branch;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * docs/products/daily-briefing/remediation/prd.md §11/§13/§14/§15 (Phase 7). The single definition of
 * submission status shared by the scheduler, both calendars, scoring, and reports, so the same
 * branch/period/date always evaluates to the same status everywhere. `missing`, `not_due`, and
 * `not_applicable` are always derived from assignment + deadline + absence of a submission — never
 * a stored `review_status` value (§11.1: no synthetic row is created to represent them).
 */
class BriefingStatusService
{
    public function __construct(private readonly BriefingAssignmentService $assignments) {}

    /**
     * Status of a single task's submission (or lack of one) on $recordDate.
     */
    public function itemStatus(BriefingTask $task, ?BriefingItem $item, CarbonInterface $recordDate): string
    {
        if ($item !== null) {
            return match ($item->review_status) {
                BriefingReviewStatus::Approved => 'approved',
                BriefingReviewStatus::Rejected => $item->hasOpenReopenGrant() ? 'supervisor_review' : 'rejected',
                default => 'supervisor_review',
            };
        }

        return $task->isPastDeadline() ? 'missing' : 'not_due';
    }

    /**
     * Aggregate status for a branch's whole period/date obligation: not_applicable when the
     * branch isn't a participant or has no assigned tasks for the period; otherwise rolled up
     * from each assigned task's itemStatus (rejected beats everything, then missing, then
     * supervisor_review, then not_due, and only approved when every task is approved).
     */
    public function branchPeriodStatus(Branch $branch, BriefingPeriod $period, CarbonInterface $recordDate): string
    {
        $tasks = $this->assignments->taskAssignmentsForBranch($branch, $period, $recordDate);

        if ($tasks->isEmpty()) {
            return 'not_applicable';
        }

        $record = BriefingRecord::canonicalForBranch((int) $branch->id, $period, $recordDate)?->load('items');
        $itemsByKey = $record?->items->keyBy('task_key') ?? collect();

        $statuses = $tasks->map(fn (BriefingTask $task): string => $this->itemStatus($task, $itemsByKey->get($task->key), $recordDate));

        return $this->rollUp($statuses);
    }

    /**
     * @param  Collection<int, string>  $statuses
     */
    private function rollUp(Collection $statuses): string
    {
        if ($statuses->contains('rejected')) {
            return 'rejected';
        }

        if ($statuses->every(fn (string $s): bool => $s === 'approved')) {
            return 'approved';
        }

        if ($statuses->contains('supervisor_review')) {
            return 'supervisor_review';
        }

        if ($statuses->contains('missing')) {
            return 'missing';
        }

        return 'not_due';
    }
}
