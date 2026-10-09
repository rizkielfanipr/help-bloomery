<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoExtraProduct;
use App\Models\RndInternalMemoMaterial;
use Illuminate\Support\Collection;

/**
 * docs/rnd-internal-memo-prd.md §10.5. Consolidates every consolidation-candidate material
 * (base materials and packaging; WIP/Assembly rows are excluded, per
 * RndInternalMemoMaterial::isConsolidationCandidate()) across all of a Memo's Menus, grouped by
 * `productDetailID + normalized UOM`. A row without a Product Detail ID still consolidates, keyed
 * by its Product Code (or name as a last resort), but is flagged with a warning since two
 * differently-coded components could collide under that fallback key.
 */
class InternalMemoConsolidationService
{
    /**
     * @return array{
     *     rows: list<array{key: string, product_name: string, product_code: ?string, uom_name: string, net_quantity: float, menu_count: int, has_fallback_identity: bool}>,
     *     warnings: list<string>,
     * }
     */
    public function consolidate(RndInternalMemo $memo): array
    {
        $materials = RndInternalMemoMaterial::query()
            ->whereIn('rnd_internal_memo_menu_id', $memo->menus()->pluck('id'))
            ->where('is_wip', false)
            ->get();

        $warnings = [];

        /** @var Collection<string, Collection<int, RndInternalMemoMaterial>> $groups */
        $groups = $materials->groupBy(function (RndInternalMemoMaterial $material) use (&$warnings): string {
            $uom = mb_strtoupper(trim($material->uom_name));
            $hasFallback = $material->esb_product_detail_id === null;

            if ($hasFallback) {
                $fallback = $material->product_code ?: $material->product_name;
                $warnings[] = "Bahan \"{$material->product_name}\" tidak mempunyai Product Detail ID; dikonsolidasikan lewat kode/nama (\"{$fallback}\").";

                return "fallback:{$fallback}:{$uom}";
            }

            return "pd:{$material->esb_product_detail_id}:{$uom}";
        });

        $rows = $groups->map(function (Collection $rows, string $key): array {
            $first = $rows->first();

            return [
                'key' => $key,
                'product_name' => $first->product_name,
                'product_code' => $first->product_code,
                'uom_name' => $first->uom_name,
                'net_quantity' => (float) $rows->sum('net_quantity'),
                'menu_count' => $rows->pluck('rnd_internal_memo_menu_id')->unique()->count(),
                'has_fallback_identity' => $first->esb_product_detail_id === null,
            ];
        })->sortBy('product_name')->values()->all();

        return ['rows' => $rows, 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * Ringkasan produk (docs/rnd-internal-memo-simplification-prd.md §9.4, §12.2), split by who uses
     * the item: `store` holds only rows of the Menu BOM itself (depth 0), `kitchen` holds the rows
     * reached by tracing WIP/Assembly BOMs (depth > 0). Each scope is split into WIP and Bahan and
     * consolidated by InternalMemoItemIdentity, so the same item reached through more than one Menu
     * or BOM path within a scope appears once with every contributing source listed. Products added
     * by hand (RndInternalMemoExtraProduct) join their WIP/RAW group with `source` = manual.
     *
     * @return array{store: array{wip: list<array<string, mixed>>, bahan: list<array<string, mixed>>}, kitchen: array{wip: list<array<string, mixed>>, bahan: list<array<string, mixed>>}, warnings: list<string>}
     */
    public function consolidateForSummary(RndInternalMemo $memo): array
    {
        $materials = RndInternalMemoMaterial::query()
            ->with('menu')
            ->whereIn('rnd_internal_memo_menu_id', $memo->menus()->pluck('id'))
            ->get();

        $warnings = [];

        /** @var Collection<string, Collection<int, RndInternalMemoMaterial>> $groups */
        $groups = $materials->groupBy(function (RndInternalMemoMaterial $material) use (&$warnings): string {
            if ($material->esb_product_detail_id === null) {
                $warnings[] = "\"{$material->product_name}\" tidak mempunyai Product Detail ID; dikonsolidasikan lewat identitas cadangan.";
            }

            return InternalMemoItemIdentity::scopedKey(
                InternalMemoItemIdentity::scopeForDepth((int) $material->depth),
                InternalMemoItemIdentity::key($material->esb_product_detail_id, $material->esb_product_id, $material->product_code, $material->product_name, $material->uom_name),
            );
        });

        $rows = $groups->map(function (Collection $rows, string $key): array {
            $first = $rows->first();
            $distinctMinimumOrders = $rows->pluck('minimum_order')->filter(fn ($value) => $value !== null)->unique();
            $syncedAt = $rows->pluck('product_synced_at')->filter()->sort()->last();

            return [
                'key' => $key,
                'source' => 'bom',
                'extra_product_id' => null,
                'scope' => InternalMemoItemIdentity::parseScopedKey($key)[0],
                'product_detail_id' => $first->esb_product_detail_id,
                'product_code' => $first->product_code,
                'product_name' => $first->product_name,
                'uom_name' => $first->uom_name,
                'has_purchase_uom' => $first->hasPurchaseUom(),
                'purchase_uom_name' => $first->purchase_uom_name,
                'minimum_order' => $distinctMinimumOrders->count() === 1 ? (float) $distinctMinimumOrders->first() : null,
                'product_synced_at' => $syncedAt,
                'is_wip' => (bool) $first->is_wip,
                'is_packaging' => (bool) $first->is_packaging,
                'sources' => $rows
                    ->map(fn (RndInternalMemoMaterial $material): array => [
                        'menu_name' => $material->menu->menu_name,
                        'path' => implode(' → ', $material->source_path ?? []),
                    ])
                    ->unique(fn (array $source): string => $source['menu_name'].'|'.$source['path'])
                    ->values()
                    ->all(),
            ];
        })->values()
            ->concat($memo->extraProducts()->get()->map(fn (RndInternalMemoExtraProduct $extra): array => [
                'key' => InternalMemoItemIdentity::scopedKey($extra->scope, InternalMemoItemIdentity::extraKey($extra->id)),
                'source' => 'manual',
                'extra_product_id' => $extra->id,
                'scope' => $extra->scope,
                'product_detail_id' => $extra->esb_product_detail_id,
                'product_code' => $extra->product_code,
                'product_name' => $extra->product_name,
                'uom_name' => $extra->uom_name,
                'has_purchase_uom' => $extra->hasPurchaseUom(),
                'purchase_uom_name' => $extra->purchase_uom_name,
                'minimum_order' => $extra->minimum_order !== null ? (float) $extra->minimum_order : null,
                'product_synced_at' => $extra->product_synced_at,
                'is_wip' => $extra->isWip(),
                'is_packaging' => false,
                'sources' => [],
            ]))
            ->sortBy('product_name')
            ->values();

        $split = fn (string $scope): array => [
            'wip' => $rows->where('scope', $scope)->where('is_wip', true)->values()->all(),
            'bahan' => $rows->where('scope', $scope)->where('is_wip', false)->values()->all(),
        ];

        return [
            'store' => $split(InternalMemoItemIdentity::SCOPE_STORE),
            'kitchen' => $split(InternalMemoItemIdentity::SCOPE_KITCHEN),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
