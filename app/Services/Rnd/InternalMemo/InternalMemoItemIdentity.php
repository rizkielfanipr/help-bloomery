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
    /** Items of the Menu BOM itself (depth 0): what the Store uses. */
    public const SCOPE_STORE = 'store';

    /** Items reached by tracing a WIP/Assembly BOM (depth > 0): what the Kitchen uses. */
    public const SCOPE_KITCHEN = 'kitchen';

    public static function scopeForDepth(int $depth): string
    {
        return $depth === 0 ? self::SCOPE_STORE : self::SCOPE_KITCHEN;
    }

    /**
     * Identity within one usage scope, so the same product used by both Store and Kitchen keeps a
     * separate summary row and Minimum Order per scope.
     */
    public static function scopedKey(string $scope, string $identityKey): string
    {
        return $scope.'|'.$identityKey;
    }

    /** @return array{0: ?string, 1: string} [scope or null for an unscoped key, identity key] */
    public static function parseScopedKey(string $key): array
    {
        [$scope, $identity] = array_pad(explode('|', $key, 2), 2, null);

        return in_array($scope, [self::SCOPE_STORE, self::SCOPE_KITCHEN], true) && $identity !== null
            ? [$scope, $identity]
            : [null, $key];
    }

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
