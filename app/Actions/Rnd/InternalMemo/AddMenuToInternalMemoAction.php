<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\RndProductEsbShelfLife;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * docs/rnd-internal-memo-prd.md §7.2. Takes the Menu row already fetched by the picker modal
 * (App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService::page) instead of re-fetching by
 * ID, because a single-Menu detail endpoint is not proven to exist (Phase 0 report, PRD §8.5).
 *
 * `release_date` defaults to the memo's period_month because the column is required
 * (§12.2) while the PRD flow only asks for it in the later "Melengkapi data per Menu" step
 * (§7.3); the R&D Operator refines it there.
 */
class AddMenuToInternalMemoAction
{
    /** @param array<string, mixed> $menu */
    public function execute(RndInternalMemo $memo, array $menu): RndInternalMemoMenu
    {
        if (! $memo->status->isEditable()) {
            throw new RuntimeException('Menu hanya dapat ditambahkan selama Memo berstatus Draft.');
        }

        $esbMenuId = (int) ($menu['menuID'] ?? 0);
        $bomId = (int) ($menu['bomID'] ?? 0);

        if ($esbMenuId < 1) {
            throw new RuntimeException('Menu tidak valid.');
        }

        if ($bomId < 1) {
            throw ValidationException::withMessages([
                'menu' => 'Menu ini belum memiliki BOM dan tidak dapat dipilih.',
            ]);
        }

        if ($memo->menus()->where('esb_menu_id', $esbMenuId)->exists()) {
            throw ValidationException::withMessages([
                'menu' => 'Menu ini sudah ada pada Memo.',
            ]);
        }

        $shelfLife = RndProductEsbShelfLife::forMenu($memo->company_code, $esbMenuId);
        $nextSortOrder = ((int) $memo->menus()->max('sort_order')) + 1;

        return $memo->menus()->create([
            'esb_menu_id' => $esbMenuId,
            'menu_code' => $menu['menuCode'] ?? null,
            'menu_name' => (string) ($menu['menuName'] ?? ''),
            'category_detail' => $menu['categoryDetail'] ?? null,
            'esb_bom_id' => $bomId,
            'bom_name' => $menu['bomName'] ?? null,
            'release_date' => $memo->period_month,
            'forecast_quantity' => 0,
            'shelf_life_value' => $shelfLife?->shelf_life_value,
            'shelf_life_unit' => $shelfLife?->shelf_life_unit,
            'storage_condition' => $shelfLife?->storage_condition,
            'sync_status' => RndInternalMemoMenuSyncStatus::Pending,
            'menu_snapshot' => $menu['raw'] ?? $menu,
            'sort_order' => $nextSortOrder,
        ]);
    }
}
