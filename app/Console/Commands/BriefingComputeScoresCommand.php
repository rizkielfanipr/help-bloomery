<?php

namespace App\Console\Commands;

use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
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

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $weeksInMonth = $this->countWeeksInMonth($year, $month);

        $bar = $this->output->createProgressBar($branches->count());
        $bar->start();

        foreach ($branches as $branch) {
            $this->computeForBranch($branch, $year, $month, $daysInMonth, $weeksInMonth);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Selesai.');
    }

    private function computeForBranch(Branch $branch, int $year, int $month, int $daysInMonth, int $weeksInMonth): void
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
                BriefingPeriod::Daily => $this->rateDailyTask($task, $dailyByDate, $year, $month, $daysInMonth),
                BriefingPeriod::Weekly => $this->rateWeeklyTask($task, $weeklyByDate, $year, $month, $weeksInMonth),
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
    private function rateDailyTask(BriefingTask $task, Collection $recordsByDate, int $year, int $month, int $daysInMonth): array
    {
        $approved = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateKey = Carbon::create($year, $month, $d)->toDateString();
            $dayRecords = $recordsByDate->get($dateKey, collect());

            foreach ($dayRecords as $record) {
                $item = $record->items->firstWhere('task_key', $task->key);
                if ($item && $item->review_status?->value === 'approved') {
                    $approved++;
                    break;
                }
            }
        }

        $rate = $daysInMonth > 0 ? $approved / $daysInMonth : 0.0;

        return [$approved, $daysInMonth, $rate];
    }

    /**
     * Sebuah minggu (Senin) dihitung approved jika setidaknya satu user
     * di branch mendapat task ini approved pada minggu tersebut.
     *
     * @return array{int, int, float}
     */
    private function rateWeeklyTask(BriefingTask $task, Collection $recordsByDate, int $year, int $month, int $weeksInMonth): array
    {
        $approved = 0;
        $current = Carbon::create($year, $month, 1);
        if (! $current->isMonday()) {
            $current->next(Carbon::MONDAY);
        }

        while ($current->month === $month) {
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

        $rate = $weeksInMonth > 0 ? $approved / $weeksInMonth : 0.0;

        return [$approved, $weeksInMonth, $rate];
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

    private function countWeeksInMonth(int $year, int $month): int
    {
        $current = Carbon::create($year, $month, 1);
        if (! $current->isMonday()) {
            $current->next(Carbon::MONDAY);
        }
        $count = 0;
        while ($current->month === $month) {
            $count++;
            $current->addWeek();
        }

        return $count;
    }
}
