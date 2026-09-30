<?php

namespace App\Services;

use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Services\Rnd\BomCalculationService;
use Illuminate\Http\Client\ConnectionException;
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
     * @return array{rows: list<array{code: string, name: string, unit: string, quantity: float, effective_quantity: float, is_calculated: bool, calculation_notes: list<string>}>, projection_details: list<array{name: string, quantity: float, effective_quantity: float, is_calculated: bool}>, forecast_percentage: float, projected_units: float, effective_projected_units: float, projection_products: int, projected_products: int, warnings: list<string>, recipe_notes: list<string>}
     */
    public function calculate(RndProject $project, string $forecastType = 'kitchen'): array
    {
        $this->outputConversionFactors = [];
        $materials = [];
        $projectionDetails = [];
        $projectedUnits = 0.0;
        $effectiveProjectedUnits = 0.0;
        $projectionProducts = 0;
        $projectedProducts = 0;
        $warnings = [];
        $recipeNotes = [];
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
            $menuBoms = $product->boms->filter(fn ($bom): bool => $bom->pivot->usage_type === 'menu');
            $rootBoms = $forecastType === 'store'
                ? $menuBoms
                : $product->boms->filter(fn ($bom): bool => $bom->pivot->usage_type === 'main');
            $isCalculated = false;

            if ($forecastType === 'store') {
                foreach ($rootBoms as $rootBom) {
                    $this->addBomMaterials(
                        $rootBom,
                        $targetQuantity,
                        false,
                        $project->boms,
                        $materials,
                        $product->id,
                        $warnings,
                        $product->name,
                        [$rootBom->bom_name],
                    );
                }

                $isCalculated = $rootBoms->isNotEmpty();
            } else {
                $processedResultCodes = [];

                foreach ($rootBoms as $rootBom) {
                    $resultCode = strtoupper(trim((string) data_get($rootBom->detail_snapshot, 'productCode', '')));
                    if ($resultCode !== '') {
                        $processedResultCodes[] = $resultCode;
                    }

                    $this->addBomMaterials(
                        $rootBom,
                        $this->rootRecipeMultiplier($rootBom, $product, $targetQuantity, $warnings, $recipeNotes, $product->name),
                        true,
                        $project->boms,
                        $materials,
                        $product->id,
                        $warnings,
                        $product->name,
                        [$rootBom->bom_name],
                    );
                }

                $isCalculated = $rootBoms->isNotEmpty();
                $isCalculated = $this->addUnmappedMenuWipMaterials(
                    $product,
                    $menuBoms,
                    $targetQuantity,
                    $project->boms,
                    $materials,
                    $warnings,
                    $recipeNotes,
                    $processedResultCodes,
                ) || $isCalculated;
            }

            $projectionDetails[] = [
                'name' => $product->name,
                'quantity' => $projectedQuantity,
                'effective_quantity' => $targetQuantity,
                'is_calculated' => $isCalculated,
            ];

            if ($isCalculated) {
                $projectedProducts++;
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
            'recipe_notes' => array_values(array_unique($recipeNotes)),
        ];
    }

    /**
     * Expand WIP components that appear directly in a Menu BOM but are not the result of one
     * of the product's attached Main Recipes. A Menu can consume several independently produced
     * WIPs, so using only its selected Main Recipe would silently omit the others.
     *
     * @param  Collection<int, mixed>  $menuBoms
     * @param  Collection<int, mixed>  $recipeBoms
     * @param  array<string, array{code: string, name: string, unit: string, quantity: float, product_ids: array<int, bool>, calculation_notes: list<string>}>  $materials
     * @param  list<string>  $warnings
     * @param  list<string>  $recipeNotes
     * @param  list<string>  $processedResultCodes
     */
    private function addUnmappedMenuWipMaterials(RndProjectProduct $product, Collection $menuBoms, float $targetQuantity, Collection $recipeBoms, array &$materials, array &$warnings, array &$recipeNotes, array $processedResultCodes): bool
    {
        $requirements = [];

        foreach ($menuBoms as $menuBom) {
            foreach (data_get($menuBom->detail_snapshot, 'bomDetails', []) as $detail) {
                $code = strtoupper(trim((string) ($detail['productCode'] ?? '')));

                if ($code === '' || in_array($code, $processedResultCodes, true) || ! $this->isWorkInProgress($detail)) {
                    continue;
                }

                $requirements[$code] ??= [
                    'detail' => $detail,
                    'quantity' => 0.0,
                    'menu_names' => [],
                    'source_bom' => $menuBom,
                ];
                $requirements[$code]['quantity'] += $this->bomCalculation->componentRequirement($detail, $targetQuantity, true);
                $requirements[$code]['menu_names'][] = $menuBom->bom_name;
            }
        }

        $calculated = false;

        foreach ($requirements as $code => $requirement) {
            $detail = $requirement['detail'];
            $componentBom = $product->boms
                ->filter(fn ($candidate): bool => $candidate->pivot->usage_type !== 'menu')
                ->first(fn ($candidate): bool => $this->recipeMatchesComponent($candidate, $detail))
                ?? $recipeBoms
                    ->reject(fn ($candidate): bool => data_get($candidate, 'pivot.usage_type') === 'menu')
                    ->first(fn ($candidate): bool => $this->recipeMatchesComponent($candidate, $detail));

            if (! $componentBom) {
                $remoteRecipes = $this->remoteWipRecipes($requirement['source_bom'], $detail);

                if ($remoteRecipes !== []) {
                    $selectedRecipe = $remoteRecipes[0];
                    $componentBom = (object) [
                        'id' => -1 * (int) $selectedRecipe['bomID'],
                        'bom_name' => (string) ($selectedRecipe['bomName'] ?? $selectedRecipe['bomCode']),
                        'detail_snapshot' => $selectedRecipe,
                        'documentMaterials' => collect(),
                    ];
                }
            }

            if (! $componentBom) {
                $name = trim((string) ($detail['productName'] ?? 'WIP tanpa nama'));
                $warnings[] = 'WIP '.$name.' ('.$code.') pada produk '.$product->name.' · '.implode(', ', array_unique($requirement['menu_names'])).' belum memiliki BOM turunan yang cocok di ESB.';

                continue;
            }

            $bomName = (string) ($componentBom->bom_name ?? data_get($componentBom->detail_snapshot, 'bomName', $code));
            $multiplier = $this->childRecipeMultiplier(
                $detail,
                $componentBom,
                $requirement['quantity'],
                $warnings,
                $product->name,
                array_values(array_unique($requirement['menu_names'])),
            );
            $yield = $this->bomCalculation->outputConversionFactor((array) ($componentBom->detail_snapshot ?? []))
                ?? $this->resolveOutputConversionFromMasterProduct($componentBom);

            if ($yield !== null) {
                $unit = (string) ($detail['uomName'] ?? '');
                $recipeNotes[] = sprintf(
                    '%s · %s: %s unit terjual × %s %s/unit (dari BOM Menu) = %s %s dibutuhkan ÷ %s %s hasil per resep = %s kali resep.',
                    $product->name,
                    $bomName,
                    $this->formatNumber($targetQuantity),
                    $this->formatNumber((float) ($detail['qty'] ?? 0)),
                    $unit,
                    $this->formatNumber($requirement['quantity']),
                    $unit,
                    $this->formatNumber($yield),
                    $unit,
                    $this->formatNumber($multiplier),
                );
            }

            $this->addBomMaterials(
                $componentBom,
                $multiplier,
                true,
                $recipeBoms,
                $materials,
                $product->id,
                $warnings,
                $product->name,
                [...array_values(array_unique($requirement['menu_names'])), $bomName],
            );
            $calculated = true;
        }

        return $calculated;
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
                    $this->calculationNote($productName, $bomPath, $detail, $multiplier, $quantity, (string) ($detail['uomName'] ?? '-')),
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

            if ($this->isWorkInProgress($detail)) {
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
                $this->calculationNote($productName, $bomPath, $detail, $multiplier, $quantity, (string) ($detail['uomName'] ?? '-')),
            );
        }

        foreach ($bom->documentMaterials as $material) {
            $sopQuantity = (float) $material->quantity * $multiplier;
            $this->addMaterial(
                $materials,
                '',
                $material->name,
                $material->unit,
                $sopQuantity,
                $productId,
                sprintf(
                    '%s · %s (Bahan Khusus SOP): %s %s/resep × %s = %s %s',
                    $productName,
                    implode(' → ', $bomPath),
                    $this->formatNumber((float) $material->quantity),
                    $material->unit,
                    $this->formatNumber($multiplier),
                    $this->formatNumber($sopQuantity),
                    $material->unit,
                ),
            );
        }
    }

    /** @param array<string, mixed> $component */
    private function isWorkInProgress(array $component): bool
    {
        $categoryName = mb_strtolower(trim((string) ($component['categoryName'] ?? $component['category'] ?? '')));
        $code = trim((string) ($component['productCode'] ?? ''));

        return str_contains($categoryName, 'barang wip') || preg_match('/^BW[-_]?\d*/i', $code) === 1;
    }

    /** @param array<string, mixed> $component */
    private function recipeMatchesComponent(object $recipe, array $component): bool
    {
        $componentProductDetailId = (int) ($component['productDetailID'] ?? 0);
        $recipeProductDetailId = (int) data_get($recipe->detail_snapshot, 'productDetailID', 0);

        if ($componentProductDetailId > 0 && $recipeProductDetailId > 0) {
            return $componentProductDetailId === $recipeProductDetailId;
        }

        $componentCode = strtoupper(trim((string) ($component['productCode'] ?? '')));
        $recipeCode = strtoupper(trim((string) data_get($recipe->detail_snapshot, 'productCode', '')));

        return $componentCode !== '' && $componentCode === $recipeCode;
    }

    /** @param list<string> $bomPath
     * @param  array<string, mixed>  $detail
     */
    private function calculationNote(string $productName, array $bomPath, array $detail, float $multiplier, float $quantity, string $unit): string
    {
        $qtyPerLine = (float) ($detail['qty'] ?? 0);
        $tolerance = (float) ($detail['tolerancePercent'] ?? 0);
        $toleranceText = $tolerance > 0 ? ' × (1 + '.$this->formatNumber($tolerance).'% toleransi)' : '';

        return sprintf(
            '%s · %s: %s %s/resep × %s%s = %s %s',
            $productName,
            implode(' → ', $bomPath),
            $this->formatNumber($qtyPerLine),
            $unit,
            $this->formatNumber($multiplier),
            $toleranceText,
            $this->formatNumber($quantity),
            $unit,
        );
    }

    private function formatNumber(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * A Main Recipe attached directly to a product may itself be a shared batch WIP (e.g. a
     * 6000 GR "Froyo Mix" recipe of which a single sold unit only consumes 130 GR via the
     * product's own BOM Menu) rather than a "1 recipe run per sold unit" preparation. When the
     * root recipe's own result product code is also consumed as an ingredient in the product's
     * Menu BOM(s), scale it the same proportional way nested WIPs already are — total quantity
     * needed across all sold units ÷ the recipe's own yield — instead of treating every unit
     * sold as requiring one full recipe run.
     *
     * @param  list<string>  $warnings
     * @param  list<string>  $recipeNotes
     */
    private function rootRecipeMultiplier(object $rootBom, RndProjectProduct $product, float $targetQuantity, array &$warnings, array &$recipeNotes, string $productName): float
    {
        $resultCode = trim((string) data_get($rootBom->detail_snapshot, 'productCode', ''));

        if ($resultCode === '') {
            return $targetQuantity;
        }

        $menuBoms = $product->boms->filter(fn ($bom): bool => $bom->pivot->usage_type === 'menu');
        $requiredQuantity = 0.0;
        $matchedUsage = null;

        foreach ($menuBoms as $menuBom) {
            foreach (data_get($menuBom->detail_snapshot, 'bomDetails', []) as $detail) {
                if (trim((string) ($detail['productCode'] ?? '')) !== $resultCode) {
                    continue;
                }

                $requiredQuantity += $this->bomCalculation->componentRequirement($detail, $targetQuantity, true);
                $matchedUsage ??= $detail;
            }
        }

        if ($matchedUsage === null) {
            return $targetQuantity;
        }

        $yield = $this->bomCalculation->outputConversionFactor((array) ($rootBom->detail_snapshot ?? []))
            ?? $this->resolveOutputConversionFromMasterProduct($rootBom);
        $multiplier = $this->childRecipeMultiplier($matchedUsage, $rootBom, $requiredQuantity, $warnings, $productName, [$rootBom->bom_name]);

        if ($yield !== null) {
            $unit = (string) ($matchedUsage['uomName'] ?? '');
            $recipeNotes[] = sprintf(
                '%s · %s: %s unit terjual × %s %s/unit (dari BOM Menu) = %s %s dibutuhkan ÷ %s %s hasil per resep = %s kali resep.',
                $productName,
                $rootBom->bom_name,
                $this->formatNumber($targetQuantity),
                $this->formatNumber((float) ($matchedUsage['qty'] ?? 0)),
                $unit,
                $this->formatNumber($requiredQuantity),
                $unit,
                $this->formatNumber($yield),
                $unit,
                $this->formatNumber($multiplier),
            );
        }

        return $multiplier;
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
        } catch (ConnectionException|RuntimeException) {
            return [];
        }
    }

    /** @param array<string, array{code: string, name: string, unit: string, quantity: float, product_ids: array<int, bool>, calculation_notes: list<string>}> $materials */
    private function addMaterial(array &$materials, string $code, string $name, string $unit, float $quantity, int $productId, string $calculationNote): void
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
            'calculation_notes' => [],
        ];
        $materials[$key]['quantity'] += $quantity;
        $materials[$key]['product_ids'][$productId] = true;
        $materials[$key]['calculation_notes'][] = $calculationNote;
    }
}
