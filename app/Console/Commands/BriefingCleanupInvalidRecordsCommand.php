<?php

namespace App\Console\Commands;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingScore;
use App\Services\BriefingCleanupClassifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * docs/products/daily-briefing/remediation/prd.md §17.3 (Phase 12). Only a dry-run is implemented in this
 * release — there is deliberately no mutating code path at all yet, so this command cannot
 * delete or change anything no matter how it is invoked. A mutating mode (and its own approval,
 * sampling, and rollback-artifact step) is a separate, explicitly-authorized follow-up per the
 * PRD's "Dilarang ... mengeksekusi mode mutating" instruction for this phase.
 */
#[Signature('briefing:cleanup-invalid-records {--dry-run}')]
#[Description('Dry-run only: previews rejected briefing items that would be safe to clean up. Mutation is not implemented in this release.')]
class BriefingCleanupInvalidRecordsCommand extends Command
{
    public function handle(BriefingCleanupClassifier $classifier): int
    {
        if (! $this->option('dry-run')) {
            $this->error(
                'Mutation mode is not implemented. Run with --dry-run. Deleting/changing cleanup '
                .'candidates requires a separate, explicitly-approved follow-up (see '
                .'docs/products/daily-briefing/remediation/prd.md §17.3).'
            );

            return Command::FAILURE;
        }

        $this->info('=== Daily Briefing — Cleanup Dry-Run (no mutation will occur) ===');
        $this->newLine();

        $rejectedItems = BriefingItem::query()
            ->where('review_status', BriefingReviewStatus::Rejected->value)
            ->with('record.branch', 'record.user')
            ->get();

        $buckets = $classifier->classifyMany($rejectedItems);
        $safeCandidates = $buckets[BriefingCleanupClassifier::SAFE_CLEANUP_CANDIDATE];
        $manualReview = $buckets[BriefingCleanupClassifier::MANUAL_REVIEW];

        if ($safeCandidates->isEmpty() && $manualReview->isEmpty()) {
            $this->info('No cleanup candidates found. Nothing to preview.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Classification', 'Count'],
            [
                [BriefingCleanupClassifier::SAFE_CLEANUP_CANDIDATE, $safeCandidates->count()],
                [BriefingCleanupClassifier::MANUAL_REVIEW, $manualReview->count()],
                [BriefingCleanupClassifier::VALID_SUBMISSION, $buckets[BriefingCleanupClassifier::VALID_SUBMISSION]->count().' (never touched)'],
            ],
        );

        $this->newLine();
        $this->info('--- Safe-cleanup candidates by branch / role / task / period ---');
        $this->printBreakdown($safeCandidates);

        $this->newLine();
        $this->info('--- Parent records that would become empty if safe-cleanup items were removed ---');
        $emptiedRecordIds = $safeCandidates
            ->groupBy('briefing_record_id')
            ->filter(fn ($items, $recordId) => $items->count() === BriefingItem::where('briefing_record_id', $recordId)->count())
            ->keys();

        $this->line($emptiedRecordIds->isEmpty()
            ? 'None — every affected record has at least one other, non-candidate item.'
            : 'Record IDs: '.$emptiedRecordIds->implode(', '));

        $this->newLine();
        $this->info('--- Score/period rows potentially affected (same branch + year/month as a candidate) ---');
        $affectedPeriods = $safeCandidates
            ->map(fn (BriefingItem $item) => $item->record === null ? null : [
                'branch_id' => $item->record->branch_id,
                'year' => $item->record->record_date->year,
                'month' => $item->record->record_date->month,
            ])
            ->filter()
            ->unique(fn (array $p) => "{$p['branch_id']}-{$p['year']}-{$p['month']}");

        $affectedScores = $affectedPeriods->filter(fn (array $p) => BriefingScore::where('branch_id', $p['branch_id'])
            ->where('year', $p['year'])
            ->where('month', $p['month'])
            ->exists());

        $this->line($affectedScores->isEmpty()
            ? 'None — no existing BriefingScore row shares a branch/month with a candidate.'
            : $affectedScores->map(fn (array $p) => "branch_id={$p['branch_id']} {$p['year']}-{$p['month']}")->implode('; '));

        $this->newLine();
        $this->warn('This was a preview only. No rows were changed, deleted, or recomputed.');

        return Command::SUCCESS;
    }

    private function printBreakdown(Collection $items): void
    {
        if ($items->isEmpty()) {
            $this->line('None.');

            return;
        }

        $grouped = $items->groupBy(fn (BriefingItem $item): string => sprintf(
            '%s | %s | %s',
            $item->record?->branch?->name ?? 'Branch #'.($item->record?->branch_id ?? '?'),
            $item->task_key,
            $item->record?->period?->value ?? '?',
        ));

        $rows = $grouped->map(fn ($group, string $key) => [
            ...explode(' | ', $key),
            $group->count(),
            $group->take(5)->pluck('id')->implode(', '),
        ])->values()->all();

        $this->table(['Branch', 'Task', 'Period', 'Count', 'Sample IDs'], $rows);
    }
}
