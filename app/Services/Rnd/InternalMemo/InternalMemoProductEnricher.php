<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemoMaterial;
use App\Models\RndInternalMemoMenu;
use App\Services\EsbCompanyProductService;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Enriches a Menu's Material rows with Product data from ESB Master Product
 * (docs/rnd-internal-memo-simplification-prd.md §7.2 step 6, Phase 3). Product detail is only
 * fetched once per unique `esb_product_detail_id` across the Menu, not once per row.
 *
 * Purchase UOM is deliberately left unset here: the Phase 0 audit proved no field on the Product
 * detail contract (`unit`, `baseUnit`, `conversionFactor`) is documented or confirmed to represent
 * a purchasing unit distinct from the product detail's own UOM. Inventing that mapping would
 * violate the PRD's explicit instruction not to guess Purchase UOM. Every material still gets its
 * raw Product detail snapshot and a `product_synced_at` timestamp so the UI can show "Purchase
 * UOM belum tersedia" instead of silently having no data at all, and so a human can later confirm
 * the real field once ESB's contract is proven.
 */
class InternalMemoProductEnricher
{
    public function __construct(private readonly EsbCompanyProductService $products) {}

    public function enrichMenu(RndInternalMemoMenu $menu): void
    {
        $materials = $menu->materials()->get();

        /** @var Collection<int, Collection<int, RndInternalMemoMaterial>> $byProductDetailId */
        $byProductDetailId = $materials
            ->filter(fn (RndInternalMemoMaterial $material): bool => $material->esb_product_detail_id !== null)
            ->groupBy('esb_product_detail_id');

        foreach ($byProductDetailId as $productDetailId => $group) {
            $detail = $this->fetchDetail($menu->company_code, (int) $productDetailId);

            foreach ($group as $material) {
                $material->update([
                    'product_detail_snapshot' => $detail,
                    'product_synced_at' => now(),
                ]);
            }
        }

        // Materials without a Product Detail ID cannot be looked up at all (EsbService::
        // findActiveProductDetail requires one) — still stamp them as "checked" so the UI does
        // not confuse "no Product Detail ID" with "not yet synced".
        $materials
            ->filter(fn (RndInternalMemoMaterial $material): bool => $material->esb_product_detail_id === null)
            ->each(fn (RndInternalMemoMaterial $material) => $material->update(['product_synced_at' => now()]));
    }

    /** @return array<string, mixed>|null */
    private function fetchDetail(string $companyCode, int $productDetailId): ?array
    {
        try {
            return $this->products->detailByProductDetailId($companyCode, $productDetailId);
        } catch (RuntimeException) {
            return null;
        }
    }
}
