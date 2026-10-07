<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemoMaterial;
use App\Models\RndInternalMemoMenu;
use App\Services\EsbCompanyProductService;
use App\Services\EsbCoreClient;
use App\Services\Rnd\BomCalculationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolves one Menu's BOM through ESB Core using the Menu Company Code into flat
 * App\Models\RndInternalMemoMaterial rows, recursing into WIP/Assembly components until base
 * materials and packaging are reached (docs/rnd-internal-memo-prd.md §7.4, §9).
 *
 * `quantity_per_menu` on every row is propagated through every Assembly level for exactly one
 * unit of the Menu. WIP requirements are divided by the active output-unit conversion when ESB
 * provides it; tolerance remains excluded from Memo forecasting.
 */
class InternalMemoBomResolver
{
    /**
     * docs/rnd-internal-memo-simplification-prd.md §9.3: "batas kedalaman aman tercapai" stops
     * traversal. The Phase 0 audit found no existing precedent for this guard — only the
     * circular-BOM-ID check existed — so this is new protection against a pathological or
     * misconfigured BOM chain that never repeats a bomID but never terminates either.
     */
    private const MAX_ASSEMBLY_DEPTH = 10;

    /**
     * WIP name prefixes that are not expanded: these WIPs (e.g. "PRX | CRP02", premix supplied by
     * Bloomery Premix) have no Assembly BOM in the Memo's company, so they are kept as a WIP item
     * without searching for a child BOM and without a "belum memiliki BOM turunan" blocker.
     */
    private const UNEXPANDED_WIP_PREFIXES = ['PRX'];

    /** @var array<int, float> */
    private array $outputConversionFactors = [];

    private string $companyCode = '';

    public function __construct(
        private EsbCoreClient $esbCore,
        private EsbCompanyProductService $products,
        private BomCalculationService $bomCalculation,
    ) {}

    /**
     * @return array{blockers: list<string>, warnings: list<string>, bom_snapshot: array<string, mixed>}
     */
    public function resolve(RndInternalMemoMenu $menu): array
    {
        $this->outputConversionFactors = [];
        $this->companyCode = mb_strtoupper(trim($menu->company_code));

        if ($this->companyCode === '') {
            throw new RuntimeException('Company Code Menu wajib tersedia untuk mengambil BOM.');
        }

        // Fetch before mutating anything: if ESB is unreachable, the exception propagates here
        // and the Menu's last valid Material snapshot is never touched
        // (docs/rnd-internal-memo-simplification-prd.md §13, "kegagalan refresh tidak boleh
        // menghapus snapshot valid terakhir").
        $bomDetail = $this->fetchBom($menu->esb_bom_id);
        $blockers = [];
        $warnings = [];

        // The delete + rebuild happens atomically: any exception during the walk (e.g. a
        // connection failure mid-recursion while searching for a WIP's Assembly) rolls back the
        // delete too, so a partial failure never leaves the Menu with fewer materials than its
        // last successful sync.
        DB::transaction(function () use ($menu, $bomDetail, &$blockers, &$warnings): void {
            $menu->materials()->delete();

            $this->walk(
                menu: $menu,
                bomDetail: $bomDetail,
                multiplier: 1.0,
                parentMaterialId: null,
                depth: 0,
                visitedBomIds: [$menu->esb_bom_id],
                path: [$this->bomLabel($bomDetail, $menu->esb_bom_id)],
                blockers: $blockers,
                warnings: $warnings,
            );
        });

        return ['blockers' => $blockers, 'warnings' => $warnings, 'bom_snapshot' => $bomDetail];
    }

