<?php

namespace App\Services;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingSubmissionType;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * docs/daily-briefing-bundle-approval-prd.md §15.2–§15.4/§15.6: decides what one legacy group becomes.
 * Conservative by design — anything that would need a guess (an existing bundle on the key, the
 * same point submitted twice, a point that is no longer assigned, mixed decisions, more than one
 * reviewer, decisions days apart, or a real submission the SLA rejected) goes to manual_review.
 * Pure: reads the rows it is given and never writes.
 */
class BriefingLegacyGroupingClassifier
{
    /** Item decisions further apart than this are not treated as one bundle decision. */
    public const MAX_REVIEW_SPREAD_HOURS = 72;

    public function __construct(
        private readonly BriefingCleanupClassifier $cleanup,
        private readonly BriefingBundleCompletenessService $completeness,
    ) {}

    /**
     * @param  Collection<int, BriefingRecord>  $legacyRecords  ungrouped rows of the group, items loaded
     * @param  Collection<int, BriefingTask>  $assignedTasks  what the branch is assigned for the period
     */
    public function classify(?int $branchId, BriefingPeriod $period, ?CarbonImmutable $periodStart, Collection $legacyRecords, ?BriefingRecord $existingBundle, Collection $assignedTasks): BriefingLegacyGroupingPlan
    {
        $sourceIds = $legacyRecords->sortBy('id')->modelKeys();
        $manual = fn (string $reason): BriefingLegacyGroupingPlan => new BriefingLegacyGroupingPlan(BriefingLegacyGroupingPlan::MANUAL_REVIEW, $branchId, $period, $periodStart, $sourceIds, $reason);

        if ($branchId === null || $periodStart === null) {
            return $manual('missing_branch_or_date');
        }

        $items = $legacyRecords->flatMap->items;
        $buckets = $this->cleanup->classifyMany($items);

        if ($buckets[BriefingCleanupClassifier::MANUAL_REVIEW]->isNotEmpty()) {
            return $manual('system_rejected_real_submission');
        }

        $real = $buckets[BriefingCleanupClassifier::VALID_SUBMISSION]->filter(fn (BriefingItem $item): bool => $this->wasSubmitted($item))->values();

        if ($real->isEmpty()) {
            $classification = $buckets[BriefingCleanupClassifier::SAFE_CLEANUP_CANDIDATE]->isNotEmpty()
                ? BriefingLegacyGroupingPlan::CLEANUP_CANDIDATE
                : BriefingLegacyGroupingPlan::MISSING;

            return new BriefingLegacyGroupingPlan($classification, $branchId, $period, $periodStart, $sourceIds);
        }

        if ($existingBundle !== null) {
            return $manual('existing_bundle_collision');
        }

        if ($real->countBy('task_key')->contains(fn (int $count): bool => $count > 1)) {
            return $manual('duplicate_task');
        }

        $tasksByKey = $assignedTasks->keyBy('key');

        if ($tasksByKey->isEmpty()) {
            return $manual('no_assignment');
        }

        if ($real->contains(fn (BriefingItem $item): bool => ! $tasksByKey->has($item->task_key))) {
            return $manual('unassigned_task');
        }

        $realByKey = $real->keyBy('task_key');
        $checkboxBackfill = $real
            ->filter(fn (BriefingItem $item): bool => $this->needsCheckboxBackfill($item, $tasksByKey->get($item->task_key)))
            ->map(fn (BriefingItem $item): int => (int) $item->id)
            ->values()
            ->all();
        $missingTaskKeys = $tasksByKey->reject(fn (BriefingTask $task): bool => $this->satisfies($task, $realByKey->get($task->key)))->keys()->values()->all();
        $isComplete = $missingTaskKeys === [];
        [$leftInSource, $canonicalId] = $this->resolveDuplicateSlots($items, $real, $legacyRecords, $periodStart);

        if ($canonicalId === null) {
            return $manual('duplicate_task');
        }

        $plan = fn (BriefingBundleStatus $status, array $extra = []): BriefingLegacyGroupingPlan => new BriefingLegacyGroupingPlan(...[
            'classification' => $status->value,
            'branchId' => $branchId,
            'period' => $period,
            'periodStart' => $periodStart,
            'sourceRecordIds' => $sourceIds,
            'canonicalRecordId' => $canonicalId,
            'status' => $status,
            'completedAt' => $status === BriefingBundleStatus::Draft ? null : $this->latest($real, 'completed_at', 'created_at'),
            'checkboxBackfillItemIds' => $checkboxBackfill,
            'leftInSourceItemIds' => $leftInSource,
            ...$extra,
        ]);

        if (! $isComplete) {
            return $plan(BriefingBundleStatus::Draft, ['missingTaskKeys' => $missingTaskKeys]);
        }

        $statuses = $real->map(fn (BriefingItem $item): ?BriefingReviewStatus => $item->review_status?->isAwaitingSupervisorReview() ? BriefingReviewStatus::SupervisorReview : $item->review_status)->unique();

        if ($statuses->count() !== 1) {
            return $manual('mixed_status');
        }

        return match ($statuses->first()) {
            BriefingReviewStatus::SupervisorReview => $plan(BriefingBundleStatus::SupervisorReview),
            BriefingReviewStatus::Approved, BriefingReviewStatus::Rejected => $this->decided($real, $statuses->first(), $plan, $manual),
            default => $manual('mixed_status'),
        };
    }

