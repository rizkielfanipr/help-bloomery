<?php

namespace App\Services;

use App\Models\RndProject;
use App\Services\Rnd\BomCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RndProjectMaterialForecastService
{
    /** @var array<int, float> */
    private array $outputConversionFactors = [];

    public function __construct(
        private readonly EsbService $esbService,
        private readonly BomCalculationService $bomCalculation,
    ) {}

    /**
     * @return array{rows: list<array{code: string, name: string, unit: string, quantity: float, effective_quantity: float, is_calculated: bool}>, projection_details: list<array{name: string, quantity: float, effective_quantity: float, is_calculated: bool}>, forecast_percentage: float, projected_units: float, effective_projected_units: float, projection_products: int, projected_products: int, warnings: list<string>}
     */
    public function calculate(RndProject $project, string $forecastType = 'kitchen'): array
    {
        $this->outputConversionFactors = [];
        $usageType = $forecastType === 'store' ? 'menu' : 'main';
        $materials = [];
        $projectionDetails = [];
        $projectedUnits = 0.0;
        $effectiveProjectedUnits = 0.0;
        $projectionProducts = 0;
        $projectedProducts = 0;
        $warnings = [];
        $forecastPercentage = min(100, max(1, (float) ($project->forecast_percentage ?? 100)));

        foreach ($project->products as $product) {
            $projectedQuantity = (float) $product->salesProjections->sum('target_quantity');

            if ($projectedQuantity <= 0) {
                continue;
            }

            $targetQuantity = $projectedQuantity * ($forecastPercentage / 100);
            $projectedUnits += $projectedQuantity;
            $effectiveProjectedUnits += $targetQuantity;
            $projectionProducts++;
            $rootBoms = $product->boms->filter(fn ($bom): bool => $bom->pivot->usage_type === $usageType);
            $projectionDetails[] = [
                'name' => $product->name,
                'quantity' => $projectedQuantity,
                'effective_quantity' => $targetQuantity,
                'is_calculated' => $rootBoms->isNotEmpty(),
            ];

            if ($rootBoms->isEmpty()) {
                continue;
            }

            $projectedProducts++;
            foreach ($rootBoms as $rootBom) {
                $this->addBomMaterials(
                    $rootBom,
                    $targetQuantity,
                    $forecastType !== 'store',
                    $project->boms,
                    $materials,
                    $product->id,
                    $warnings,
                    $product->name,
                    [$rootBom->bom_name],
                );
            }
        }

        $rows = collect($materials)
            ->map(function (array $material): array {
                $material['product_count'] = count($material['product_ids']);
                unset($material['product_ids']);

                return $material;
            })
            ->sortByDesc('quantity')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'projection_details' => $projectionDetails,
            'forecast_percentage' => $forecastPercentage,
            'projected_units' => $projectedUnits,
            'effective_projected_units' => $effectiveProjectedUnits,
            'projection_products' => $projectionProducts,
            'projected_products' => $projectedProducts,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @param Collection<int, mixed> $recipeBoms
     * @param  array<string, array{code: string, name: string, unit: string, quantity: float, product_ids: array<int, bool>}>  $materials
     * @param  list<string>  $warnings
     */
    private function addBomMaterials(object $bom, float $multiplier, bool $expandComponents, Collection $recipeBoms, array &$materials, int $productId, array &$warnings, string $productName, array $bomPath, array $visitedBomIds = []): void
    {
        if (in_array((int) $bom->id, $visitedBomIds, true)) {
            return;
        }

        $visitedBomIds[] = (int) $bom->id;
        $details = data_get($bom->detail_snapshot, 'bomDetails', []);

        foreach ($details as $detail) {
            $quantity = $this->bomCalculation->componentRequirement($detail, $multiplier, true);
            $code = trim((string) ($detail['productCode'] ?? ''));

            if (! $expandComponents) {
                $this->addMaterial(
                    $materials,
                    $code,
                    (string) ($detail['productName'] ?? 'Bahan tanpa nama'),
                    (string) ($detail['uomName'] ?? '-'),
                    $quantity,
                    $productId,
                );

                continue;
            }

            $componentBom = $recipeBoms->first(function ($candidate) use ($code, $visitedBomIds): bool {
                if (in_array((int) $candidate->id, $visitedBomIds, true)) {
                    return false;
                }

                $resultCode = trim((string) data_get($candidate->detail_snapshot, 'productCode', ''));

                return $code !== '' && $resultCode !== '' && $resultCode === $code;
            });

            if ($componentBom) {
                $this->addBomMaterials(
                    $componentBom,
                    $this->childRecipeMultiplier($detail, $componentBom, $quantity, $warnings, $productName, $bomPath),
                    true,
                    $recipeBoms,
                    $materials,
                    $productId,
                    $warnings,
                    $productName,
                    [...$bomPath, $componentBom->bom_name],
                    $visitedBomIds,
                );

                continue;
            }

            $categoryName = mb_strtolower(trim((string) ($detail['categoryName'] ?? $detail['category'] ?? '')));
            $isWorkInProgress = str_contains($categoryName, 'barang wip') || preg_match('/^BW[-_]?\d*/i', $code) === 1;

            if ($isWorkInProgress) {
                $remoteRecipes = $this->remoteWipRecipes($bom, $detail);

                if ($remoteRecipes !== []) {
                    $selectedRecipe = $remoteRecipes[0];
                    $remoteBom = (object) [
                        'id' => -1 * (int) $selectedRecipe['bomID'],
                        'detail_snapshot' => $selectedRecipe,
                        'documentMaterials' => collect(),
                    ];
                    $this->addBomMaterials(
                        $remoteBom,
                        $this->childRecipeMultiplier($detail, $remoteBom, $quantity, $warnings, $productName, $bomPath),
                        true,
                        $recipeBoms,
                        $materials,
                        $productId,
                        $warnings,
                        $productName,
                        [...$bomPath, (string) ($selectedRecipe['bomName'] ?? $selectedRecipe['bomCode'])],
                        $visitedBomIds,
                    );

                    continue;
                }

                $wipName = trim((string) ($detail['productName'] ?? 'WIP tanpa nama'));
                $wipLabel = $code !== '' ? $wipName.' ('.$code.')' : $wipName;
                $warnings[] = 'WIP '.$wipLabel.' pada produk '.$productName.' · '.implode(' → ', $bomPath).' belum memiliki BOM turunan yang cocok di ESB.';

                continue;
            }

            $this->addMaterial(
                $materials,
                $code,
                (string) ($detail['productName'] ?? 'Bahan tanpa nama'),
                (string) ($detail['uomName'] ?? '-'),
                $quantity,
                $productId,
            );
        }

        foreach ($bom->documentMaterials as $material) {
            $this->addMaterial(
                $materials,
                '',
                $material->name,
                $material->unit,
                (float) $material->quantity * $multiplier,
                $productId,
            );
        }
    }

    /** @param list<string> $warnings
     * @param  list<string>  $bomPath
     */
    private function childRecipeMultiplier(array $component, object $childBom, float $requiredQuantity, array &$warnings, string $productName, array $bomPath): float
    {
        $result = $this->bomCalculation->childRecipeMultiplier(
            $component,
            (array) ($childBom->detail_snapshot ?? []),
            $requiredQuantity,
            fn (): ?float => $this->resolveOutputConversionFromMasterProduct($childBom),
        );

        if (! $result['is_proportional']) {
            $code = trim((string) ($component['productCode'] ?? ''));
            $name = trim((string) ($component['productName'] ?? 'WIP tanpa nama'));
            $label = $code !== '' ? $name.' ('.$code.')' : $name;
            $warnings[] = 'Hasil per resep WIP '.$label.' pada produk '.$productName.' · '.implode(' → ', $bomPath).' belum tersedia; perhitungan sementara memakai 1 unit hasil per resep.';

        }

        return $result['multiplier'];
    }

    private function resolveOutputConversionFromMasterProduct(object $bom): ?float
    {
        $snapshot = (array) ($bom->detail_snapshot ?? []);
        $productDetailId = (int) ($snapshot['productDetailID'] ?? 0);
        if ($productDetailId < 1) {
            return null;
        }

        if (array_key_exists($productDetailId, $this->outputConversionFactors)) {
            return $this->outputConversionFactors[$productDetailId] ?: null;
        }

        try {
            $detail = $this->esbService->findActiveProductDetail(
                $productDetailId,
                (string) ($snapshot['productCode'] ?? ''),
                (string) ($snapshot['productName'] ?? ''),
            );
            $factor = is_numeric($detail['conversionFactor'] ?? null) && (float) $detail['conversionFactor'] > 0
                ? (float) $detail['conversionFactor']
                : 0.0;
        } catch (RuntimeException) {
            $factor = 0.0;
        }

        $this->outputConversionFactors[$productDetailId] = $factor;

        return $factor > 0 ? $factor : null;
    }

    /** @return list<array<string, mixed>> */
    private function remoteWipRecipes(object $sourceBom, array $component): array
    {
        $sourceBomId = abs((int) $sourceBom->id);
        $productDetailId = (int) ($component['productDetailID'] ?? 0);
        $productId = (int) ($component['productID'] ?? 0);
        $productCode = strtoupper(trim((string) ($component['productCode'] ?? '')));
        $productName = trim((string) ($component['productName'] ?? ''));
        $cacheKey = 'rnd.material-forecast.wip.v1.'.md5(implode('|', [$sourceBomId, $productDetailId, $productId, $productCode, $productName]));

        try {
            return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($sourceBomId, $productDetailId, $productId, $productCode, $productName): array {
                $core = app(EsbBillOfMaterialService::class);
                $candidates = $core->getBillOfMaterials([
                    'productName' => $productName,
                    'limit' => 100,
                ]);
                $recipes = [];

                foreach ($candidates['data'] ?? [] as $candidate) {
                    $candidateBomId = (int) ($candidate['bomID'] ?? 0);
                    if ($candidateBomId < 1 || $candidateBomId === $sourceBomId) {
                        continue;
                    }

                    $recipe = $core->getBillOfMaterial($candidateBomId);
                    $matches = ($productDetailId > 0 && (int) ($recipe['productDetailID'] ?? 0) === $productDetailId)
                        || ($productId > 0 && (int) ($recipe['productID'] ?? 0) === $productId)
                        || ($productCode !== '' && strtoupper(trim((string) ($recipe['productCode'] ?? ''))) === $productCode);

                    if (! $matches) {
                        continue;
                    }

                    $recipe['bomID'] = $candidateBomId;
                    $recipe['bomCode'] = (string) ($recipe['bomCode'] ?? $candidate['bomCode'] ?? 'BOM-'.$candidateBomId);
                    $recipes[$candidateBomId] = $recipe;
                }

                return array_values($recipes);
            });
        } catch (RuntimeException) {
            return [];
        }
    }

    /** @param array<string, array{code: string, name: string, unit: string, quantity: float, product_ids: array<int, bool>}> $materials */
    private function addMaterial(array &$materials, string $code, string $name, string $unit, float $quantity, int $productId): void
    {
        if ($quantity <= 0) {
            return;
        }

        $key = mb_strtolower(($code !== '' ? $code : $name).'|'.$unit);
        $materials[$key] ??= [
            'code' => $code,
            'name' => $name,
            'unit' => $unit,
            'quantity' => 0.0,
            'product_ids' => [],
        ];
        $materials[$key]['quantity'] += $quantity;
        $materials[$key]['product_ids'][$productId] = true;
    }
}
