<?php

namespace App\Console\Commands;

use App\Actions\Briefing\RecomputeBriefingScoresAction;
use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * docs/daily-briefing-scoring-ui-prd.md §10/Phase 5 (and docs/daily-briefing-remediation-prd.md
 * §14). A thin orchestrator — resolve scope, delegate to RecomputeBriefingScoresAction (the same
 * action the workspace's "Hitung Ulang" button uses), log the run. The formula itself lives only
 * in BriefingScoreCalculator, so this command and the UI can never disagree.
 */
class BriefingComputeScoresCommand extends Command
{
    protected $signature = 'briefing:compute-scores
                            {--year= : Tahun (default: bulan lalu)}
                            {--month= : Bulan (default: bulan lalu)}
                            {--branch= : Branch ID tertentu (optional)}';

    protected $description = 'Hitung nilai briefing per branch per bulan';

    public function __construct(private readonly RecomputeBriefingScoresAction $recompute)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $runId = (string) Str::ulid();
        $startedAt = now();

        $year = (int) ($this->option('year') ?: now()->subMonth()->year);
        $month = (int) ($this->option('month') ?: now()->subMonth()->month);
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;

        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->exists()) {
            $this->error("Branch ID {$branchId} tidak ditemukan.");

            return Command::FAILURE;
        }

        $this->info("Menghitung nilai briefing untuk {$month}/{$year}...");

        $branches = Branch::when($branchId, fn ($q) => $q->where('id', $branchId))->orderBy('name')->get();

        $bar = $this->output->createProgressBar($branches->count());
        $bar->start();
        $result = $this->recompute->process($branches, $year, $month);
        $bar->advance($branches->count());
        $bar->finish();
        $this->newLine();

        Log::info('briefing.compute_scores.completed', [
            'run_id' => $runId,
            'year' => $year,
            'month' => $month,
            'branch_id' => $branchId,
            'computed' => $result['computed'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
            'duration_ms' => now()->diffInMilliseconds($startedAt),
        ]);

        $this->info("Selesai. Dihitung: {$result['computed']}, dilewati (not applicable): {$result['skipped']}, gagal: {$result['failed']}.");

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
