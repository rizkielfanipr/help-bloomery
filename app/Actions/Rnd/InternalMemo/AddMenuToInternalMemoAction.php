<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\RndProductEsbShelfLife;
use App\Services\Rnd\InternalMemo\InternalMemoBomResolver;
use App\Services\Rnd\InternalMemo\InternalMemoProductEnricher;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * docs/rnd-internal-memo-simplification-prd.md §7.2. Takes the Menu row already fetched by the
 * picker modal (App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService::page) instead of
 * re-fetching by ID, because a single-Menu detail endpoint is not proven to exist (Phase 0
 * report, PRD §8.5). "Menu dapat ditambahkan kapan saja" — there is no longer a Draft-only gate.
 *
 * BOM/Assembly resolution runs synchronously right after the Menu row is created (§7.2 steps
 * 3-7), matching the simplified PRD's removal of the old Draft → Syncing job workflow. A BOM
 * resolution failure does not fail the whole use case — the Menu stays added with sync_status
 * Failed so the UI can offer "Coba Ambil Ulang" (§7.2 last paragraph).
 *
 * `release_date` defaults to the memo's period_month because the column is required
 * (§12.2) while the PRD flow only asks for it in the later "Melengkapi data per Menu" step
 * (§7.3); the R&D Operator refines it there.
 */
class AddMenuToInternalMemoAction
{
    public function __construct(
        private readonly InternalMemoBomResolver $resolver,
        private readonly InternalMemoProductEnricher $productEnricher,
    ) {}

    /** @param array<string, mixed> $menu */
    public function execute(RndInternalMemo $memo, array $menu): RndInternalMemoMenu
    {
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

        $menuRecord = $memo->menus()->create([
            'company_code' => $memo->company_code,
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
            'sync_status' => RndInternalMemoMenuSyncStatus::Syncing,
            'menu_snapshot' => $menu['raw'] ?? $menu,
            'sort_order' => $nextSortOrder,
        ]);

        try {
            $result = $this->resolver->resolve($menuRecord);

            $menuRecord->update([
                'sync_status' => RndInternalMemoMenuSyncStatus::Synced,
                'synced_at' => now(),
                'sync_error' => $result['blockers'] === [] ? null : implode(' | ', $result['blockers']),
                'sync_warnings' => $result['warnings'] === [] ? null : $result['warnings'],
                'bom_snapshot' => $result['bom_snapshot'],
            ]);

            // Product/Purchase UOM enrichment never fails the whole Add Menu use case — the BOM
            // structure already stands on its own (§12.3, "Data BOM tetap tampil, Purchase UOM
            // diberi status belum tersedia").
            $this->productEnricher->enrichMenu($menuRecord);
        } catch (Throwable $exception) {
            $menuRecord->update([
                'sync_status' => RndInternalMemoMenuSyncStatus::Failed,
                'sync_error' => $exception->getMessage(),
            ]);
        }

        return $menuRecord->fresh();
    }
}
