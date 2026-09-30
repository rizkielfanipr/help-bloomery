<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
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
     * Ringkasan Item Akhir (docs/rnd-internal-memo-simplification-prd.md §9.4, §12.2): unlike
     * `consolidate()` above (which only ever served the old Forecast-based workflow and excludes
     * WIP), the simplified summary shows Bahan and WIP as their own groups, each consolidated by
     * InternalMemoItemIdentity so the same item reached through more than one Menu or BOM path
     * appears once with every contributing source listed.
     *
     * @return array{bahan: list<array<string, mixed>>, wip: list<array<string, mixed>>, warnings: list<string>}
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

            return InternalMemoItemIdentity::key($material->esb_product_detail_id, $material->esb_product_id, $material->product_code, $material->product_name, $material->uom_name);
        });

        $rows = $groups->map(function (Collection $rows, string $key): array {
            $first = $rows->first();
            $distinctMinimumOrders = $rows->pluck('minimum_order')->filter(fn ($value) => $value !== null)->unique();
            $syncedAt = $rows->pluck('product_synced_at')->filter()->sort()->last();

            return [
                'key' => $key,
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
        })->sortBy('product_name')->values();

        return [
            'bahan' => $rows->where('is_wip', false)->values()->all(),
            'wip' => $rows->where('is_wip', true)->values()->all(),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