    /**
     * @param  Collection<int, BriefingItem>  $real
     * @param  callable(BriefingBundleStatus, array<string, mixed>): BriefingLegacyGroupingPlan  $plan
     * @param  callable(string): BriefingLegacyGroupingPlan  $manual
     */
    private function decided(Collection $real, BriefingReviewStatus $decision, callable $plan, callable $manual): BriefingLegacyGroupingPlan
    {
        $reviewers = $real->pluck('reviewed_by')->unique();
        $reviewedAt = $real->pluck('reviewed_at')->filter();

        if ($reviewers->count() !== 1 || $reviewers->first() === null || $reviewedAt->count() !== $real->count()) {
            return $manual('inconsistent_reviewer');
        }

        if ($reviewedAt->min()->diffInHours($reviewedAt->max()) > self::MAX_REVIEW_SPREAD_HOURS) {
            return $manual('inconsistent_review_time');
        }

        $extra = ['reviewedBy' => (int) $reviewers->first(), 'reviewedAt' => $reviewedAt->max()];

        if ($decision === BriefingReviewStatus::Approved) {
            return $plan(BriefingBundleStatus::Approved, $extra);
        }

        $reasons = $real->pluck('rejection_reason')->filter()->unique()->values();

        return $plan(BriefingBundleStatus::Rejected, [
            ...$extra,
            'reviewNotes' => $reasons->implode(' | ') ?: null,
            'corrections' => $real->mapWithKeys(fn (BriefingItem $item): array => [$item->id => (string) $item->rejection_reason])->all(),
        ]);
    }

    /**
     * Rows nobody acted on (no status, nothing attached) are placeholders, not submissions.
     */
    private function wasSubmitted(BriefingItem $item): bool
    {
        return $item->review_status !== null || $item->is_completed || ! empty($item->photo_paths) || filled($item->notes);
    }

    /**
     * Before Phase 4, a ticked checkbox was stored without is_completed/completed_at; the row itself
     * is the evidence of the tick, so its created_at is the submission time.
     */
    private function needsCheckboxBackfill(BriefingItem $item, ?BriefingTask $task): bool
    {
        return $task?->submission_type === BriefingSubmissionType::Checkbox
            && $item->review_status !== null
            && (! $item->is_completed || $item->completed_at === null);
    }

    private function satisfies(BriefingTask $task, ?BriefingItem $item): bool
    {
        if ($item !== null && $this->needsCheckboxBackfill($item, $task)) {
            return true;
        }

        return $this->completeness->itemSatisfies($task->submission_type, $item);
    }

    /**
     * The row already sitting on the business key wins; otherwise the oldest row. Only reached once
     * the group has no conflicting evidence (no duplicate points, no mixed decisions).
     *
     * @param  Collection<int, BriefingRecord>  $records
     */
    /**
     * A point can sit in more than one source row when an empty or system-rejected slot was created
     * next to the real submission. Only one item per point may enter the bundle — the real one, else
     * the oldest — and the others stay in their own source row untouched. The canonical row must
     * therefore hold none of those leftovers; when every row does, the group needs a person.
     *
     * @param  Collection<int, BriefingItem>  $items
     * @param  Collection<int, BriefingItem>  $real
     * @param  Collection<int, BriefingRecord>  $records
     * @return array{0: list<int>, 1: ?int} leftover item ids, canonical record id
     */
    private function resolveDuplicateSlots(Collection $items, Collection $real, Collection $records, CarbonImmutable $periodStart): array
    {
        $realIds = $real->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $leftInSource = $items->groupBy('task_key')
            ->filter(fn (Collection $sameKey): bool => $sameKey->count() > 1)
            ->flatMap(function (Collection $sameKey) use ($realIds): Collection {
                $keeper = $sameKey->first(fn (BriefingItem $item): bool => in_array((int) $item->id, $realIds, true))
                    ?? $sameKey->sortBy('id')->first();

                return $sameKey->reject(fn (BriefingItem $item): bool => $item->id === $keeper->id)->pluck('id');
            })
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $rowsWithLeftovers = $items->whereIn('id', $leftInSource)->pluck('briefing_record_id')->map(fn ($id): int => (int) $id)->unique();
        $preferred = $this->canonicalId($records, $periodStart);

        if (! $rowsWithLeftovers->contains($preferred)) {
            return [$leftInSource, $preferred];
        }

        $fallback = $records->reject(fn (BriefingRecord $record): bool => $rowsWithLeftovers->contains((int) $record->id))->min('id');

        return [$leftInSource, $fallback !== null ? (int) $fallback : null];
    }

    private function canonicalId(Collection $records, CarbonImmutable $periodStart): int
    {
        $onKey = $records->filter(fn (BriefingRecord $record): bool => $record->record_date->isSameDay($periodStart));

        return (int) ($onKey->count() === 1 ? $onKey->first()->id : $records->min('id'));
    }

    /**
     * @param  Collection<int, BriefingItem>  $items
     */
    private function latest(Collection $items, string $column, string $fallback): ?CarbonImmutable
    {
        $latest = $items->map(fn (BriefingItem $item) => $item->{$column} ?? $item->{$fallback})->filter()->max();

        return $latest?->toImmutable();
    }
}
