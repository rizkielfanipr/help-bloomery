<?php

namespace App\Console\Commands;

use App\Actions\Briefing\RecomputeBriefingScoresAction;
use App\Models\Branch;
use App\Models\BriefingScore;
use App\Services\BriefingScoringMethod;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * docs/daily-briefing-scoring-ui-prd.md §13/§18.3/Phase 13. Safe tooling for recomputing a range
 * of historical months at once. Defaults to --dry-run (read-only, identical in shape to
 * `briefing:compare-scores` but across a range). Mutation only runs behind the explicit
 * --confirm flag — this command never runs that flag itself; it must be invoked by a human who
 * has reviewed the dry-run output and approved it. Before any write, every row about to be
 * overwritten is snapshotted to a JSON file so --restore can put it back.
 */
#[Signature('briefing:recompute-history
    {--from-year=} {--from-month=}
    {--to-year=} {--to-month=}
    {--branch=}
    {--dry-run}
    {--confirm}
    {--method= : Preview another scoring method in --dry-run; --confirm always writes the active method}
    {--restore= : Path to a snapshot JSON file to restore instead of recomputing}')]
#[Description('Dry-run by default; --confirm required to mutate. Snapshots every overwritten row first; --restore=<file> reverts from a snapshot.')]
class BriefingRecomputeHistoryCommand extends Command
{
    public function __construct(
        private readonly BriefingScoringMethod $scoring,
        private readonly RecomputeBriefingScoresAction $recompute,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($restorePath = $this->option('restore')) {
            return $this->restore($restorePath);
        }

        $fromYear = $this->option('from-year') ? (int) $this->option('from-year') : null;
        $fromMonth = $this->option('from-month') ? (int) $this->option('from-month') : null;
        $toYear = $this->option('to-year') ? (int) $this->option('to-year') : null;
        $toMonth = $this->option('to-month') ? (int) $this->option('to-month') : null;
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;

        if (! $fromYear || ! $fromMonth || ! $toYear || ! $toMonth) {
            $this->error('--from-year, --from-month, --to-year, dan --to-month wajib diisi.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $confirm = (bool) $this->option('confirm');

        if (! $dryRun && ! $confirm) {
            $this->error('Jalankan dengan --dry-run untuk preview, atau --confirm untuk benar-benar menulis (setelah dry-run direview dan disetujui).');

            return Command::FAILURE;
        }

        $months = $this->monthsInRange($fromYear, $fromMonth, $toYear, $toMonth);
        if ($months === []) {
            $this->error('Rentang periode tidak valid.');

            return Command::FAILURE;
        }

        $branches = Branch::when($branchId, fn ($q) => $q->where('id', $branchId))->orderBy('name')->get();
        $method = $this->option('method') !== null ? (int) $this->option('method') : null;

        if ($dryRun) {
            return $this->runDryRun($branches, $months, $method);
        }

        // Writing a method other than the active one would leave stored scores the next recompute
        // job or scheduler run silently overwrites with the active method.
        if ($method !== null && $method !== $this->scoring->activeVersion()) {
            $this->error("--confirm selalu menulis metode aktif (v{$this->scoring->activeVersion()}). Ubah BRIEFING_SCORING_METHOD_VERSION terlebih dahulu bila cutover sudah disetujui.");

            return Command::FAILURE;
        }

        return $this->runConfirmed($branches, $months);
    }

    /** @return array<int, array{year: int, month: int}> */
    private function monthsInRange(int $fromYear, int $fromMonth, int $toYear, int $toMonth): array
    {
        $start = Carbon::create($fromYear, $fromMonth, 1);
        $end = Carbon::create($toYear, $toMonth, 1);

        if ($end->lt($start)) {
            return [];
        }

        $months = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $months[] = ['year' => $current->year, 'month' => $current->month];
            $current->addMonth();
        }

        return $months;
    }

    private function runDryRun(Collection $branches, array $months, ?int $method = null): int
    {
        $this->warn('=== DRY RUN — tidak ada data yang diubah === (metode v'.($method ?? $this->scoring->activeVersion()).')');
        $rows = [];

        foreach ($months as $period) {
            foreach ($branches as $branch) {
                $old = BriefingScore::where('branch_id', $branch->id)->where('year', $period['year'])->where('month', $period['month'])->first();
                $new = $this->scoring->calculate($branch, $period['year'], $period['month'], $method);
                $newScore = $new->isNotApplicable() ? null : $new->score;
                $delta = ($old && $newScore !== null) ? round($newScore - $old->score, 2) : null;

                if ($old === null && $new->isNotApplicable()) {
                    continue;
                }

                $rows[] = [
                    $branch->name,
                    "{$period['month']}/{$period['year']}",
                    $old?->score !== null ? number_format($old->score, 2) : '-',
                    $newScore !== null ? number_format($newScore, 2) : 'not_applicable',
                    $delta !== null ? ($delta >= 0 ? '+' : '').number_format($delta, 2) : '-',
                ];
            }
        }

        $this->table(['Branch', 'Periode', 'Score Lama', 'Score Baru', 'Delta'], $rows);
        $this->info('Preview selesai. Tidak ada mutation. Jalankan ulang dengan --confirm untuk menulis setelah direview.');

        return Command::SUCCESS;
    }

    private function runConfirmed(Collection $branches, array $months): int
    {
        $runId = (string) Str::ulid();
        $snapshot = [];

        foreach ($months as $period) {
            $old = BriefingScore::whereIn('branch_id', $branches->pluck('id'))
                ->where('year', $period['year'])->where('month', $period['month'])->get();

            foreach ($old as $row) {
                $snapshot[] = $row->only(['branch_id', 'year', 'month', 'score', 'breakdown', 'computed_at']);
            }
        }

        // Explicitly the "local" disk, not this app's default (b2, a remote bucket meant for
        // user-facing uploads) — a rollback artifact belongs on the server's own filesystem.
        $snapshotPath = "briefing-recompute-snapshots/{$runId}.json";
        Storage::disk('local')->put($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT));

        $snapshotDescription = $snapshot === [] ? '(kosong, tidak ada score lama)' : count($snapshot).' row';
        $this->info("Snapshot {$snapshotDescription} disimpan di storage/app/{$snapshotPath}.");

        $totals = ['computed' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($months as $period) {
            $result = $this->recompute->process($branches, $period['year'], $period['month']);
            foreach ($totals as $key => $_) {
                $totals[$key] += $result[$key];
            }
        }

        Log::warning('briefing.recompute_history.confirmed', [
            'run_id' => $runId,
            'months' => $months,
            'branch_count' => $branches->count(),
            'snapshot_path' => $snapshotPath,
            'totals' => $totals,
        ]);

        $this->info("Selesai. Dihitung: {$totals['computed']}, dilewati: {$totals['skipped']}, gagal: {$totals['failed']}.");
        $this->warn("Rollback: php artisan briefing:recompute-history --restore={$snapshotPath}");

        return $totals['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function restore(string $path): int
    {
        if (! Storage::disk('local')->exists($path)) {
            $this->error("Snapshot {$path} tidak ditemukan.");

            return Command::FAILURE;
        }

        $rows = json_decode(Storage::disk('local')->get($path), associative: true) ?? [];
        $restored = 0;

        foreach ($rows as $row) {
            BriefingScore::updateOrCreate(
                ['branch_id' => $row['branch_id'], 'year' => $row['year'], 'month' => $row['month']],
                ['score' => $row['score'], 'breakdown' => $row['breakdown'], 'computed_at' => $row['computed_at']],
            );
            $restored++;
        }

        $this->info("Dipulihkan {$restored} row dari snapshot {$path}.");

        return Command::SUCCESS;
    }
}
