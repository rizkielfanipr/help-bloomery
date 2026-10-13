<?php

namespace App\Services;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * docs/daily-briefing-bundle-approval-prd.md §11 (method_version 3). One unit = one branch bundle
 * per period occurrence. For each period:
 *
 *   rate = approved bundles ÷ expected occurrences × 100
 *
 * The denominator is the whole month from the start: every day, every week whose Monday falls in
 * the month, and the month itself — as long as the branch was a participant on its period_start,
 * it carried at least one scored point, and it falls on/after scoring_started_at. A running month
 * therefore shows its full target and climbs as bundles are approved. An occurrence with no bundle
 * is derived, never a row: `missing` once its period has ended, `upcoming` while it has not. A
 * month that has not started yet is not applicable. Weekly occurrences belong to the month of
 * their Monday, matching method_version 2. Each period also lists its occurrences for the detail.
 *
 * The repository has no operational calendar (holidays/closures); participation windows are the
 * only availability source, so every day inside a window counts. Pure and deterministic: it reads
 * but never writes.
 */
class BriefingBundleScoreCalculator
{
    private const STATUS_MISSING = 'missing';

    private const STATUS_UPCOMING = 'upcoming';

    public function __construct(private readonly BriefingPeriodNormalizer $periods) {}

    public function calculate(Branch $branch, int $year, int $month): BriefingScoreResult
    {
        $now = now()->toImmutable();
        $branchId = (int) $branch->id;
        $monthStart = CarbonImmutable::create($year, $month)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $settings = BriefingSettings::instance();
        $scoringStartedAt = $settings->scoring_started_at?->toImmutable()->startOfDay();

        $countFrom = $scoringStartedAt !== null && $scoringStartedAt->greaterThan($monthStart) ? $scoringStartedAt : $monthStart;
        $isProvisional = $now->lessThanOrEqualTo($monthEnd->endOfDay());

        $notApplicable = fn (): BriefingScoreResult => BriefingScoreResult::notApplicable(
            $branchId, $year, $month, $countFrom->toDateString(), $monthEnd->toDateString(), $now, BriefingScoreResult::BUNDLE_METHOD_VERSION,
        );

        if ($countFrom->greaterThan($monthEnd) || $monthStart->greaterThan($now)) {
            return $notApplicable();
        }

        $participation = BriefingBranchSetting::query()->where('branch_id', $branchId)->get();
        $bundles = $this->bundles($branchId, $monthStart, $monthEnd);
        $weights = BriefingPeriodWeight::forBranch($branchId);

        $periodsData = [];
        foreach (BriefingPeriod::cases() as $period) {
            $scoredAssignment = BriefingTask::forPeriod($period, $branchId)->contains(fn (BriefingTask $task): bool => (bool) $task->include_in_score);
            $statuses = [];
            $occurrences = [];

            foreach ($this->occurrenceStarts($period, $monthStart, $monthEnd, $countFrom) as $start) {
                if (! $participation->contains(fn (BriefingBranchSetting $setting): bool => $setting->coversDate($start))) {
                    continue;
                }

                $bundle = $bundles->get($period->value.'|'.$start->toDateString());

                if (! $this->isScored($bundle, $scoredAssignment)) {
                    continue;
                }

                $status = $bundle?->status->value
                    ?? ($this->isDue($period, $start, $now) ? self::STATUS_MISSING : self::STATUS_UPCOMING);

                $statuses[] = $status;
                $occurrences[] = [
                    'start' => $start->toDateString(),
                    'end' => $this->periods->end($period, $start)->toDateString(),
                    'status' => $status,
                    'bundle_id' => $bundle?->id,
                ];
            }

            $counts = array_count_values($statuses);

            $periodsData[$period->value] = [
                'applicable' => false,
                'approved' => $counts[BriefingBundleStatus::Approved->value] ?? 0,
                'expected' => count($statuses),
                'rate' => 0.0,
                'configured_weight' => (float) $weights[$period->value],
                'statuses' => $this->statusCounts($counts),
                'occurrences' => $occurrences,
                'tasks' => [],
            ];
        }

        if (array_sum(array_column($periodsData, 'expected')) === 0) {
            return $notApplicable();
        }

        return BriefingScoreResult::fromPeriods(
            branchId: $branchId,
            year: $year,
            month: $month,
            periods: $periodsData,
            passingScore: (float) $settings->passing_score,
            isProvisional: $isProvisional,
            periodStart: $countFrom->toDateString(),
            periodEnd: $monthEnd->toDateString(),
            computedAt: $now,
            methodVersion: BriefingScoreResult::BUNDLE_METHOD_VERSION,
        );
    }

    /**
     * Grouped bundles only — legacy rows without a status are not bundles until the grouping
     * command classifies them, so they count as missing here. Keyed "period|period_start".
     *
     * @return Collection<string, BriefingRecord>
     */
    private function bundles(int $branchId, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): Collection
    {
        return BriefingRecord::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('status')
            ->whereDate('period_start', '>=', $monthStart)
            ->whereDate('period_start', '<=', $monthEnd)
            ->with('requirements:id,briefing_record_id,include_in_score')
            ->get()
            ->keyBy(fn (BriefingRecord $bundle): string => $bundle->period->value.'|'.$bundle->period_start->toDateString());
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function occurrenceStarts(BriefingPeriod $period, CarbonImmutable $monthStart, CarbonImmutable $monthEnd, CarbonImmutable $countFrom): array
    {
        $starts = [];

        $cursor = match ($period) {
            BriefingPeriod::Daily => $countFrom,
            BriefingPeriod::Weekly => $countFrom->isMonday() ? $countFrom : $countFrom->next(CarbonImmutable::MONDAY),
            BriefingPeriod::Monthly => $monthStart,
        };

        if ($period === BriefingPeriod::Monthly) {
            return $cursor->greaterThanOrEqualTo($countFrom) ? [$cursor] : [];
        }

        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            $starts[] = $cursor;
            $cursor = $period === BriefingPeriod::Daily ? $cursor->addDay() : $cursor->addWeek();
        }

        return $starts;
    }

    private function isDue(BriefingPeriod $period, CarbonImmutable $start, CarbonImmutable $now): bool
    {
        return $this->periods->end($period, $start)->endOfDay()->lessThan($now);
    }

    /**
     * An existing bundle is judged by the obligations it was created with; without one, by what is
     * assigned now (the only record of the obligation that exists).
     */
    private function isScored(?BriefingRecord $bundle, bool $scoredAssignment): bool
    {
        if ($bundle !== null && $bundle->requirements->isNotEmpty()) {
            return $bundle->requirements->contains(fn (BriefingRecordRequirement $requirement): bool => $requirement->include_in_score);
        }

        return $scoredAssignment;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function statusCounts(array $counts): array
    {
        $keys = [...array_map(fn (BriefingBundleStatus $status): string => $status->value, BriefingBundleStatus::cases()), self::STATUS_MISSING, self::STATUS_UPCOMING];

        return array_combine($keys, array_map(fn (string $key): int => $counts[$key] ?? 0, $keys));
    }
}
