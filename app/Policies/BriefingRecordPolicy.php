<?php

namespace App\Policies;

use App\Enums\BriefingBundleStatus;
use App\Models\BriefingRecord;
use App\Models\User;

/**
 * docs/daily-briefing-bundle-approval-prd.md §9.1/§14: bundle review requires `review briefing items`
 * AND the bundle's branch snapshot (never the submitter's current branch) inside
 * accessibleBranchIds(), unless the reviewer canAccessAllBranches(). Contributors gain nothing here
 * from being able to submit.
 */
class BriefingRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view briefing items');
    }

    public function view(User $user, BriefingRecord $bundle): bool
    {
        return $user->can('view briefing items') && $user->canAccessBranch($bundle->branch_id);
    }

    public function approve(User $user, BriefingRecord $bundle): bool
    {
        return $this->canReview($user, $bundle) && $bundle->status === BriefingBundleStatus::SupervisorReview;
    }

    public function reject(User $user, BriefingRecord $bundle): bool
    {
        return $this->canReview($user, $bundle) && $bundle->status === BriefingBundleStatus::SupervisorReview;
    }

    public function reopen(User $user, BriefingRecord $bundle): bool
    {
        return $this->canReview($user, $bundle)
            && in_array($bundle->status, [BriefingBundleStatus::Approved, BriefingBundleStatus::Rejected], true);
    }

    /**
     * Whether the user may act on this bundle at all, regardless of its current status — used to
     * acknowledge a repeated request idempotently without leaking another branch's bundle.
     */
    public function canReview(User $user, BriefingRecord $bundle): bool
    {
        return $user->can('review briefing items') && $user->canAccessBranch($bundle->branch_id);
    }
}
