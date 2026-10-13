<?php

namespace App\Services;

use App\Enums\BriefingPeriod;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use Carbon\CarbonImmutable;
use Generator;

/**
 * docs/daily-briefing-bundle-approval-prd.md §15.2: finds legacy groups (branch + period + normalized
 * period_start) and plans each one. Shared by the grouping command and the score compare report, so
 * both count manual-review groups the same way. Read-only.
 */
class BriefingLegacyGroupingScanner
{
    public function __construct(
        private readonly BriefingLegacyGroupingClassifier $classifier,
        private readonly BriefingPeriodNormalizer $periods,
    ) {}

    /**
     * Keys are discovered from the filtered legacy rows; each group then holds every legacy row on
     * its key, because a weekly group can include rows dated outside the filtered month. Rows with
     * no branch are their own group each.
     *
     * @return Generator<string, array{branch_id: ?int, period: BriefingPeriod, period_start: ?CarbonImmutable, record_id: int}>
     */
    public function groups(?int $branchId = null, ?int $year = null, ?int $month = null, int $chunk = 500): Generator
    {
        $keys = [];

        BriefingRecord::query()
            ->whereNull('status')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($year, fn ($query) => $query->whereYear('record_date', $year))
            ->when($month, fn ($query) => $query->whereMonth('record_date', $month))
            ->select(['id', 'branch_id', 'period', 'record_date'])
            ->lazyById(max(1, $chunk))
            ->each(function (BriefingRecord $record) use (&$keys): void {
                $start = $record->branch_id !== null ? $this->periods->start($record->period, $record->record_date) : null;
                $key = ($record->branch_id ?? 'none').'|'.$record->period->value.'|'.($start?->toDateString() ?? 'none').($record->branch_id === null ? '|'.$record->id : '');

                $keys[$key] ??= ['branch_id' => $record->branch_id, 'period' => $record->period, 'period_start' => $start, 'record_id' => $record->id];
            });

        ksort($keys);

        foreach ($keys as $key => $group) {
            yield $key => $group;
        }
    }

    /**
     * @param  array{branch_id: ?int, period: BriefingPeriod, period_start: ?CarbonImmutable, record_id: int}  $group
     */
    public function plan(array $group): BriefingLegacyGroupingPlan
    {
        if ($group['branch_id'] === null) {
            $records = BriefingRecord::query()->whereKey($group['record_id'])->with('items')->get();

            return $this->classifier->classify(null, $group['period'], null, $records, null, collect());
        }

        $start = $group['period_start'];
        $end = $this->periods->end($group['period'], $start);

        $records = BriefingRecord::query()
            ->whereNull('status')
            ->where('branch_id', $group['branch_id'])
            ->where('period', $group['period']->value)
            ->whereDate('record_date', '>=', $start)
            ->whereDate('record_date', '<=', $end)
            ->with('items')
            ->get();

        $existing = BriefingRecord::query()
            ->where('branch_id', $group['branch_id'])
            ->where('period', $group['period']->value)
            ->whereNotNull('status')
            ->whereDate('period_start', $start)
            ->first();

        return $this->classifier->classify($group['branch_id'], $group['period'], $start, $records, $existing, BriefingTask::forPeriod($group['period'], $group['branch_id']));
    }
}
