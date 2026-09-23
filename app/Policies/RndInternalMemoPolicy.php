<?php

namespace App\Policies;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;

/**
 * Permission strings match docs/rnd-internal-memo-prd.md §5.2. Role names are never
 * hardcoded; only permissions and record status govern access.
 */
class RndInternalMemoPolicy
{
    /** @var list<RndInternalMemoStatus> */
    private const OPEN_STATUSES = [
        RndInternalMemoStatus::Draft,
        RndInternalMemoStatus::NeedsAttention,
        RndInternalMemoStatus::Ready,
    ];

    public function viewAny(User $user): bool
    {
        return $user->can('view any rnd internal memo');
    }

    public function view(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('view rnd internal memo');
    }

    public function create(User $user): bool
    {
        return $user->can('create rnd internal memo');
    }

    /**
     * Metadata and Menu selection stay editable only while the memo is Draft (§6 table).
     */
    public function update(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('update rnd internal memo') && $memo->status->isEditable();
    }

    /**
     * Sync is only meaningful before the memo is locked and while no other sync is running.
     */
    public function sync(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('sync rnd internal memo') && in_array($memo->status, self::OPEN_STATUSES, true);
    }

    /**
     * Forecast Quantity and Shelf Life are local per-Menu data (§7.3, §11) that stay editable
     * through the whole "not yet locked" lifecycle, not only Draft like `update` — a memo stuck
     * in NeedsAttention must still let the user fix a missing Forecast or Shelf Life value.
     */
    public function updateForecast(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('update rnd internal memo') && in_array($memo->status, self::OPEN_STATUSES, true);
    }

    public function finalize(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('finalize rnd internal memo') && $memo->status === RndInternalMemoStatus::Ready;
    }

    /** A revision may only be created from an already Finalized memo (§6, §7.5). */
    public function createRevision(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('create rnd internal memo revision') && $memo->status === RndInternalMemoStatus::Finalized;
    }

    public function generatePdf(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('generate rnd internal memo pdf') && $memo->status === RndInternalMemoStatus::Finalized;
    }

    public function downloadPdf(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('download rnd internal memo pdf');
    }

    public function archive(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('archive rnd internal memo')
            && in_array($memo->status, [RndInternalMemoStatus::Finalized, RndInternalMemoStatus::Archived], true);
    }

    public function delete(User $user, RndInternalMemo $memo): bool
    {
        return $user->can('delete rnd internal memo')
            && in_array($memo->status, self::OPEN_STATUSES, true);
    }
}