    /**
     * @param  array<string, mixed>  $bomDetail
     * @param  list<int>  $visitedBomIds
     * @param  list<string>  $path
     * @param  list<string>  $blockers
     * @param  list<string>  $warnings
     */
    private function walk(RndInternalMemoMenu $menu, array $bomDetail, float $multiplier, ?int $parentMaterialId, int $depth, array $visitedBomIds, array $path, array &$blockers, array &$warnings): void
    {
        $currentBomId = (int) ($bomDetail['bomID'] ?? end($visitedBomIds));
        $currentBomCode = filled($bomDetail['bomCode'] ?? null) ? (string) $bomDetail['bomCode'] : null;

        foreach ($bomDetail['bomDetails'] ?? [] as $component) {
            if (! is_array($component)) {
                continue;
            }

            $propagatedQty = $this->bomCalculation->componentRequirement($component, $multiplier);
            if ($propagatedQty <= 0) {
                continue;
            }
            $identity = $this->identity($component);
            if ($identity['productDetailID'] === null && $identity['productID'] === null && $identity['productCode'] === null) {
                $warnings[] = "Bahan \"{$identity['productName']}\" pada jalur ".implode(' → ', $path).' tidak mempunyai Product Code, Product ID, maupun Product Detail ID; dicocokkan lewat nama dan UOM.';
            } elseif ($identity['productCode'] === null) {
                $warnings[] = "Bahan \"{$identity['productName']}\" pada jalur ".implode(' → ', $path).' tidak mempunyai Product Code.';
            }
            if ($identity['productDetailID'] === null) {
                $warnings[] = "Bahan \"{$identity['productName']}\" pada jalur ".implode(' → ', $path).' tidak mempunyai Product Detail ID.';
            }

            $categoryName = mb_strtolower(trim((string) ($component['categoryName'] ?? '')));
            $code = strtoupper((string) ($identity['productCode'] ?? ''));
            $isWip = str_contains($categoryName, 'wip') || preg_match('/^BW[-_]?\d*/i', $code) === 1;
            $isPackaging = ! $isWip && (str_contains($categoryName, 'packaging') || str_contains($categoryName, 'kemasan'));

            if (! $isWip) {
                $this->createMaterialRow($menu, $parentMaterialId, $currentBomId, $currentBomCode, $path, $depth, $component, $propagatedQty, false, $isPackaging);

                continue;
            }

            if ($this->isUnexpandedWip($identity['productName'])) {
                $this->createMaterialRow($menu, $parentMaterialId, $currentBomId, $currentBomCode, $path, $depth, $component, $propagatedQty, true, false);

                continue;
            }

            if ($depth >= self::MAX_ASSEMBLY_DEPTH) {
                $blockers[] = 'Batas kedalaman Assembly maksimum ('.self::MAX_ASSEMBLY_DEPTH.' level) tercapai pada jalur '.implode(' → ', $path).' untuk WIP "'.$identity['productName'].'".';
                $this->createMaterialRow($menu, $parentMaterialId, $currentBomId, $currentBomCode, $path, $depth, $component, $propagatedQty, true, false);

                continue;
            }

            $childBom = $this->findBomForComponent($component);

            if ($childBom === null) {
                $blockers[] = "WIP \"{$identity['productName']}\" (".($identity['productCode'] ?? '-').') pada jalur '.implode(' → ', $path).' belum memiliki BOM turunan yang cocok di ESB.';
                $this->createMaterialRow($menu, $parentMaterialId, $currentBomId, $currentBomCode, $path, $depth, $component, $propagatedQty, true, false);

                continue;
            }

            $childBomId = (int) ($childBom['bomID'] ?? 0);

            if (in_array($childBomId, $visitedBomIds, true)) {
                $blockers[] = 'Circular BOM terdeteksi pada jalur '.implode(' → ', [...$path, $this->bomLabel($childBom, $childBomId)]).'.';
                $this->createMaterialRow($menu, $parentMaterialId, $currentBomId, $currentBomCode, $path, $depth, $component, $propagatedQty, true, false);

                continue;
            }

            $wipRow = $this->createMaterialRow($menu, $parentMaterialId, $currentBomId, $currentBomCode, $path, $depth, $component, $propagatedQty, true, false);
            $recipeMultiplier = $this->bomCalculation->childRecipeMultiplier(
                $component,
                $childBom,
                $propagatedQty,
                fn (array $bom): ?float => $this->resolveOutputConversionFromMasterProduct($bom),
            );

            if (! $recipeMultiplier['is_proportional']) {
                $warnings[] = "Hasil per resep WIP \"{$identity['productName']}\" pada jalur ".implode(' → ', [...$path, $this->bomLabel($childBom, $childBomId)]).' belum tersedia; perhitungan sementara memakai 1 unit hasil per resep.';
            }

            $this->walk(
                menu: $menu,
                bomDetail: $childBom,
                multiplier: $recipeMultiplier['multiplier'],
                parentMaterialId: $wipRow->id,
                depth: $depth + 1,
                visitedBomIds: [...$visitedBomIds, $childBomId],
                path: [...$path, $this->bomLabel($childBom, $childBomId)],
                blockers: $blockers,
                warnings: $warnings,
            );
        }
    }

    /** @param array<string, mixed> $component */
    private function createMaterialRow(RndInternalMemoMenu $menu, ?int $parentMaterialId, int $sourceBomId, ?string $sourceBomCode, array $path, int $depth, array $component, float $quantityPerMenu, bool $isWip, bool $isPackaging): RndInternalMemoMaterial
    {
        $identity = $this->identity($component);

        return $menu->materials()->create([
            'parent_material_id' => $parentMaterialId,
            'source_bom_id' => $sourceBomId,
            'source_bom_code' => $sourceBomCode,
            'source_path' => $path,
            'depth' => $depth,
            'esb_product_id' => $identity['productID'],
            'esb_product_detail_id' => $identity['productDetailID'],
            'product_code' => $identity['productCode'],
            'product_name' => $identity['productName'],
            'category_name' => filled($component['categoryName'] ?? null) ? (string) $component['categoryName'] : null,
            'uom_id' => filled($component['uomID'] ?? null) ? (int) $component['uomID'] : null,
            'uom_name' => (string) ($component['uomName'] ?? '-'),
            'quantity_per_menu' => $quantityPerMenu,
            'net_quantity' => 0,
            'is_wip' => $isWip,
            'is_packaging' => $isPackaging,
            'product_snapshot' => $component,
        ]);
    }

