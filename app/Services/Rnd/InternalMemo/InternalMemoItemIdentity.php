<?php

namespace App\Services\Rnd\InternalMemo;

/**
 * Stable identity for reconciling the same item across a Menu refresh and for grouping it in the
 * Ringkasan Item Akhir (docs/rnd-internal-memo-simplification-prd.md §9.4 priority order):
 * productDetailID, then productID, then productCode, then productName — each of the last three
 * paired with UOM BOM since a proven Purchase UOM is not always available (Phase 0 audit finding).
 *
 * Shared by RefreshInternalMemoMenuAction (Minimum Order preservation across a refresh) and the
 * Ringkasan Item Akhir summary/Minimum Order update (Phase 4) so both agree on what "the same
 * item" means.
 */
class InternalMemoItemIdentity
{
    public static function key(?int $productDetailId, ?int $productId, ?string $productCode, string $productName, string $uomName): string
    {
        $uom = mb_strtoupper(trim($uomName));

        if ($productDetailId !== null) {
            return "pd:{$productDetailId}";
        }

        if ($productId !== null) {
            return "p:{$productId}:{$uom}";
        }

        if (filled($productCode)) {
            return 'code:'.mb_strtoupper(trim($productCode)).":{$uom}";
        }

        return 'name:'.mb_strtolower(trim($productName)).":{$uom}";
    }
}
