<?php

namespace App\Services\Kitchen;

use App\Enums\RndBomCatalogSyncStatus;
use App\Models\RndBomCatalog;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single source of BOM Assembly eligibility for production schedules
 * (docs/products/kitchen/production-schedule/prd.md §9). The picker and save Action
 * all ask this class, so a BOM the picker hides can never be added by a forged request. Only the
 * local catalog is read; ESB is never called from here.
 *
 * Assembly is the proven contract `bom_type_id = 1` (SyncBomCatalogAction::ASSEMBLY_BOM_TYPE_ID,
 * SyncBomCatalogActionTest). The type name is display-only and never admits a row.
 */
class ProductionBomEligibilityService
{
    public const ASSEMBLY_BOM_TYPE_ID = 1;

    /**
     * Catalog rows that may be added to a schedule, using the same active/synced Assembly catalog
     * used by R&D. No age or Kitchen-specific sync state is involved.
     *
     * @return Builder<RndBomCatalog>
     */
    public function eligibleQuery(): Builder
    {
        return RndBomCatalog::query()
            ->where('bom_type_id', self::ASSEMBLY_BOM_TYPE_ID)
            ->where('is_active', true)
            ->where('sync_status', RndBomCatalogSyncStatus::Synced->value)
            ->whereNotNull('product_detail_id')
            ->whereNotNull('product_code')->where('product_code', '!=', '')
            ->whereNotNull('product_name')->where('product_name', '!=', '')
            ->whereNotNull('uom_name')->where('uom_name', '!=', '');
    }

    /**
     * Why a catalog row cannot be scheduled, in user-facing words, or null when it can.
     */
    public function reasonFor(?RndBomCatalog $catalog): ?string
    {
        if ($catalog === null) {
            return 'BOM tidak ditemukan di katalog.';
        }

        return match (true) {
            $catalog->bom_type_id !== self::ASSEMBLY_BOM_TYPE_ID => 'Bukan BOM Assembly.',
            ! $catalog->is_active => 'BOM tidak aktif di ESB.',
            $catalog->sync_status !== RndBomCatalogSyncStatus::Synced => 'BOM belum tersinkron dengan benar ('.$catalog->sync_status?->getLabel().').',
            blank($catalog->esb_bom_id), blank($catalog->product_detail_id), blank($catalog->product_code), blank($catalog->product_name) => 'Produk hasil BOM tidak lengkap.',
            blank($catalog->uom_name) => 'Satuan hasil BOM tidak tersedia.',
            default => null,
        };
    }
}
