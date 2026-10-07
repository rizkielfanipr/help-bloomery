<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoBomResolver;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogQuery;
use App\Services\Rnd\InternalMemo\InternalMemoProductEnricher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * docs/rnd-internal-memo-simplification-prd.md §7.2, docs/rnd-internal-memo-brand-prd.md §11.3,
 * §15.3. The caller only names a Menu ID; the Menu row is re-read server-side from the global BLSS
 * catalog snapshot, so nothing from the picker (company, branch, BOM ID, name) is trusted. The new
 * row always uses Company Code BLSS and no Menu–Branch row is written. "Menu dapat ditambahkan
 * kapan saja" — there is no Draft-only gate.
 *
 * BOM/Assembly resolution runs synchronously right after the Menu row is created. A BOM
 * resolution failure does not fail the whole use case — the Menu stays added with sync_status
 * Failed so the UI can offer "Coba Ambil Ulang".
 *
 * `release_date` defaults to the memo's period_month because the column is required; the R&D
 * Operator refines it later.
 */
class AddMenuToInternalMemoAction
{
    public function __construct(
        private readonly InternalMemoBomResolver $resolver,
        private readonly InternalMemoProductEnricher $productEnricher,
        private readonly InternalMemoMenuCatalogQuery $catalog,
    ) {}

    public function execute(RndInternalMemo $memo, int $esbMenuId): RndInternalMemoMenu
    {
        $catalogMenu = $esbMenuId > 0 ? $this->catalog->findSelectable($esbMenuId) : null;

        if ($catalogMenu === null) {
            throw ValidationException::withMessages(['menu' => 'Menu tidak ditemukan pada katalog ESB BLSS.']);
        }

        if ((int) $catalogMenu->bom_id < 1) {
            throw ValidationException::withMessages([
                'menu' => 'Menu ini belum memiliki BOM dan tidak dapat dipilih.',
            ]);
        }

        if ($memo->menus()->where('esb_menu_id', $esbMenuId)->exists()) {
            throw $this->duplicateMenu();
        }

        // Shelf Life is no longer kept per Menu (docs/rnd-wip-shelf-life-prd.md §19.1): new Menus
        // start without it and the retired Menu master is not consulted.
        try {
            $menuRecord = $memo->menus()->create([
                'company_code' => RndInternalMemo::COMPANY_CODE,
                'esb_menu_id' => $esbMenuId,
                'menu_code' => $catalogMenu->menu_code,
                'menu_name' => (string) $catalogMenu->menu_name,
                'category_detail' => $catalogMenu->category_detail,
                'esb_bom_id' => (int) $catalogMenu->bom_id,
                'bom_name' => $catalogMenu->bom_name,
                'release_date' => $memo->period_month,
                'forecast_quantity' => 0,
                'sync_status' => RndInternalMemoMenuSyncStatus::Syncing,
                'menu_snapshot' => $catalogMenu->raw_snapshot ?? [],
                'sort_order' => ((int) $memo->menus()->max('sort_order')) + 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateMenu();
        }

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
            // structure already stands on its own.
            $this->productEnricher->enrichMenu($menuRecord);
        } catch (Throwable $exception) {
            $menuRecord->update([
                'sync_status' => RndInternalMemoMenuSyncStatus::Failed,
                'sync_error' => $exception->getMessage(),
            ]);
        }

        return $menuRecord->fresh();
    }

    private function duplicateMenu(): ValidationException
    {
        return ValidationException::withMessages(['menu' => 'Menu ini sudah ada pada Memo.']);
    }
}
