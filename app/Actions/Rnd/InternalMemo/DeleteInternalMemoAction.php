<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\RndInternalMemo;

/**
 * docs/rnd-internal-memo-simplification-prd.md §6/§7.5. The simplified UI removed every control
 * that could move a Memo out of Finalized/Archived, so gating delete on status would permanently
 * strand a Memo finalized under the old workflow with no way to remove it. Authorization
 * (RndInternalMemoPolicy::delete) is permission-only; this Action has no further guard.
 */
class DeleteInternalMemoAction
{
    public function execute(RndInternalMemo $memo): void
    {
        $memo->delete();
    }
}
