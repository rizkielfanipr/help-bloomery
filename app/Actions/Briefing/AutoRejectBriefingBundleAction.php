<?php

namespace App\Actions\Briefing;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTransitionSource;
use App\Jobs\Briefing\RecomputeBriefingBranchScoreJob;
use App\Models\BriefingRecord;
use Illuminate\Support\Facades\DB;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 8: the review-SLA rejection of one complete bundle
 * a supervisor never got to. It has no reviewer (that is what marks it as system-made), is audited
 * as a system reconciliation, and re-checks the bundle on the locked row so a supervisor's decision
 * made a moment earlier always wins.
 */
class AutoRejectBriefingBundleAction
{
    public function execute(BriefingRecord $bundle, string $reason, int $slaDays): bool
    {
        return DB::transaction(function () use ($bundle, $reason, $slaDays): bool {
            $locked = BriefingRecord::query()
                ->whereKey($bundle->getKey())
                ->awaitingReviewPastSla($slaDays)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return false;
            }

            $locked->fill([
                'status' => BriefingBundleStatus::Rejected->value,
                'reviewed_by' => null,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ])->save();

            $locked->items()->update([
                'review_status' => BriefingReviewStatus::Rejected->value,
                'rejection_reason' => $reason,
                'reviewed_by' => null,
                'reviewed_at' => now(),
            ]);

            $locked->transitions()->create([
                'from_status' => BriefingBundleStatus::SupervisorReview->value,
                'to_status' => BriefingBundleStatus::Rejected->value,
                'actor_id' => null,
                'reason' => $reason,
                'source' => BriefingTransitionSource::SystemReconciliation->value,
                'metadata' => ['rule' => 'review_sla', 'sla_days' => $slaDays],
            ]);

            RecomputeBriefingBranchScoreJob::dispatchForBundle($locked);

            return true;
        });
    }
}
