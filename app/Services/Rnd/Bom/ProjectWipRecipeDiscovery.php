<?php

namespace App\Services\Rnd\Bom;

use App\Models\RndProjectBom;
use App\Services\EsbBillOfMaterialService;
use Illuminate\Support\Facades\Cache;

/**
 * The Project Product page's existing WIP mapping, extracted unchanged so the Shelf Life section
 * and the Ready/Released gate can reuse it server-side (docs/rnd-wip-shelf-life-prd.md §9.7,
 * §17.1). For each WIP component of a Main BOM it finds that WIP's own recipe in ESB; results are
 * cached per Main BOM. It is one level deep — the same scope the Project page has always mapped.
 */
class ProjectWipRecipeDiscovery
{
    public const CACHE_PREFIX = 'rnd.wip-recipes.v4.';

    public function __construct(private readonly EsbBillOfMaterialService $billOfMaterials) {}

    /**
     * WIP rule used by the Project mapping: product code prefixed "BW" or category "Barang WIP".
     *
     * @param  array<string, mixed>  $component
     */
    public function isWipComponent(array $component): bool
    {
        $productCode = strtoupper(trim((string) ($component['productCode'] ?? '')));
        $categoryName = trim((string) ($component['categoryName'] ?? ''));

        return str_starts_with($productCode, 'BW') || mb_strtolower($categoryName) === 'barang wip';
    }

    /**
     * Cached recipes of the WIP components of one Main BOM.
     *
     * @param  array<string, mixed>  $mainDetail
     * @return list<array{bomID: int, bomCode: string, bomName: string, productDetailID: int, productCode: string, productName: string, uomName: string, sourceQty: float, sourceUnit: string, bomDetails: list<array<string, mixed>>}>
     */
    public function recipesFor(RndProjectBom $mainBom, array $mainDetail, bool $force = false): array
    {
        $cacheKey = self::CACHE_PREFIX.$mainBom->esb_bom_id;

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(30), fn (): array => $this->discover($mainBom, $mainDetail));
    }

    /**
     * Cached recipes only — never calls ESB. Null when the mapping has not been cached yet.
     *
     * @return list<array<string, mixed>>|null
     */
    public function cachedRecipesFor(RndProjectBom $mainBom): ?array
    {
        $recipes = Cache::get(self::CACHE_PREFIX.$mainBom->esb_bom_id);

        return is_array($recipes) ? $recipes : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function normalizedBomRows(array $rows): array
    {
        return array_values(array_map(
            fn (array $item): array => [
                'ID' => (int) ($item['ID'] ?? 0),
                'productID' => (int) ($item['productID'] ?? 0),
                'productDetailID' => (int) ($item['productDetailID'] ?? 0),
                'productCode' => (string) ($item['productCode'] ?? ''),
                'productName' => (string) ($item['productName'] ?? ''),
                'categoryName' => (string) ($item['categoryName'] ?? ''),
                'uomName' => (string) ($item['uomName'] ?? ''),
                'lastHPP' => (float) ($item['lastHPP'] ?? $item['lastHpp'] ?? $item['price'] ?? 0),
                'qty' => (float) ($item['qty'] ?? 0),
                'yieldPercent' => (float) ($item['yieldPercent'] ?? 0),
                'tolerancePercent' => (float) ($item['tolerancePercent'] ?? 0),
                'printGroup' => (string) ($item['printGroup'] ?? ''),
                'subtitution' => is_array($item['subtitution'] ?? null) ? $item['subtitution'] : [],
            ],
            $rows,
        ));
    }

    /**
     * @param  array<string, mixed>  $mainDetail
     * @return list<array<string, mixed>>
     */
    private function discover(RndProjectBom $mainBom, array $mainDetail): array
    {
        $recipes = [];

        foreach ($mainDetail['bomDetails'] ?? [] as $component) {
            if (! $this->isWipComponent($component)) {
                continue;
            }

            $productDetailId = (int) ($component['productDetailID'] ?? 0);
            $productCode = strtoupper(trim((string) ($component['productCode'] ?? '')));
            $productName = (string) ($component['productName'] ?? '');
            $productId = (int) ($component['productID'] ?? 0);
            $candidates = $this->billOfMaterials->getBillOfMaterials([
                'productName' => $productName,
                'limit' => 100,
            ]);

            foreach ($candidates['data'] as $candidate) {
                $candidateBomId = (int) ($candidate['bomID'] ?? 0);
                if ($candidateBomId < 1 || $candidateBomId === $mainBom->esb_bom_id) {
                    continue;
                }

                $detail = $this->billOfMaterials->getBillOfMaterial($candidateBomId);
                $sameProductDetail = (int) ($detail['productDetailID'] ?? 0) === $productDetailId;
                $sameMasterProduct = $productId > 0
                    && (int) ($detail['productID'] ?? 0) === $productId;
                $sameProductCode = $productCode !== ''
                    && strtoupper(trim((string) ($detail['productCode'] ?? ''))) === $productCode;

                if (! $sameProductDetail && ! $sameMasterProduct && ! $sameProductCode) {
                    continue;
                }

                $detail['bomDetails'] = $this->normalizedBomRows($detail['bomDetails'] ?? []);
                $recipes[$candidateBomId] = [
                    'bomID' => $candidateBomId,
                    'bomCode' => (string) ($detail['bomCode'] ?? $candidate['bomCode'] ?? ''),
                    'bomName' => (string) ($detail['bomName'] ?? $candidate['bomName'] ?? ''),
                    'productDetailID' => $productDetailId,
                    'productCode' => $productCode,
                    'productName' => $productName,
                    'uomName' => (string) ($detail['uomName'] ?? $component['uomName'] ?? ''),
                    'sourceQty' => (float) ($component['qty'] ?? 0),
                    'sourceUnit' => (string) ($component['uomName'] ?? ''),
                    'bomDetails' => $detail['bomDetails'],
                ];
            }
        }

        return array_values($recipes);
    }
}
