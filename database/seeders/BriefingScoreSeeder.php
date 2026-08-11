<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BriefingPeriodWeight;
use App\Models\BriefingScore;
use App\Models\BriefingTask;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BriefingScoreSeeder extends Seeder
{
    /** Task key => periode */
    private array $scoredTasks = [
        'daily_selfie_pagi' => 'daily',
        'daily_selfie_sore' => 'daily',
        'weekly_cleaning' => 'weekly',
        'weekly_wm_pic' => 'weekly',
        'monthly_gm_kpi' => 'monthly',
        'monthly_cleaning_chiller' => 'monthly',
    ];

    public function run(): void
    {
        // Tandai task ini sebagai "Ikut Penilaian"
        BriefingTask::whereIn('key', array_keys($this->scoredTasks))->update(['include_in_score' => true]);

        $this->command->info('Task penilaian berhasil ditandai.');

        $tasks = BriefingTask::whereIn('key', array_keys($this->scoredTasks))
            ->get()
            ->keyBy('key');

        $branches = Branch::all();

        // 3 bulan terakhir: April, Mei, Juni 2026
        $periods = [
            [2026, 4],
            [2026, 5],
            [2026, 6],
        ];

        $bar = $this->command->getOutput()->createProgressBar($branches->count() * count($periods));
        $bar->start();

        foreach ($branches as $index => $branch) {
            // Variasikan performa antar branch
            $level = match ($index % 3) {
                0 => 'excellent',
                1 => 'good',
                default => 'borderline',
            };

            $weights = BriefingPeriodWeight::forBranch($branch->id);

            foreach ($periods as [$year, $month]) {
                $daysInMonth = Carbon::create($year, $month)->daysInMonth;
                $weeksInMonth = $this->countWeeksInMonth($year, $month);

                [$score, $breakdown] = $this->buildScore($tasks, $weights, $level, $daysInMonth, $weeksInMonth);

                BriefingScore::updateOrCreate(
                    ['branch_id' => $branch->id, 'year' => $year, 'month' => $month],
                    [
                        'score' => $score,
                        'breakdown' => $breakdown,
                        'computed_at' => Carbon::create($year, $month)
                            ->endOfMonth()->addDay()->setTime(2, 0, 0),
                    ]
                );

                $bar->advance();
            }
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Nilai briefing berhasil di-seed untuk {$branches->count()} branch, 3 bulan.");
    }

    /**
     * @param  array{daily: float, weekly: float, monthly: float}  $weights
     * @return array{float, array<string, mixed>}
     */
    private function buildScore($tasks, array $weights, string $level, int $daysInMonth, int $weeksInMonth): array
    {
        $multipliers = match ($level) {
            'excellent' => ['daily' => 0.95, 'weekly' => 1.00, 'monthly' => 1],
            'good' => ['daily' => 0.85, 'weekly' => 0.80, 'monthly' => 1],
            default => ['daily' => 0.70, 'weekly' => 0.75, 'monthly' => 0],
        };

        $periodLabels = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];
        $tasksByPeriod = ['daily' => [], 'weekly' => [], 'monthly' => []];

        foreach ($this->scoredTasks as $key => $period) {
            $task = $tasks->get($key);
            if (! $task) {
                continue;
            }

            $m = $multipliers[$period] ?? 0.7;

            [$approved, $expected, $rate] = match ($period) {
                'daily' => $this->rateDaily($m, $daysInMonth),
                'weekly' => $this->rateWeekly($m, $weeksInMonth),
                default => $this->rateMonthly($m),
            };

            $tasksByPeriod[$period][] = [
                'key' => $key,
                'label' => $task->label,
                'approved' => $approved,
                'expected' => $expected,
                'rate' => round($rate * 100, 2),
            ];
        }

        $periodsWithTasks = collect(['daily', 'weekly', 'monthly'])
            ->filter(fn (string $period) => ! empty($tasksByPeriod[$period]))
            ->values();

        $activeWeightSum = $periodsWithTasks->sum(fn (string $period) => $weights[$period]);

        $breakdown = [];
        $totalScore = 0.0;

        foreach ($periodsWithTasks as $period) {
            $taskDetails = $tasksByPeriod[$period];
            $periodRate = collect($taskDetails)->avg('rate') / 100;

            $effectiveWeight = $activeWeightSum > 0
                ? ($weights[$period] / $activeWeightSum) * 100
                : 100 / $periodsWithTasks->count();

            $periodScore = $periodRate * $effectiveWeight;
            $totalScore += $periodScore;

            $breakdown[$period] = [
                'label' => $periodLabels[$period],
                'configured_weight' => $weights[$period],
                'effective_weight' => round($effectiveWeight, 2),
                'rate' => round($periodRate * 100, 2),
                'score' => round($periodScore, 2),
                'tasks' => $taskDetails,
            ];
        }

        return [round($totalScore, 2), $breakdown];
    }

    /** @return array{int, int, float} */
    private function rateDaily(float $multiplier, int $days): array
    {
        $approved = (int) round($days * $multiplier);

        return [$approved, $days, $days > 0 ? $approved / $days : 0.0];
    }

    /** @return array{int, int, float} */
    private function rateWeekly(float $multiplier, int $weeks): array
    {
        $approved = (int) round($weeks * $multiplier);

        return [$approved, $weeks, $weeks > 0 ? $approved / $weeks : 0.0];
    }

    /** @return array{int, int, float} */
    private function rateMonthly(float $multiplier): array
    {
        $approved = $multiplier >= 0.5 ? 1 : 0;

        return [$approved, 1, (float) $approved];
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
