<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

/**
 * Removes a hand-added product from a Memo's Product Active summary. Items that come from the Menu
 * BOMs are not affected; they disappear only when their Menu is removed or refreshed.
 */
class RemoveExtraProductFromInternalMemoAction
{
    public function execute(RndInternalMemo $memo, int $extraProductId, User $actor): void
    {
        if (! $actor->can('update', $memo)) {
            throw new AuthorizationException('Anda tidak berhak mengubah Memo Internal ini.');
        }

        $extra = $memo->extraProducts()->findOrFail($extraProductId);
        $extra->delete();

        Log::info('rnd internal memo extra product removed', ['memo_id' => $memo->id, 'extra_product_id' => $extraProductId, 'user_id' => $actor->id]);
    }
}