    /** "PRX | CRP02" → prefix PRX, matched against UNEXPANDED_WIP_PREFIXES. */
    private function isUnexpandedWip(string $productName): bool
    {
        $prefix = mb_strtoupper(trim(explode('|', $productName, 2)[0]));

        return str_contains($productName, '|') && in_array($prefix, self::UNEXPANDED_WIP_PREFIXES, true);
    }

    /** @return array{productDetailID: ?int, productID: ?int, productCode: ?string, productName: string} */
    private function identity(array $component): array
    {
        $productDetailId = (int) ($component['productDetailID'] ?? 0);
        $productId = (int) ($component['productID'] ?? 0);
        $productCode = trim((string) ($component['productCode'] ?? ''));

        return [
            'productDetailID' => $productDetailId > 0 ? $productDetailId : null,
            'productID' => $productId > 0 ? $productId : null,
            'productCode' => $productCode !== '' ? $productCode : null,
            'productName' => trim((string) ($component['productName'] ?? 'Tanpa Nama')),
        ];
    }

    /**
     * Finds the active BOM producing the given WIP/Assembly component, matching identity in the
     * priority order productDetailID > productID > productCode (§9.1). The Browse BOM list is
     * searched by product name; every candidate is fetched and checked because a Menu-name-only
     * filter is not proven precise enough on its own.
     *
     * @param  array<string, mixed>  $component
     * @return array<string, mixed>|null
     */
    private function findBomForComponent(array $component): ?array
    {
        $identity = $this->identity($component);
        $cacheKey = 'rnd.internal-memo.bom-search.'.mb_strtolower($this->companyCode).'.'.md5(json_encode($identity, JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($identity): ?array {
            if ($identity['productName'] === '' || $identity['productName'] === 'Tanpa Nama') {
                return null;
            }

            $response = $this->esbCore->request($this->companyCode, 'get', '/product/bom', [
                'productName' => $identity['productName'],
                'limit' => 100,
            ]);
            $list = $this->esbCore->successfulResult($response, 'mencari BOM Assembly', $this->companyCode, '/product/bom');
            $candidates = is_array($list['data'] ?? null) ? $list['data'] : [];

            foreach ($candidates as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $candidateBomId = (int) ($candidate['bomID'] ?? 0);
                if ($candidateBomId < 1) {
                    continue;
                }

                $detail = $this->fetchBom($candidateBomId);
                $matches = ($identity['productDetailID'] !== null && (int) ($detail['productDetailID'] ?? 0) === $identity['productDetailID'])
                    || ($identity['productID'] !== null && (int) ($detail['productID'] ?? 0) === $identity['productID'])
                    || ($identity['productCode'] !== null && strtoupper(trim((string) ($detail['productCode'] ?? ''))) === strtoupper($identity['productCode']));

                if ($matches) {
                    $detail['bomID'] = $candidateBomId;

                    return $detail;
                }
            }

            return null;
        });
    }

    /** @return array<string, mixed> */
    private function fetchBom(int $bomId): array
    {
        if ($bomId < 1) {
            throw new RuntimeException('BOM ID tidak valid.');
        }

        return Cache::remember('rnd.internal-memo.bom-detail.'.mb_strtolower($this->companyCode).".{$bomId}", now()->addMinutes(15), function () use ($bomId): array {
            $response = $this->esbCore->request($this->companyCode, 'get', '/product/bom/'.$bomId);

            return $this->esbCore->successfulResult($response, 'mengambil detail BOM', $this->companyCode, '/product/bom/'.$bomId);
        });
    }

    /** @param array<string, mixed> $bom */
    private function resolveOutputConversionFromMasterProduct(array $bom): ?float
    {
        $productDetailId = (int) ($bom['productDetailID'] ?? 0);
        if ($productDetailId < 1) {
            return null;
        }

        if (array_key_exists($productDetailId, $this->outputConversionFactors)) {
            return $this->outputConversionFactors[$productDetailId] ?: null;
        }

        try {
            $detail = $this->products->detailByProductDetailId($this->companyCode, $productDetailId);
            $productDetail = (array) ($detail['matchedProductDetail'] ?? $detail ?? []);
            $factor = is_numeric($productDetail['conversionFactor'] ?? null) && (float) $productDetail['conversionFactor'] > 0
                ? (float) $productDetail['conversionFactor']
                : 0.0;
        } catch (RuntimeException) {
            $factor = 0.0;
        }

        $this->outputConversionFactors[$productDetailId] = $factor;

        return $factor > 0 ? $factor : null;
    }

    private function bomLabel(array $bomDetail, int $bomId): string
    {
        $name = trim((string) ($bomDetail['bomName'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($bomDetail['bomCode'] ?? ''));

        return $code !== '' ? $code : 'BOM-'.$bomId;
    }
}
