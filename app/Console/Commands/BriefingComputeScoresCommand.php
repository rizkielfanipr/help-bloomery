<?php

namespace App\Console\Commands;

use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\BriefingSettings;
use App\Models\BriefingTask;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BriefingComputeScoresCommand extends Command
{
    protected $signature = 'briefing:compute-scores
                            {--year= : Tahun (default: bulan lalu)}
                            {--month= : Bulan (default: bulan lalu)}
                            {--branch= : Branch ID tertentu (optional)}';

    protected $description = 'Hitung nilai briefing per branch per bulan';

    private const PERIOD_LABELS = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];

    public function handle(): void
    {
        $year = (int) ($this->option('year') ?: now()->subMonth()->year);
        $month = (int) ($this->option('month') ?: now()->subMonth()->month);
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;

        $this->info("Menghitung nilai briefing untuk {$month}/{$year}...");

        $branches = Branch::when($branchId, fn ($q) => $q->where('id', $branchId))->get();

        $periodStart = Carbon::create($year, $month)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $scoringStartedAt = BriefingSettings::instance()->scoring_started_at;

        if ($scoringStartedAt !== null && $scoringStartedAt->betweenIncluded($periodStart, $periodEnd)) {
            $periodStart = $scoringStartedAt->copy()->startOfDay();
        }

        if ($scoringStartedAt !== null && $scoringStartedAt->isAfter($periodEnd)) {
            $this->warn('Periode ini berada sebelum tanggal mulai penilaian briefing.');

            return;
        }

        $bar = $this->output->createProgressBar($branches->count());
        $bar->start();

        foreach ($branches as $branch) {
            $this->computeForBranch($branch, $year, $month, $periodStart, $periodEnd);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Selesai.');
    }

    private function computeForBranch(Branch $branch, int $year, int $month, Carbon $periodStart, Carbon $periodEnd): void
    {
        $tasks = BriefingTask::scoreableForBranch($branch->id);

        if ($tasks->isEmpty()) {
            return;
        }

        $records = BriefingRecord::query()
            ->where(function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id)
                    ->orWhere(function ($legacyQuery) use ($branch): void {
                        $legacyQuery->whereNull('branch_id')
                            ->whereHas('user', fn ($userQuery) => $userQuery->where('branch_id', $branch->id));
                    });
            })
            ->whereYear('record_date', $year)
            ->whereMonth('record_date', $month)
            ->with('items')
            ->get();

        // Group per periode
        $dailyByDate = $records->where('period', BriefingPeriod::Daily->value)
            ->groupBy(fn ($r) => $r->record_date->toDateString());

        $weeklyByDate = $records->where('period', BriefingPeriod::Weekly->value)
            ->groupBy(fn ($r) => $r->record_date->toDateString());

        $monthlyRecords = $records->where('period', BriefingPeriod::Monthly->value);

        // Rate (0..1) per task, grouped by period
        $tasksByPeriod = ['daily' => [], 'weekly' => [], 'monthly' => []];

        foreach ($tasks as $task) {
            [$approved, $expected, $rate] = match ($task->period) {
                BriefingPeriod::Daily => $this->rateDailyTask($task, $dailyByDate, $periodStart, $periodEnd),
                BriefingPeriod::Weekly => $this->rateWeeklyTask($task, $weeklyByDate, $periodStart, $periodEnd),
                BriefingPeriod::Monthly => $this->rateMonthlyTask($task, $monthlyRecords),
            };

            $tasksByPeriod[$task->period->value][] = [
                'key' => $task->key,
                'label' => $task->label,
                'approved' => $approved,
                'expected' => $expected,
                'rate' => round($rate * 100, 2),
            ];
        }

        $configuredWeights = BriefingPeriodWeight::forBranch($branch->id);

        // Only periods with at least one scoreable task actually count — a
        // period's weight is proportionally redistributed among the periods
        // that do apply, so a branch without e.g. any Weekly tasks isn't
        // penalized for a period that simply doesn't exist for it.
        $periodsWithTasks = collect(['daily', 'weekly', 'monthly'])
            ->filter(fn (string $period) => ! empty($tasksByPeriod[$period]))
            ->values();

        if ($periodsWithTasks->isEmpty()) {
            return;
        }

        $activeWeightSum = $periodsWithTasks->sum(fn (string $period) => $configuredWeights[$period]);

        $breakdown = [];
        $totalScore = 0.0;

        foreach ($periodsWithTasks as $period) {
            $taskDetails = $tasksByPeriod[$period];
            $periodRate = collect($taskDetails)->avg('rate') / 100;

            $effectiveWeight = $activeWeightSum > 0
                ? ($configuredWeights[$period] / $activeWeightSum) * 100
                : 100 / $periodsWithTasks->count();

            $periodScore = $periodRate * $effectiveWeight;
            $totalScore += $periodScore;

            $breakdown[$period] = [
                'label' => self::PERIOD_LABELS[$period],
                'configured_weight' => $configuredWeights[$period],
                'effective_weight' => round($effectiveWeight, 2),
                'rate' => round($periodRate * 100, 2),
                'score' => round($periodScore, 2),
                'tasks' => $taskDetails,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ];
        }

        BriefingScore::updateOrCreate(
            ['branch_id' => $branch->id, 'year' => $year, 'month' => $month],
            [
                'score' => round($totalScore, 2),
                'breakdown' => $breakdown,
                'computed_at' => now(),
            ]
        );
    }

    /**
     * Sebuah hari dihitung approved jika setidaknya satu user di branch
     * mendapat task ini approved pada hari tersebut.
     *
     * @return array{int, int, float}
     */
    private function rateDailyTask(BriefingTask $task, Collection $recordsByDate, Carbon $periodStart, Carbon $periodEnd): array
    {
        $approved = 0;
        $expected = 0;

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            $expected++;
            $dateKey = $date->toDateString();
            $dayRecords = $recordsByDate->get($dateKey, collect());

            foreach ($dayRecords as $record) {
                $item = $record->items->firstWhere('task_key', $task->key);
                if ($item && $item->review_status?->value === 'approved') {
                    $approved++;
                    break;
                }
            }
        }

        $rate = $expected > 0 ? $approved / $expected : 0.0;

        return [$approved, $expected, $rate];
    }

    /**
     * Sebuah minggu (Senin) dihitung approved jika setidaknya satu user
     * di branch mendapat task ini approved pada minggu tersebut.
     *
     * @return array{int, int, float}
     */
    private function rateWeeklyTask(BriefingTask $task, Collection $recordsByDate, Carbon $periodStart, Carbon $periodEnd): array
    {
        $approved = 0;
        $expected = 0;
        $current = $periodStart->copy();
        if (! $current->isMonday()) {
            $current->next(Carbon::MONDAY);
        }

        while ($current->lte($periodEnd)) {
            $expected++;
            $dayRecords = $recordsByDate->get($current->toDateString(), collect());

            foreach ($dayRecords as $record) {
                $item = $record->items->firstWhere('task_key', $task->key);
                if ($item && $item->review_status?->value === 'approved') {
                    $approved++;
                    break;
                }
            }

            $current->addWeek();
        }

        $rate = $expected > 0 ? $approved / $expected : 0.0;

        return [$approved, $expected, $rate];
    }

    /** @return array{int, int, float} */
    private function rateMonthlyTask(BriefingTask $task, Collection $records): array
    {
        foreach ($records as $record) {
            $item = $record->items->firstWhere('task_key', $task->key);
            if ($item && $item->review_status?->value === 'approved') {
                return [1, 1, 1.0];
            }
        }

        return [0, 1, 0.0];
    }
}
