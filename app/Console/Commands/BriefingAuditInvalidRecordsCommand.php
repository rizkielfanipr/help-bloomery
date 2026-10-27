<?php

namespace App\Console\Commands;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Services\BriefingCleanupClassifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * docs/products/daily-briefing/remediation/prd.md §17.1 (Phase 12). Read-only. Reports how many rejected
 * briefing_items fall into each BriefingCleanupClassifier bucket, broken down by branch, task,
 * and period, so a human can review before any cleanup is considered. Never mutates data.
 */
#[Signature('briefing:audit-invalid-records')]
#[Description('Read-only audit of rejected briefing items, classified as safe-cleanup / manual-review / valid submission')]
class BriefingAuditInvalidRecordsCommand extends Command
{
    public function handle(BriefingCleanupClassifier $classifier): int
    {
        $rejectedItems = BriefingItem::query()
            ->where('review_status', BriefingReviewStatus::Rejected->value)
            ->with('record.branch')
            ->get();

        if ($rejectedItems->isEmpty()) {
            $this->info('No rejected briefing items found. Nothing to audit.');

            return Command::SUCCESS;
        }

        $buckets = $classifier->classifyMany($rejectedItems);

        $this->info('=== Daily Briefing — Rejected Item Audit (read-only) ===');
        $this->newLine();

        $this->table(
            ['Classification', 'Count'],
            [
                [BriefingCleanupClassifier::SAFE_CLEANUP_CANDIDATE, $buckets[BriefingCleanupClassifier::SAFE_CLEANUP_CANDIDATE]->count()],
                [BriefingCleanupClassifier::MANUAL_REVIEW, $buckets[BriefingCleanupClassifier::MANUAL_REVIEW]->count()],
                [BriefingCleanupClassifier::VALID_SUBMISSION, $buckets[BriefingCleanupClassifier::VALID_SUBMISSION]->count()],
            ],
        );

        foreach ([BriefingCleanupClassifier::SAFE_CLEANUP_CANDIDATE, BriefingCleanupClassifier::MANUAL_REVIEW] as $bucket) {
            $items = $buckets[$bucket];

            if ($items->isEmpty()) {
                continue;
            }

            $this->newLine();
            $this->info("--- {$bucket} by branch / task / period ---");

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

        return Command::SUCCESS;
    }
}
