<?php

namespace App\Policies;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\User;

/**
 * docs/products/daily-briefing/remediation/prd.md §10.2/§12.3/§14. Review actions require the
 * `review briefing items` permission AND the item's branch snapshot
 * (briefing_records.branch_id, not users.branch_id) to be within accessibleBranchIds(), unless
 * the reviewer canAccessAllBranches(). Mirrors the same branch-scope mechanism every other
 * branch-scoped Policy in this app uses (see StoreSalesOrderPolicy).
 */
class BriefingItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view briefing items');
    }

    public function view(User $user, BriefingItem $item): bool
    {
        return $user->can('view briefing items') && $this->canAccessItemBranch($user, $item);
    }

    public function approve(User $user, BriefingItem $item): bool
    {
        return $user->can('review briefing items')
            && $this->canAccessItemBranch($user, $item)
            && ($item->review_status?->isAwaitingSupervisorReview() ?? false);
    }

    public function reject(User $user, BriefingItem $item): bool
    {
        return $user->can('review briefing items')
            && $this->canAccessItemBranch($user, $item)
            && ($item->review_status?->isAwaitingSupervisorReview() ?? false);
    }

    public function reopen(User $user, BriefingItem $item): bool
    {
        return $user->can('review briefing items')
            && $this->canAccessItemBranch($user, $item)
            && $item->review_status === BriefingReviewStatus::Rejected;
    }

    private function canAccessItemBranch(User $user, BriefingItem $item): bool
    {
        return $user->canAccessBranch($item->record?->branch_id);
    }
}
