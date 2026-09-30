<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;

/**
 * docs/rnd-internal-memo-simplification-prd.md §7.5: Menu removal only deletes the Menu's own
 * snapshot and Material rows (cascade FK); items belonging to other Menus on the same Memo are
 * untouched, and there is no status gate — "Menu dapat dihapus kapan saja". Extracted from
 * ViewRndInternalMemo::removeMenu() as its own use case (Phase 2).
 */
class RemoveMenuFromInternalMemoAction
{
    public function execute(RndInternalMemo $memo, int $menuId): void
    {
        $menu = RndInternalMemoMenu::query()
            ->where('rnd_internal_memo_id', $memo->id)
            ->findOrFail($menuId);

        $menu->delete();
    }
}
