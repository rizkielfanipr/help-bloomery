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
}
