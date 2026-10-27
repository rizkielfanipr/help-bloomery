<?php

namespace App\Console\Commands;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * docs/products/daily-briefing/remediation/prd.md §11.3/§11.4 (Phase 7). Auto-rejects only real
 * submissions stuck waiting for review past the SLA window. It must never create a
 * briefing_records/briefing_items row to represent a deadline that passed with nothing submitted
 * — "missing" is a derived read, computed by BriefingStatusService, not a stored row.
 */
#[Signature('briefing:auto-reject')]
#[Description('Auto-reject real submissions still pending review past the configured SLA window (see Pengaturan Briefing)')]
class BriefingAutoRejectCommand extends Command
{
    public function handle(): int
    {
        $runId = (string) Str::ulid();
        $startedAt = now();
        $settings = BriefingSettings::instance();

        $checked = 0;
        $updated = 0;
        $errors = 0;

        $days = $settings->auto_reject_after_days;
        $reason = $settings->formattedAutoRejectReason($days);

        try {
            BriefingItem::query()
                ->whereIn('review_status', [
                    BriefingReviewStatus::LegacyPending->value,
                    BriefingReviewStatus::SupervisorReview->value,
                ])
                ->where('is_completed', true)
                ->whereNotNull('completed_at')
                ->where('completed_at', '<=', now()->subDays($days))
                ->chunkById(100, function ($items) use (&$checked, &$updated, $reason): void {
                    foreach ($items as $item) {
                        $checked++;

                        $item->update([
                            'review_status' => BriefingReviewStatus::Rejected->value,
                            'rejection_reason' => $reason,
                            'reviewed_by' => null,
                            'reviewed_at' => now(),
                        ]);

                        $updated++;
                    }
                });
        } catch (\Throwable $exception) {
            $errors++;
            $exceptionClass = $exception::class;

            Log::error('briefing.auto_reject.failed', [
                'run_id' => $runId,
                'exception' => $exceptionClass,
            ]);

            $this->error("Auto-reject run failed: {$exceptionClass}");

            return Command::FAILURE;
        }

        Log::info('briefing.auto_reject.completed', [
            'run_id' => $runId,
            'checked' => $checked,
            'updated' => $updated,
            'errors' => $errors,
            'duration_ms' => now()->diffInMilliseconds($startedAt),
        ]);

        $this->info("Auto-rejected {$updated} briefing item(s) pending review past the SLA window.");

        return Command::SUCCESS;
    }
}
