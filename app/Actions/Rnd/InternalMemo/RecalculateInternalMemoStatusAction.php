<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Services\Rnd\InternalMemo\InternalMemoValidationService;

/**
 * docs/rnd-internal-memo-prd.md §6, §15. Recomputes Draft/NeedsAttention/Ready after anything
 * that can change readiness — a sync run finishing (called while the memo is still Syncing, to
 * decide what it becomes next) or a Forecast/Shelf Life edit. Finalized and Archived are left
 * untouched — those transitions belong to their own dedicated Actions.
 */
class RecalculateInternalMemoStatusAction
{
    public function __construct(private InternalMemoValidationService $validator) {}

    public function execute(RndInternalMemo $memo): RndInternalMemo
    {
        if (! in_array($memo->status, [
            RndInternalMemoStatus::Draft,
            RndInternalMemoStatus::Syncing,
            RndInternalMemoStatus::NeedsAttention,
            RndInternalMemoStatus::Ready,
        ], true)) {
            return $memo;
        }

        if ($memo->source_synced_at === null) {
            $memo->update(['status' => RndInternalMemoStatus::Draft]);

            return $memo->refresh();
        }

        $result = $this->validator->validate($memo);
        $memo->update(['status' => $result['blockers'] === [] ? RndInternalMemoStatus::Ready : RndInternalMemoStatus::NeedsAttention]);

        return $memo->refresh();
    }
}
