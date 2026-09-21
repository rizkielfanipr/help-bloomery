<?php

namespace App\Services;

use App\Models\RndProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RndProjectMaterialForecastService
{
    /**
     * @return array{rows: list<array{code: string, name: string, unit: string, quantity: float, product_count: int}>, projected_units: float, projected_products: int, warnings: list<string>}
     */
    public function calculate(RndProject $project, string $forecastType = 'kitchen'): array
    {
        $usageType = $forecastType === 'store' ? 'menu' : 'main';
        $materials = [];
        $projectedUnits = 0.0;
        $projectedProducts = 0;
        $warnings = [];

        foreach ($project->products as $product) {
            $targetQuantity = (float) $product->salesProjections->sum('target_quantity');

            if ($targetQuantity <= 0) {
                continue;
            }

            $rootBoms = $product->boms->filter(fn ($bom): bool => $bom->pivot->usage_type === $usageType);

            if ($rootBoms->isEmpty()) {
                continue;
            }

            $projectedUnits += $targetQuantity;
            $projectedProducts++;
            foreach ($rootBoms as $rootBom) {
                $this->addBomMaterials(
                    $rootBom,
                    $targetQuantity,
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
            'projected_units' => $projectedUnits,
            'projected_products' => $projectedProducts,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @param Collection<int, mixed> $recipeBoms
     * @param  array<string, array{code: string, name: string, unit: string, quantity: float, product_ids: array<int, bool>}>  $materials
     * @param  list<string>  $warnings
     */
    private function addBomMaterials(object $bom, float $multiplier, Collection $recipeBoms, array &$materials, int $productId, array &$warnings, string $productName, array $bomPath, array $visitedBomIds = []): void
    {
        if (in_array((int) $bom->id, $visitedBomIds, true)) {
            return;
        }

        $visitedBomIds[] = (int) $bom->id;
        $details = data_get($bom->detail_snapshot, 'bomDetails', []);

        foreach ($details as $detail) {
            $quantity = (float) ($detail['qty'] ?? 0) * $multiplier;
            $tolerance = max(0, (float) ($detail['tolerancePercent'] ?? 0));
            $quantity *= 1 + ($tolerance / 100);
            $code = trim((string) ($detail['productCode'] ?? ''));
            $componentBom = $recipeBoms->first(function ($candidate) use ($code, $visitedBomIds): bool {
                if (in_array((int) $candidate->id, $visitedBomIds, true)) {
                    return false;
                }

                $resultCode = trim((string) data_get($candidate->detail_snapshot, 'productCode', ''));

                return $code !== '' && $resultCode !== '' && $resultCode === $code;
            });

            if ($componentBom) {
                $this->addBomMaterials($componentBom, $quantity, $recipeBoms, $materials, $productId, $warnings, $productName, [...$bomPath, $componentBom->bom_name], $visitedBomIds);

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
                    $this->addBomMaterials($remoteBom, $quantity, $recipeBoms, $materials, $productId, $warnings, $productName, [...$bomPath, (string) ($selectedRecipe['bomName'] ?? $selectedRecipe['bomCode'])], $visitedBomIds);

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
                $core = app(EsbCoreService::class);
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
