<?php

namespace App\Actions\Briefing;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * docs/products/daily-briefing/remediation/prd.md §20 (Phase 6). The only path that transitions a
 * submission's review status. Authorization is re-checked here (via BriefingItemPolicy) even
 * though callers also gate the action's visibility, so a cross-branch mutation is rejected
 * server-side regardless of how the action is invoked.
 */
class BriefingReviewAction
{
    public function approve(User $actor, BriefingItem $item): BriefingReviewResult
    {
        if (! $actor->can('approve', $item)) {
            return BriefingReviewResult::denied('forbidden', 'Anda tidak berhak menyetujui poin ini.');
        }

        DB::transaction(function () use ($actor, $item): void {
            $item->update([
                'review_status' => BriefingReviewStatus::Approved->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        return BriefingReviewResult::success($item, 'Poin disetujui.');
    }

    public function reject(User $actor, BriefingItem $item, string $reason): BriefingReviewResult
    {
        if (! $actor->can('reject', $item)) {
            return BriefingReviewResult::denied('forbidden', 'Anda tidak berhak menolak poin ini.');
        }

        if (trim($reason) === '') {
            return BriefingReviewResult::denied('reason_required', 'Alasan penolakan wajib diisi.');
        }

        DB::transaction(function () use ($actor, $item, $reason): void {
            $item->update([
                'review_status' => BriefingReviewStatus::Rejected->value,
                'rejection_reason' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
        });

        return BriefingReviewResult::success($item, 'Poin ditolak.');
    }

    /**
     * §11.2/§14: grants exactly one further edit on a rejected item, bypassing the deadline
     * check in BriefingSubmissionAction once. The grant is consumed on the staff member's next
     * successful save.
     */
    public function reopen(User $actor, BriefingItem $item, string $reason): BriefingReviewResult
    {
        if (! $actor->can('reopen', $item)) {
            return BriefingReviewResult::denied('forbidden', 'Anda tidak berhak membuka kembali poin ini.');
        }

        if (trim($reason) === '') {
            return BriefingReviewResult::denied('reason_required', 'Alasan pembukaan kembali wajib diisi.');
        }

        DB::transaction(function () use ($actor, $item, $reason): void {
            $item->update([
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
                'reopen_reason' => $reason,
            ]);
        });

        return BriefingReviewResult::success($item, 'Poin dibuka kembali untuk diperbaiki.');
    }
}
