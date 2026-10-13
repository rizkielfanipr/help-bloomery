<?php

namespace App\Actions\Briefing;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTransitionSource;
use App\Jobs\Briefing\RecomputeBriefingBranchScoreJob;
use App\Models\BriefingRecord;
use App\Models\User;
use App\Policies\BriefingRecordPolicy;
use Illuminate\Support\Facades\DB;

/**
 * docs/daily-briefing-bundle-approval-prd.md §9.3/§9.4 (Phase 5): the audited way to let staff change an
 * approved or rejected bundle again. Reopening an approved bundle withdraws its approval — the
 * branch score loses it until the bundle is approved again.
 */
class ReopenBriefingBundleAction
{
    public function __construct(private readonly BriefingRecordPolicy $policy) {}

    public function execute(User $actor, BriefingRecord $bundle, string $reason): BriefingBundleReviewResult
    {
        if (blank($reason)) {
            return BriefingBundleReviewResult::denied('reason_required', 'Alasan membuka kembali wajib diisi.');
        }

        return DB::transaction(function () use ($actor, $bundle, $reason): BriefingBundleReviewResult {
            $locked = BriefingRecord::query()->whereKey($bundle->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status === null) {
                return BriefingBundleReviewResult::denied('not_found', 'Bundle briefing tidak ditemukan.');
            }

            if (! $this->policy->canReview($actor, $locked)) {
                return BriefingBundleReviewResult::denied('unauthorized', 'Anda tidak berwenang membuka kembali bundle cabang ini.');
            }

            if ($locked->status === BriefingBundleStatus::Reopened) {
                return BriefingBundleReviewResult::alreadyApplied($locked, 'Bundle ini sudah dibuka kembali.');
            }

            if (! $locked->status->canTransitionTo(BriefingBundleStatus::Reopened)) {
                return BriefingBundleReviewResult::denied('not_reopenable', 'Hanya bundle yang sudah disetujui atau ditolak yang dapat dibuka kembali.');
            }

            $from = $locked->status;

            if ($from === BriefingBundleStatus::Approved) {
                $locked->items()->update([
                    'review_status' => BriefingReviewStatus::SupervisorReview->value,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);

                $locked->fill(['reviewed_by' => null, 'reviewed_at' => null]);
            }

            $locked->fill(['status' => BriefingBundleStatus::Reopened->value])->save();

            $locked->transitions()->create([
                'from_status' => $from->value,
                'to_status' => BriefingBundleStatus::Reopened->value,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'source' => BriefingTransitionSource::UserAction->value,
            ]);

            RecomputeBriefingBranchScoreJob::dispatchForBundle($locked);

            return BriefingBundleReviewResult::applied($locked->refresh(), 'Bundle dibuka kembali untuk perbaikan.');
        });
    }
}
