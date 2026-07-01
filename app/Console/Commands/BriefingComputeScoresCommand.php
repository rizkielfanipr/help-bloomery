<?php

namespace App\Console\Commands;

use App\Enums\BriefingPeriod;
use App\Models\Branch;
use App\Models\BriefingRecord;
use App\Models\BriefingScore;
use App\Models\BriefingTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BriefingComputeScoresCommand extends Command
{
    protected $signature = 'briefing:compute-scores
                            {--year= : Tahun (default: bulan lalu)}
                            {--month= : Bulan (default: bulan lalu)}
                            {--branch= : Branch ID tertentu (optional)}';

    protected $description = 'Hitung nilai briefing per branch per bulan';

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
        // Tasks yang berlaku untuk branch ini: global (branch_id null) + spesifik branch ini
        $tasks = BriefingTask::cached()
            ->where('is_active', true)
            ->filter(fn (BriefingTask $t) => $t->branch_id === null || $t->branch_id === $branch->id)
            ->filter(fn (BriefingTask $t) => $t->weight !== null && $t->weight > 0);

        if ($tasks->isEmpty()) {
            return;
        }

        // Ambil semua user yang aktif di branch ini
        $userIds = User::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        // Ambil semua briefing records dari user branch ini untuk bulan ini
        $records = BriefingRecord::whereIn('user_id', $userIds)
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

        $breakdown = [];
        $totalScore = 0.0;

        foreach ($tasks as $task) {
            [$approved, $expected, $taskScore] = match ($task->period) {
                BriefingPeriod::Daily => $this->scoreDailyTask($task, $dailyByDate, $year, $month, $daysInMonth),
                BriefingPeriod::Weekly => $this->scoreWeeklyTask($task, $weeklyByDate, $year, $month, $weeksInMonth),
                BriefingPeriod::Monthly => $this->scoreMonthlyTask($task, $monthlyRecords),
            };

            $totalScore += $taskScore;

            $breakdown[] = [
                'key' => $task->key,
                'label' => $task->label,
                'period' => $task->period->value,
                'weight' => $task->weight,
                'approved' => $approved,
                'expected' => $expected,
                'score' => round($taskScore, 2),
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
    private function scoreDailyTask(BriefingTask $task, $recordsByDate, int $year, int $month, int $daysInMonth): array
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

        $score = $daysInMonth > 0 ? ($approved / $daysInMonth) * $task->weight : 0;

        return [$approved, $daysInMonth, $score];
    }

    /**
     * Sebuah minggu (Senin) dihitung approved jika setidaknya satu user
     * di branch mendapat task ini approved pada minggu tersebut.
     *
     * @return array{int, int, float}
     */
    private function scoreWeeklyTask(BriefingTask $task, $recordsByDate, int $year, int $month, int $weeksInMonth): array
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

        $score = $weeksInMonth > 0 ? ($approved / $weeksInMonth) * $task->weight : 0;

        return [$approved, $weeksInMonth, $score];
    }

    /** @return array{int, int, float} */
    private function scoreMonthlyTask(BriefingTask $task, $records): array
    {
        foreach ($records as $record) {
            $item = $record->items->firstWhere('task_key', $task->key);
            if ($item && $item->review_status?->value === 'approved') {
                return [1, 1, (float) $task->weight];
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
