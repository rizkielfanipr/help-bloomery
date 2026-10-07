<?php

namespace App\Services\Rnd\ShelfLife;

use App\Models\RndProjectBom;
use App\Models\RndProjectProduct;
use App\Services\Rnd\Bom\ProjectWipRecipeDiscovery;
use Throwable;

/**
 * Lists the WIP items of a Project Product from the existing WIP mapping, without a second BOM
 * traversal (docs/rnd-wip-shelf-life-prd.md §9.7, §13): WIP components of each Main BOM (direct)
 * plus WIP components inside the recipes that mapping found (nested). Items are de-duplicated by
 * Product Detail ID; items without one are kept separately as "identity incomplete".
 *
 * A direct WIP whose recipe the mapping could not find is flagged `recipe_resolved = false`
 * (an "Unresolved WIP", §8): its own nested WIPs cannot be known.
 *
 * @phpstan-type WipItem array{key: string, product_detail_id: ?int, product_code: string, product_name: string, uom_name: string, paths: list<string>, recipe_resolved: bool}
 */
class ProjectProductWipCollector
{
    public function __construct(private readonly ProjectWipRecipeDiscovery $discovery) {}

    /**
     * Builds the list from mapping data already loaded on the page (no query, no ESB call).
     *
     * @param  iterable<RndProjectBom>  $mainBoms
     * @param  array<int, array<string, mixed>>  $mainDetailsByBomId  keyed by `rnd_project_boms.id`
     * @param  array<int, list<array<string, mixed>>>  $recipesByBomId  keyed by `rnd_project_boms.id`
     * @return list<WipItem>
     */
    public function fromMapping(iterable $mainBoms, array $mainDetailsByBomId, array $recipesByBomId): array
    {
        $items = [];

        foreach ($mainBoms as $mainBom) {
            $mainDetail = $mainDetailsByBomId[$mainBom->id] ?? null;
            if (! is_array($mainDetail)) {
                continue;
            }

            $mainLabel = $mainBom->bom_name ?: ($mainBom->bom_code ?: 'Main BOM');
            $recipes = collect($recipesByBomId[$mainBom->id] ?? []);

            foreach ($mainDetail['bomDetails'] ?? [] as $component) {
                if (! $this->discovery->isWipComponent($component)) {
                    continue;
                }

                $productDetailId = (int) ($component['productDetailID'] ?? 0);
                $recipe = $recipes->first(fn (array $recipe): bool => $productDetailId > 0 && (int) ($recipe['productDetailID'] ?? 0) === $productDetailId);
                $this->addItem($items, $component, $mainLabel, recipeResolved: $recipe !== null);
            }

            foreach ($recipes as $recipe) {
                $recipeLabel = $mainLabel.' → '.($recipe['bomName'] ?: ($recipe['productName'] ?? 'WIP'));

                foreach ($recipe['bomDetails'] ?? [] as $component) {
                    if ($this->discovery->isWipComponent($component)) {
                        $this->addItem($items, $component, $recipeLabel, recipeResolved: true);
                    }
                }
            }
        }

        return array_values($items);
    }

    /**
     * Server-side list for a stored Product: Main BOM snapshots from the database plus the
     * (cache-first) WIP mapping. Used to verify mutations and the Ready/Released gate, so nothing
     * comes from browser state.
     *
     * @return array{items: list<WipItem>, has_main_bom: bool, error: ?string}
     */
    public function forProduct(RndProjectProduct $product): array
    {
        $mainBoms = $product->boms()->wherePivot('usage_type', 'main')->get();

        if ($mainBoms->isEmpty()) {
            return ['items' => [], 'has_main_bom' => false, 'error' => null];
        }

        $mainDetails = [];
        $recipes = [];

        try {
            foreach ($mainBoms as $mainBom) {
                if (! is_array($mainBom->detail_snapshot)) {
                    return ['items' => [], 'has_main_bom' => true, 'error' => "Komponen BOM \"{$mainBom->bom_name}\" belum dimuat."];
                }

                $mainDetails[$mainBom->id] = $mainBom->detail_snapshot;
                $recipes[$mainBom->id] = $this->discovery->recipesFor($mainBom, $mainBom->detail_snapshot);
            }
        } catch (Throwable) {
            return ['items' => [], 'has_main_bom' => true, 'error' => 'Mapping WIP belum dapat dimuat dari ESB.'];
        }

        return ['items' => $this->fromMapping($mainBoms, $mainDetails, $recipes), 'has_main_bom' => true, 'error' => null];
    }

    /**
     * @param  array<string, WipItem>  $items
     * @param  array<string, mixed>  $component
     */
    private function addItem(array &$items, array $component, string $path, bool $recipeResolved): void
    {
        $productDetailId = (int) ($component['productDetailID'] ?? 0);
        $productCode = trim((string) ($component['productCode'] ?? ''));
        $productName = trim((string) ($component['productName'] ?? ''));
        $key = $productDetailId > 0 ? "pd:{$productDetailId}" : 'unidentified:'.mb_strtolower($productCode.'|'.$productName);

        if (isset($items[$key])) {
            $items[$key]['paths'] = array_values(array_unique([...$items[$key]['paths'], $path]));
            $items[$key]['recipe_resolved'] = $items[$key]['recipe_resolved'] || $recipeResolved;

            return;
        }

        $items[$key] = [
            'key' => $key,
            'product_detail_id' => $productDetailId > 0 ? $productDetailId : null,
            'product_code' => $productCode,
            'product_name' => $productName !== '' ? $productName : ($productCode !== '' ? $productCode : 'WIP tanpa nama'),
            'uom_name' => trim((string) ($component['uomName'] ?? '')),
            'paths' => [$path],
            'recipe_resolved' => $recipeResolved,
        ];
    }
}
