<?php

namespace App\Services\Rnd\ShelfLife;

use App\Models\RndProductEsbShelfLife;
use App\Models\RndWipProduct;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Bulk lookup of WIP Shelf Life masters by Product Detail ID (docs/rnd-wip-shelf-life-prd.md
 * §13.3): IDs are de-duplicated, one query is run per call, results are keyed by Product Detail ID
 * and isolated to one company. Never calls ESB; legacy Menu-only rows never match.
 *
 * Masters are kept per Product on its base unit. A BOM may use another unit of the same WIP
 * (e.g. "Porsi"); such a Product Detail ID resolves to the base unit's master through the local
 * `rnd_wip_products` snapshot.
 */
class WipShelfLifeResolver
{
    /**
     * Active masters only — what counts as "Lengkap" for a Project (§9.6).
     *
     * @param  iterable<int|string|null>  $productDetailIds
     * @return EloquentCollection<int, RndProductEsbShelfLife>
     */
    public function activeMasters(iterable $productDetailIds, string $companyCode = RndProductEsbShelfLife::DEFAULT_COMPANY_CODE): EloquentCollection
    {
        return $this->query($productDetailIds, $companyCode, onlyActive: true);
    }

    /**
     * Active and inactive masters, so screens can tell "Tidak Aktif" apart from "Belum Diisi".
     *
     * @param  iterable<int|string|null>  $productDetailIds
     * @return EloquentCollection<int, RndProductEsbShelfLife>
     */
    public function masters(iterable $productDetailIds, string $companyCode = RndProductEsbShelfLife::DEFAULT_COMPANY_CODE): EloquentCollection
    {
        return $this->query($productDetailIds, $companyCode, onlyActive: false);
    }

    /**
     * Non-base Product Detail ID → base Product Detail ID of the same WIP Product. IDs that are
     * already base, or unknown locally, are left out.
     *
     * @param  iterable<int|string|null>  $productDetailIds
     * @return array<int, int>
     */
    public function baseProductDetailIds(iterable $productDetailIds, string $companyCode = RndProductEsbShelfLife::DEFAULT_COMPANY_CODE): array
    {
        $ids = $this->normalizedIds($productDetailIds);

        if ($ids->isEmpty()) {
            return [];
        }

        $productIdByDetail = RndWipProduct::query()
            ->where('company_code', $companyCode)
            ->where('is_base', false)
            ->whereIn('product_detail_id', $ids)
            ->whereNotNull('esb_product_id')
            ->pluck('esb_product_id', 'product_detail_id');

        if ($productIdByDetail->isEmpty()) {
            return [];
        }

        $baseByProduct = RndWipProduct::query()
            ->where('company_code', $companyCode)
            ->where('is_base', true)
            ->whereIn('esb_product_id', $productIdByDetail->unique()->values())
            ->pluck('product_detail_id', 'esb_product_id');

        return $productIdByDetail
            ->map(fn ($productId): ?int => isset($baseByProduct[$productId]) ? (int) $baseByProduct[$productId] : null)
            ->filter()
            ->mapWithKeys(fn (int $baseId, $detailId): array => [(int) $detailId => $baseId])
            ->all();
    }

    /**
     * @param  iterable<int|string|null>  $productDetailIds
     * @return EloquentCollection<int, RndProductEsbShelfLife>
     */
    private function query(iterable $productDetailIds, string $companyCode, bool $onlyActive): EloquentCollection
    {
        $ids = $this->normalizedIds($productDetailIds);

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        $baseIds = $this->baseProductDetailIds($ids, $companyCode);
        $masters = RndProductEsbShelfLife::query()
            ->wipMaster($companyCode)
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->whereIn('esb_product_detail_id', $ids->merge(array_values($baseIds))->unique()->values())
            ->get()
            ->keyBy('esb_product_detail_id');

        $resolved = new EloquentCollection;
        foreach ($ids as $id) {
            $master = $masters->get($id) ?? (isset($baseIds[$id]) ? $masters->get($baseIds[$id]) : null);
            if ($master !== null) {
                $resolved->put($id, $master);
            }
        }

        return $resolved;
    }

    /**
     * @param  iterable<int|string|null>  $productDetailIds
     * @return Collection<int, int>
     */
    private function normalizedIds(iterable $productDetailIds): Collection
    {
        return collect($productDetailIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }
}
