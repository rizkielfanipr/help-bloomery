<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BriefingScore;
use App\Services\BriefingLegacyGroupingPlan;
use App\Services\BriefingLegacyGroupingScanner;
use App\Services\BriefingScoreResult;
use App\Services\BriefingScoringMethod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * docs/daily-briefing-scoring-ui-prd.md §18.2 and docs/daily-briefing-bundle-approval-prd.md Phase 10.
 * Always read-only: there is no mutating code path here at all, so this cannot write to
 * `briefing_scores` no matter how it is invoked. Compares each branch's stored score (whatever
 * method computed it) against what the chosen method would produce now. With --method=3 it also
 * shows, per branch, the bundle denominators, missing obligations, and how much legacy data is still
 * ungrouped or waiting for manual review — the reasons a bundle score can differ.
 */
#[Signature('briefing:compare-scores {--year=} {--month=} {--branch=} {--method=2 : Scoring method to compare against (2 per-item, 3 bundles)}')]
#[Description('Read-only: compares stored briefing_scores against a scoring method for a period. Never writes.')]
class BriefingCompareScoresCommand extends Command
{
    /** A delta at or beyond this many points is called out as worth a closer look. */
    private const NOTABLE_DELTA = 5.0;

    public function __construct(
        private readonly BriefingScoringMethod $scoring,
        private readonly BriefingLegacyGroupingScanner $legacy,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : null;
        $month = $this->option('month') ? (int) $this->option('month') : null;
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $method = (int) $this->option('method');

        if (! $year || ! $month) {
            $this->error('--year dan --month wajib diisi.');

            return Command::FAILURE;
        }

        if (! in_array($method, [BriefingScoreResult::METHOD_VERSION, BriefingScoreResult::BUNDLE_METHOD_VERSION], true)) {
            $this->error('--method harus 2 atau 3.');

            return Command::FAILURE;
        }

        if ($branchId !== null && ! Branch::query()->whereKey($branchId)->exists()) {
            $this->error("Branch ID {$branchId} tidak ditemukan.");

            return Command::FAILURE;
        }

        $branches = Branch::when($branchId, fn ($q) => $q->where('id', $branchId))->orderBy('name')->get();
        $bundleMode = $method === BriefingScoreResult::BUNDLE_METHOD_VERSION;

        $rows = [];
        $compared = 0;
        $skipped = 0;
        $errors = 0;
        $outliers = 0;

        foreach ($branches as $branch) {
            $old = BriefingScore::where('branch_id', $branch->id)->where('year', $year)->where('month', $month)->first();

            try {
                $new = $this->scoring->calculate($branch, $year, $month, $method);
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
            $newScore = $new->isNotApplicable() ? null : $new->score;
            $delta = ($oldScore !== null && $newScore !== null) ? round($newScore - $oldScore, 2) : null;
            $isOutlier = $delta !== null && abs($delta) >= self::NOTABLE_DELTA;
            $outliers += $isOutlier ? 1 : 0;

            $reason = match (true) {
                $old === null => 'Belum ada score lama',
                $new->isNotApplicable() => 'Metode baru: branch/periode ini menjadi not_applicable',
                $isOutlier && $bundleMode => 'Delta signifikan — cek missing, data lama belum di-grouping, dan manual review',
                $isOutlier => 'Delta signifikan — kemungkinan karena formula occurrence-sum baru dan/atau filter include_in_score',
                default => '-',
            };

            $row = [
                $branch->name,
                $oldScore !== null ? number_format($oldScore, 2) : '-',
                $old ? 'v'.$old->methodVersion() : '-',
                $newScore !== null ? number_format($newScore, 2) : '-',
                $delta !== null ? ($delta >= 0 ? '+' : '').number_format($delta, 2) : '-',
                $old?->status() ?? '-',
                $new->isNotApplicable() ? 'not_applicable' : $new->status,
            ];

            if ($bundleMode) {
                $legacy = $this->legacySummary($branch->id, $year, $month);

                $row = [
                    ...$row,
                    $this->periodsSummary($new),
                    (string) collect($new->periods)->sum(fn (array $period): int => $period['statuses']['missing'] ?? 0),
                    "{$legacy['ungrouped']} / {$legacy['manual_review']}",
                ];
            } else {
                $row[] = $new->isNotApplicable() ? '-' : ($new->periods[array_key_first($new->periods)]['approved'] ?? '-').'/'.($new->periods[array_key_first($new->periods)]['expected'] ?? '-');
            }

            $rows[] = [...$row, $reason];
            $compared++;
        }

        $headers = ['Branch', 'Score Lama', 'Metode Lama', "Score v{$method}", 'Delta', 'Status Lama', 'Status Baru'];
        $headers = $bundleMode
            ? [...$headers, 'Approved/Expected (H · M · B)', 'Missing', 'Legacy Belum Grouping / Manual Review', 'Alasan']
            : [...$headers, 'Approved/Expected (periode pertama)', 'Alasan'];

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Dibandingkan: {$compared}, dilewati: {$skipped}, outlier (|delta| ≥ ".self::NOTABLE_DELTA."): {$outliers}, error: {$errors}.");
        $this->warn('Mode ini read-only. Tidak ada data briefing_scores yang diubah.');

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function periodsSummary(BriefingScoreResult $result): string
    {
        return collect(['daily', 'weekly', 'monthly'])
            ->map(fn (string $period): string => isset($result->periods[$period]) && $result->periods[$period]['applicable']
                ? $result->periods[$period]['approved'].'/'.$result->periods[$period]['expected']
                : '-')
            ->implode(' · ');
    }

    /**
     * @return array{ungrouped: int, manual_review: int}
     */
    private function legacySummary(int $branchId, int $year, int $month): array
    {
        $summary = ['ungrouped' => 0, 'manual_review' => 0];

        foreach ($this->legacy->groups($branchId, $year, $month) as $group) {
            $summary['ungrouped']++;

            if ($this->legacy->plan($group)->classification === BriefingLegacyGroupingPlan::MANUAL_REVIEW) {
                $summary['manual_review']++;
            }
        }

        return $summary;
    }
}
