<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMaterial;
use App\Services\Rnd\InternalMemo\InternalMemoItemIdentity;

/**
 * docs/rnd-internal-memo-simplification-prd.md §7.4, §10.4: Minimum Order is "per item dalam
 * Memo" — when the same item is reached through more than one Menu or BOM path within one Memo,
 * every Material row sharing that stable identity is updated together so the consolidated
 * Ringkasan Item Akhir row always reflects one value, never a per-path split. It never touches
 * another Memo's copy of the same product.
 *
 * A scoped key (`store|…` / `kitchen|…`, see InternalMemoItemIdentity::scopedKey) limits the update
 * to the Store (Menu BOM) or Kitchen (traced WIP BOM) rows of that item; an unscoped key updates all.
 */
class UpdateInternalMemoMinimumOrdersAction
{
    public function execute(RndInternalMemo $memo, string $identityKey, ?float $minimumOrder): int
    {
        $materials = RndInternalMemoMaterial::query()
            ->whereIn('rnd_internal_memo_menu_id', $memo->menus()->pluck('id'))
            ->get();

        [$scope, $identityKey] = InternalMemoItemIdentity::parseScopedKey($identityKey);
        $updated = 0;

        foreach ($materials as $material) {
            $key = InternalMemoItemIdentity::key($material->esb_product_detail_id, $material->esb_product_id, $material->product_code, $material->product_name, $material->uom_name);

            if ($key === $identityKey && ($scope === null || InternalMemoItemIdentity::scopeForDepth((int) $material->depth) === $scope)) {
                $material->update(['minimum_order' => $minimumOrder]);
                $updated++;
            }
        }

        return $updated;
    }
}
