<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BriefingScore;
use App\Models\BriefingTask;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BriefingScoreSeeder extends Seeder
{
    /** Task key => bobot (%) — total harus 100 */
    private array $taskWeights = [
        'daily_selfie_pagi' => 15.0,
        'daily_selfie_sore' => 15.0,
        'weekly_cleaning' => 20.0,
        'weekly_wm_pic' => 20.0,
        'monthly_gm_kpi' => 15.0,
        'monthly_cleaning_chiller' => 15.0,
    ];

    public function run(): void
    {
        // Pasang bobot pada task yang relevan
        foreach ($this->taskWeights as $key => $weight) {
            BriefingTask::where('key', $key)->update(['weight' => $weight]);
        }

        $this->command->info('Bobot task berhasil diset.');

        $tasks = BriefingTask::whereIn('key', array_keys($this->taskWeights))
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

            foreach ($periods as [$year, $month]) {
                $daysInMonth = Carbon::create($year, $month)->daysInMonth;
                $weeksInMonth = $this->countWeeksInMonth($year, $month);

                [$score, $breakdown] = $this->buildScore($tasks, $level, $daysInMonth, $weeksInMonth);

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

    /** @return array{float, array<int, array<string, mixed>>} */
    private function buildScore($tasks, string $level, int $daysInMonth, int $weeksInMonth): array
    {
        $multipliers = match ($level) {
            'excellent' => ['daily' => 0.95, 'weekly' => 1.00, 'monthly' => 1],
            'good' => ['daily' => 0.85, 'weekly' => 0.80, 'monthly' => 1],
            default => ['daily' => 0.70, 'weekly' => 0.75, 'monthly' => 0],
        };

        $breakdown = [];
        $totalScore = 0.0;

        foreach ($this->taskWeights as $key => $weight) {
            $task = $tasks->get($key);
            if (! $task) {
                continue;
            }

            $period = $task->period->value;
            $m = $multipliers[$period] ?? 0.7;

            [$approved, $expected, $taskScore] = match ($period) {
                'daily' => $this->scoreDaily($weight, $m, $daysInMonth),
                'weekly' => $this->scoreWeekly($weight, $m, $weeksInMonth),
                default => $this->scoreMonthly($weight, $m),
            };

            $totalScore += $taskScore;

            $breakdown[] = [
                'key' => $key,
                'label' => $task->label,
                'period' => $period,
                'weight' => $weight,
                'approved' => $approved,
                'expected' => $expected,
                'score' => round($taskScore, 2),
            ];
        }

        return [round($totalScore, 2), $breakdown];
    }

    /** @return array{int, int, float} */
    private function scoreDaily(float $weight, float $multiplier, int $days): array
    {
        $approved = (int) round($days * $multiplier);
        $score = $days > 0 ? ($approved / $days) * $weight : 0.0;

        return [$approved, $days, $score];
    }

    /** @return array{int, int, float} */
    private function scoreWeekly(float $weight, float $multiplier, int $weeks): array
    {
        $approved = (int) round($weeks * $multiplier);
        $score = $weeks > 0 ? ($approved / $weeks) * $weight : 0.0;

        return [$approved, $weeks, $score];
    }

    /** @return array{int, int, float} */
    private function scoreMonthly(float $weight, float $multiplier): array
    {
        $approved = $multiplier >= 0.5 ? 1 : 0;

        return [$approved, 1, (float) ($approved * $weight)];
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
