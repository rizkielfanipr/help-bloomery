<?php

namespace App\Actions\Briefing;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTransitionSource;
use App\Jobs\Briefing\RecomputeBriefingBranchScoreJob;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use App\Policies\BriefingRecordPolicy;
use App\Services\BriefingBundleCompletenessService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * docs/daily-briefing-bundle-approval-prd.md §9/§18 (Phase 5): the single approve/reject decision for
 * a whole bundle. Everything a button's visibility implies is re-checked on the locked row: the
 * reviewer's permission and branch scope, that the bundle is awaiting review and still complete,
 * and that it has not changed since the reviewer opened it.
 *
 * During the compatibility phase each item's legacy review columns mirror the bundle decision, so
 * the per-item export and the current score method stay consistent until they move to bundles.
 */
class ReviewBriefingBundleAction
{
    public function __construct(
        private readonly BriefingBundleCompletenessService $completeness,
        private readonly BriefingRecordPolicy $policy,
    ) {}

    public function approve(User $actor, BriefingRecord $bundle, ?string $notes = null, ?CarbonInterface $seenUpdatedAt = null): BriefingBundleReviewResult
    {
        return $this->decide($actor, $bundle, BriefingBundleStatus::Approved, $seenUpdatedAt, function (BriefingRecord $locked) use ($actor, $notes): void {
            $locked->fill([
                'status' => BriefingBundleStatus::Approved->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => filled($notes) ? $notes : null,
            ])->save();

            $locked->items()->update([
                'review_status' => BriefingReviewStatus::Approved->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'requires_correction' => false,
            ]);

            $this->audit($locked, BriefingBundleStatus::SupervisorReview, BriefingBundleStatus::Approved, $actor, $notes);
        }, 'Bundle briefing disetujui.');
    }

    /**
     * @param  array<string, string|null>  $corrections  task_key => note for each point the staff must fix
     */
    public function reject(
        User $actor,
        BriefingRecord $bundle,
        string $reason,
        array $corrections = [],
        bool $reopenForCorrection = false,
        ?CarbonInterface $seenUpdatedAt = null,
    ): BriefingBundleReviewResult {
        if (blank($reason)) {
            return BriefingBundleReviewResult::denied('reason_required', 'Alasan penolakan wajib diisi.');
        }

        $unknownKeys = array_diff(array_keys($corrections), $bundle->items()->pluck('task_key')->all());

        if ($unknownKeys !== []) {
            return BriefingBundleReviewResult::denied('unknown_item', 'Poin yang ditandai tidak ada dalam bundle ini.');
        }

        return $this->decide($actor, $bundle, BriefingBundleStatus::Rejected, $seenUpdatedAt, function (BriefingRecord $locked) use ($actor, $reason, $corrections, $reopenForCorrection): void {
            $locked->fill([
                'status' => BriefingBundleStatus::Rejected->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ])->save();

            $locked->items->each(function (BriefingItem $item) use ($actor, $reason, $corrections): void {
                if (! array_key_exists($item->task_key, $corrections)) {
                    return;
                }

                $note = filled($corrections[$item->task_key]) ? $corrections[$item->task_key] : $reason;

                $item->update([
                    'requires_correction' => true,
                    'correction_note' => $note,
                    'review_status' => BriefingReviewStatus::Rejected->value,
                    'rejection_reason' => $note,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ]);
            });

            $this->audit($locked, BriefingBundleStatus::SupervisorReview, BriefingBundleStatus::Rejected, $actor, $reason, [
                'correction_task_keys' => array_keys($corrections),
            ]);

            if ($reopenForCorrection) {
                $locked->update(['status' => BriefingBundleStatus::Reopened->value]);
                $this->audit($locked, BriefingBundleStatus::Rejected, BriefingBundleStatus::Reopened, $actor, 'Dibuka untuk perbaikan.');
            }
        }, $reopenForCorrection ? 'Bundle ditolak dan dibuka untuk perbaikan.' : 'Bundle briefing ditolak.');
    }

    /**
     * @param  callable(BriefingRecord): void  $apply
     */
    private function decide(User $actor, BriefingRecord $bundle, BriefingBundleStatus $target, ?CarbonInterface $seenUpdatedAt, callable $apply, string $message): BriefingBundleReviewResult
    {
        return DB::transaction(function () use ($actor, $bundle, $target, $seenUpdatedAt, $apply, $message): BriefingBundleReviewResult {
            $locked = BriefingRecord::query()->whereKey($bundle->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status === null) {
                return BriefingBundleReviewResult::denied('not_found', 'Bundle briefing tidak ditemukan.');
            }

            if (! $this->policy->canReview($actor, $locked)) {
                return BriefingBundleReviewResult::denied('unauthorized', 'Anda tidak berwenang mereview bundle cabang ini.');
            }

            if ($locked->status === $target) {
                return BriefingBundleReviewResult::alreadyApplied($locked, 'Keputusan ini sudah tersimpan sebelumnya.');
            }

            if ($locked->status !== BriefingBundleStatus::SupervisorReview) {
                return BriefingBundleReviewResult::denied('not_awaiting_review', 'Bundle ini tidak sedang menunggu review.');
            }

            if ($seenUpdatedAt !== null && $locked->updated_at->greaterThan($seenUpdatedAt)) {
                return BriefingBundleReviewResult::denied('stale', 'Bundle sudah berubah sejak dibuka. Muat ulang lalu review kembali.');
            }

            if (! $this->completeness->evaluate($locked)->isComplete()) {
                return BriefingBundleReviewResult::denied('incomplete', 'Bundle belum lengkap dan belum dapat direview.');
            }

            $apply($locked);

            RecomputeBriefingBranchScoreJob::dispatchForBundle($locked);

            return BriefingBundleReviewResult::applied($locked->refresh(), $message);
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function audit(BriefingRecord $bundle, BriefingBundleStatus $from, BriefingBundleStatus $to, User $actor, ?string $reason, ?array $metadata = null): void
    {
        $bundle->transitions()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_id' => $actor->id,
            'reason' => filled($reason) ? $reason : null,
            'source' => BriefingTransitionSource::UserAction->value,
            'metadata' => $metadata,
        ]);
    }
}
