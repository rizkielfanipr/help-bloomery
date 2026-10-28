<?php

namespace App\Services;

use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §7–§10 (Phase 2, method_version 2). The single source of
 * Daily Briefing scores — used by the compute/compare commands, the admin workspace, PDF/Excel,
 * and the Employee Score page. No Filament/Livewire dependency; deterministic; never mutates
 * anything (persistence is the caller's job).
 *
 * One occurrence = branch + task + period + record date. A period's score is
 * (sum of approved occurrences across its scoreable tasks) ÷ (sum of expected occurrences),
 * not an average of each task's own rate — occurrences with more expected dates correctly carry
 * more weight than ones with fewer (this is the formula change from method_version 1, which
 * averaged per-task rates and did not filter by `include_in_score`).
 */
class BriefingScoreCalculator
{
    public function __construct(private readonly BriefingAssignmentService $assignments) {}

    public function calculate(Branch $branch, int $year, int $month): BriefingScoreResult
    {
        $now = now();
        $today = Carbon::today();
        $branchId = (int) $branch->id;

        $periodStart = Carbon::create($year, $month)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $notApplicable = fn (): BriefingScoreResult => BriefingScoreResult::notApplicable(
            $branchId, $year, $month, $periodStart->toDateString(), $periodEnd->toDateString(), $now,
        );

        $settings = BriefingSettings::instance();
        $scoringStartedAt = $settings->scoring_started_at;

        if ($scoringStartedAt !== null && $scoringStartedAt->betweenIncluded($periodStart, $periodEnd)) {
            $periodStart = $scoringStartedAt->copy()->startOfDay();
        }

        if ($scoringStartedAt !== null && $scoringStartedAt->isAfter($periodEnd)) {
            return $notApplicable();
        }

        if ($periodStart->isAfter($today)) {
            return $notApplicable();
        }

        // §9/§8.5: the current month never counts dates later than today as expected.
        $isProvisional = $periodEnd->greaterThan($today);
        $effectiveEnd = $isProvisional ? $today->copy() : $periodEnd->copy();

        if (! $this->assignments->hasParticipationOverlapping($branchId, $periodStart, $effectiveEnd)) {
            return $notApplicable();
        }

        $tasks = BriefingTask::forScoringBranch($branchId)->filter(fn (BriefingTask $t): bool => $t->include_in_score);

        if ($tasks->isEmpty()) {
            return BriefingScoreResult::notApplicable($branchId, $year, $month, $periodStart->toDateString(), $effectiveEnd->toDateString(), $now);
        }

        // The `orWhere` branch is a documented legacy fallback for records predating the
        // `branch_id` auto-set in BriefingRecord::booted() (docs/products/daily-briefing/remediation/prd.md
        // §10.3) — kept until a backfill is validated, so historical scores don't silently drop
        // contributions from before that column was populated.
        $records = BriefingRecord::query()
            ->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)
                    ->orWhere(function ($legacyQuery) use ($branchId): void {
                        $legacyQuery->whereNull('branch_id')
                            ->whereHas('user', fn ($userQuery) => $userQuery->where('branch_id', $branchId));
                    });
            })
            ->whereYear('record_date', $year)
            ->whereMonth('record_date', $month)
            ->with('items')
            ->get();

        $dailyByDate = $records->where('period', BriefingPeriod::Daily->value)->groupBy(fn ($r) => $r->record_date->toDateString());
        $weeklyByDate = $records->where('period', BriefingPeriod::Weekly->value)->groupBy(fn ($r) => $r->record_date->toDateString());
        $monthlyRecords = $records->where('period', BriefingPeriod::Monthly->value);

        $configuredWeights = BriefingPeriodWeight::forBranch($branchId);

        $periodsData = [];
        foreach (BriefingPeriod::cases() as $period) {
            $periodTasks = $tasks->where('period', $period);

            $taskRows = $periodTasks->map(function (BriefingTask $task) use ($period, $dailyByDate, $weeklyByDate, $monthlyRecords, $periodStart, $effectiveEnd, $branchId): array {
                [$approved, $expected] = match ($period) {
                    BriefingPeriod::Daily => $this->rateDailyTask($task, $dailyByDate, $periodStart, $effectiveEnd, $branchId),
                    BriefingPeriod::Weekly => $this->rateWeeklyTask($task, $weeklyByDate, $periodStart, $effectiveEnd, $branchId),
                    BriefingPeriod::Monthly => $this->rateMonthlyTask($task, $monthlyRecords, $periodStart, $branchId),
                };

                return [
                    'key' => $task->key,
                    'label' => $task->label,
                    'approved' => $approved,
                    'expected' => $expected,
                    'rate' => $expected > 0 ? round($approved / $expected * 100, 2) : 0.0,
                ];
            })->values();

            $periodApproved = (int) $taskRows->sum('approved');
            $periodExpected = (int) $taskRows->sum('expected');
            $applicable = $periodExpected > 0;

            $periodsData[$period->value] = [
                'applicable' => $applicable,
                'approved' => $periodApproved,
                'expected' => $periodExpected,
                'rate' => $applicable ? round($periodApproved / $periodExpected * 100, 2) : 0.0,
                'configured_weight' => (float) $configuredWeights[$period->value],
                'tasks' => $taskRows->all(),
            ];
        }

        $applicablePeriods = collect($periodsData)->filter(fn (array $p): bool => $p['applicable']);

        if ($applicablePeriods->isEmpty()) {
            return BriefingScoreResult::notApplicable($branchId, $year, $month, $periodStart->toDateString(), $effectiveEnd->toDateString(), $now);
        }

        $activeWeightSum = $applicablePeriods->sum('configured_weight');
        $finalScore = 0.0;

        foreach ($periodsData as $periodKey => &$data) {
            if (! $data['applicable']) {
                $data['effective_weight'] = 0.0;
                $data['contribution'] = 0.0;

                continue;
            }

            $effectiveWeight = $activeWeightSum > 0
                ? ($data['configured_weight'] / $activeWeightSum) * 100
                : 100 / $applicablePeriods->count();

            $contribution = round($data['rate'] * $effectiveWeight / 100, 2);

            $data['effective_weight'] = round($effectiveWeight, 2);
            $data['contribution'] = $contribution;
            $finalScore += $contribution;
        }
        unset($data);

        $finalScore = round($finalScore, 2);
        $passingScore = (float) $settings->passing_score;

        return new BriefingScoreResult(
            branchId: $branchId,
            year: $year,
            month: $month,
            score: $finalScore,
            passingScore: $passingScore,
            status: $finalScore >= $passingScore ? BriefingScoreResult::STATUS_ACHIEVED : BriefingScoreResult::STATUS_NOT_ACHIEVED,
            isProvisional: $isProvisional,
            periodStart: $periodStart->toDateString(),
            periodEnd: $effectiveEnd->toDateString(),
            periods: $periodsData,
            computedAt: $now,
        );
    }

    /** @return array{0: int, 1: int} */
    private function rateDailyTask(BriefingTask $task, Collection $recordsByDate, Carbon $periodStart, Carbon $periodEnd, int $branchId): array
    {
        $approved = 0;
        $expected = 0;

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            if (! $this->assignments->isBranchParticipant($branchId, $date)) {
                continue;
            }

            $expected++;

            foreach ($recordsByDate->get($date->toDateString(), collect()) as $record) {
                $item = $record->items->firstWhere('task_key', $task->key);
                if ($item && $item->review_status?->value === 'approved') {
                    $approved++;
                    break;
                }
            }
        }

        return [$approved, $expected];
    }

    /** @return array{0: int, 1: int} */
    private function rateWeeklyTask(BriefingTask $task, Collection $recordsByDate, Carbon $periodStart, Carbon $periodEnd, int $branchId): array
    {
        $approved = 0;
        $expected = 0;
        $current = $periodStart->copy();
        if (! $current->isMonday()) {
            $current->next(Carbon::MONDAY);
        }

        while ($current->lte($periodEnd)) {
            if (! $this->assignments->isBranchParticipant($branchId, $current)) {
                $current->addWeek();

                continue;
            }

            $expected++;

            foreach ($recordsByDate->get($current->toDateString(), collect()) as $record) {
                $item = $record->items->firstWhere('task_key', $task->key);
                if ($item && $item->review_status?->value === 'approved') {
                    $approved++;
                    break;
                }
            }

            $current->addWeek();
        }

        return [$approved, $expected];
    }

    /** @return array{0: int, 1: int} */
    private function rateMonthlyTask(BriefingTask $task, Collection $records, Carbon $periodStart, int $branchId): array
    {
        if (! $this->assignments->isBranchParticipant($branchId, $periodStart)) {
            return [0, 0];
        }

        foreach ($records as $record) {
            $item = $record->items->firstWhere('task_key', $task->key);
            if ($item && $item->review_status?->value === 'approved') {
                return [1, 1];
            }
        }

        return [0, 1];
    }
}
