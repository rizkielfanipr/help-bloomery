<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BriefingScore;
use App\Services\BriefingScoreCalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * docs/products/daily-briefing/scoring-ui/prd.md §18.2/Phase 12. Always read-only: there is no mutating
 * code path here at all, so this cannot write to `briefing_scores` no matter how it is invoked.
 * Compares each branch's currently-stored score (whatever method computed it) against what
 * BriefingScoreCalculator (method_version 2) would produce for the same period, so the delta
 * from the formula change is visible before anyone decides to recompute history.
 */
#[Signature('briefing:compare-scores {--year=} {--month=} {--branch=}')]
#[Description('Read-only: compares stored briefing_scores against the current calculator for a period. Never writes.')]
class BriefingCompareScoresCommand extends Command
{
    /** A delta at or beyond this many points is called out as worth a closer look. */
    private const NOTABLE_DELTA = 5.0;

    public function __construct(private readonly BriefingScoreCalculator $calculator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : null;
        $month = $this->option('month') ? (int) $this->option('month') : null;
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;

        if (! $year || ! $month) {
            $this->error('--year dan --month wajib diisi.');

            return Command::FAILURE;
        }

        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->exists()) {
            $this->error("Branch ID {$branchId} tidak ditemukan.");

            return Command::FAILURE;
        }

        $branches = Branch::when($branchId, fn ($q) => $q->where('id', $branchId))->orderBy('name')->get();

        $rows = [];
        $compared = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($branches as $branch) {
            $old = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->first();

            try {
                $new = $this->calculator->calculate($branch, $year, $month);
            } catch (\Throwable $exception) {
                $errors++;
                $exceptionClass = $exception::class;
                $this->error("Branch {$branch->name}: gagal menghitung ({$exceptionClass}).");

                continue;
            }

            if ($new->isNotApplicable() && ! $old) {
                $skipped++;

                continue;
            }

            $oldScore = $old?->score;
            $oldStatus = $old?->status() ?? '-';
            $newScore = $new->isNotApplicable() ? null : $new->score;
            $newStatus = $new->isNotApplicable() ? 'not_applicable' : $new->status;
            $delta = ($oldScore !== null && $newScore !== null) ? round($newScore - $oldScore, 2) : null;

            $reason = match (true) {
                $old === null => 'Belum ada score lama',
                $new->isNotApplicable() => 'Metode baru: branch/periode ini menjadi not_applicable',
                $delta !== null && abs($delta) >= self::NOTABLE_DELTA => 'Delta signifikan — kemungkinan karena formula occurrence-sum baru dan/atau filter include_in_score',
                default => '-',
            };

            $rows[] = [
                $branch->name,
                $oldScore !== null ? number_format($oldScore, 2) : '-',
                $newScore !== null ? number_format($newScore, 2) : '-',
                $delta !== null ? ($delta >= 0 ? '+' : '').number_format($delta, 2) : '-',
                $oldStatus,
                $newStatus,
                $new->isNotApplicable() ? '-' : ($new->periods[array_key_first($new->periods)]['approved'] ?? '-').'/'.($new->periods[array_key_first($new->periods)]['expected'] ?? '-'),
                $reason,
            ];
            $compared++;
        }

        $this->table(['Branch', 'Score Lama', 'Score Baru', 'Delta', 'Status Lama', 'Status Baru', 'Approved/Expected (periode pertama)', 'Alasan'], $rows);

        $this->newLine();
        $this->info("Dibandingkan: {$compared}, dilewati: {$skipped}, error: {$errors}.");
        $this->warn('Mode ini read-only. Tidak ada data briefing_scores yang diubah.');

        return Command::SUCCESS;
    }
}
