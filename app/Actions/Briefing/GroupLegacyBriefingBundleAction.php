<?php

namespace App\Actions\Briefing;

use App\Enums\BriefingTransitionSource;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingRecordTransition;
use App\Models\BriefingTask;
use App\Services\BriefingHistoricalTaskAssignment;
use App\Services\BriefingLegacyGroupingClassifier;
use App\Services\BriefingLegacyGroupingPlan;
use App\Services\BriefingPeriodNormalizer;
use App\Services\BriefingScoreResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * docs/daily-briefing-bundle-approval-prd.md §15.5/§15.6 (Phase 9): turns one classified legacy group
 * into a bundle, in its own transaction. The group is re-read under lock and re-classified first, so
 * a plan made during a long dry-run never writes stale data. Source rows are kept (emptied, never
 * deleted; a duplicate slot of a point already in the bundle stays in its row), every item id that moved is recorded, and the returned snapshot is enough to put the
 * group back exactly as it was. Never recomputes scores.
 */
class GroupLegacyBriefingBundleAction
{
    public function __construct(
        private readonly BriefingLegacyGroupingClassifier $classifier,
        private readonly BriefingPeriodNormalizer $periods,
        private readonly BriefingHistoricalTaskAssignment $assignments,
    ) {}

    /**
     * @return array<string, mixed>|null the rollback snapshot entry, or null when the group no longer qualifies
     */
    public function apply(BriefingLegacyGroupingPlan $plan, string $runId): ?array
    {
        return DB::transaction(function () use ($plan, $runId): ?array {
            $records = BriefingRecord::query()->whereKey($plan->sourceRecordIds)->whereNull('status')->lockForUpdate()->with('items')->get();

            if ($records->count() !== count($plan->sourceRecordIds)) {
                return null;
            }

            $existing = BriefingRecord::query()
                ->where('branch_id', $plan->branchId)
                ->where('period', $plan->period->value)
                ->whereNotNull('status')
                ->whereDate('period_start', $plan->periodStart)
                ->lockForUpdate()
                ->first();
            $tasks = $this->assignments->tasksFor($plan->branchId, $plan->period, $plan->periodStart, $records->flatMap->items);

            $fresh = $this->classifier->classify($plan->branchId, $plan->period, CarbonImmutable::instance($plan->periodStart), $records, $existing, $tasks);

            if (! $fresh->isMutating() || $fresh->classification !== $plan->classification || $fresh->canonicalRecordId !== $plan->canonicalRecordId) {
                return null;
            }

            $canonical = $records->firstWhere('id', $fresh->canonicalRecordId);
            $items = $records->flatMap->items->reject(fn (BriefingItem $item): bool => in_array((int) $item->id, $fresh->leftInSourceItemIds, true));
            $snapshot = $this->snapshot($runId, $canonical, $items);
            $ownerByRecord = $records->pluck('user_id', 'id');
            $movedItemIds = $items->where('briefing_record_id', '!=', $canonical->id)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            foreach ($items as $item) {
                $item->forceFill(['briefing_record_id' => $canonical->id]);

                if ($item->hasSubmittedContent()) {
                    $item->forceFill([
                        'submitted_by' => $item->submitted_by ?? $ownerByRecord[$item->getOriginal('briefing_record_id')],
                        'submitted_at' => $item->submitted_at ?? $item->completed_at ?? $item->created_at,
                    ]);
                }

                if (in_array($item->id, $fresh->checkboxBackfillItemIds, true)) {
                    $item->forceFill(['is_completed' => true, 'completed_at' => $item->completed_at ?? $item->created_at]);
                }

                if (array_key_exists($item->id, $fresh->corrections)) {
                    $item->forceFill(['requires_correction' => true, 'correction_note' => $fresh->corrections[$item->id] ?: null]);
                }

                $item->save();
            }

            $canonical->forceFill([
                'period_start' => $fresh->periodStart->toDateString(),
                'period_end' => $this->periods->end($fresh->period, $fresh->periodStart)->toDateString(),
                'status' => $fresh->status->value,
                'completed_at' => $fresh->completedAt,
                'reviewed_by' => $fresh->reviewedBy,
                'reviewed_at' => $fresh->reviewedAt,
                'review_notes' => $fresh->reviewNotes,
                'scoring_method_version' => BriefingScoreResult::BUNDLE_METHOD_VERSION,
            ])->save();

            $canonical->requirements()->createMany(
                $tasks->map(fn (BriefingTask $task): array => BriefingRecordRequirement::attributesFromTask($task))->all(),
            );

            $canonical->transitions()->create([
                'from_status' => null,
                'to_status' => $fresh->status->value,
                'actor_id' => null,
                'reason' => 'Dikelompokkan dari data lama.',
                'source' => BriefingTransitionSource::Migration->value,
                'metadata' => [
                    'run_id' => $runId,
                    'classification' => $fresh->classification,
                    'source_record_ids' => $fresh->sourceRecordIds,
                    'moved_item_ids' => $movedItemIds,
                    'checkbox_backfill_item_ids' => $fresh->checkboxBackfillItemIds,
                    'left_in_source_item_ids' => $fresh->leftInSourceItemIds,
                ],
            ]);

            return $snapshot;
        });
    }

    /**
     * Puts one group back exactly as the snapshot recorded it and removes what the run added.
     *
     * @param  array<string, mixed>  $entry
     */
    public function restore(array $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $canonicalId = $entry['canonical']['id'];

            BriefingRecordTransition::query()
                ->where('briefing_record_id', $canonicalId)
                ->where('source', BriefingTransitionSource::Migration->value)
                ->get()
                ->filter(fn (BriefingRecordTransition $transition): bool => ($transition->metadata['run_id'] ?? null) === $entry['run_id'])
                ->each->delete();

            BriefingRecordRequirement::query()->where('briefing_record_id', $canonicalId)->delete();

            foreach ($entry['items'] as $item) {
                BriefingItem::query()->whereKey($item['id'])->update(collect($item)->except('id')->all());
            }

            BriefingRecord::query()->whereKey($canonicalId)->update(collect($entry['canonical'])->except('id')->all());
        });
    }

    /**
     * @param  Collection<int, BriefingItem>  $items
     * @return array<string, mixed>
     */
    private function snapshot(string $runId, BriefingRecord $canonical, $items): array
    {
        return [
            'run_id' => $runId,
            'canonical' => collect(['id', 'period_start', 'period_end', 'status', 'completed_at', 'reviewed_by', 'reviewed_at', 'review_notes', 'scoring_method_version'])
                ->mapWithKeys(fn (string $column): array => [$column => $canonical->getRawOriginal($column)])
                ->all(),
            'items' => $items->map(fn (BriefingItem $item): array => [
                'id' => $item->id,
                'briefing_record_id' => $item->briefing_record_id,
                'submitted_by' => $item->submitted_by,
                'submitted_at' => $item->getRawOriginal('submitted_at'),
                'is_completed' => $item->getRawOriginal('is_completed'),
                'completed_at' => $item->getRawOriginal('completed_at'),
                'requires_correction' => $item->getRawOriginal('requires_correction'),
                'correction_note' => $item->correction_note,
            ])->values()->all(),
        ];
    }
}
