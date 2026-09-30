<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Models\RndInternalMemoMaterial;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoBomResolver;
use App\Services\Rnd\InternalMemo\InternalMemoProductEnricher;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Refreshes a single Menu's BOM/Assembly structure from ESB
 * (docs/rnd-internal-memo-simplification-prd.md §7.5, §9.4, §13, Phase 2). Minimum Order values
 * are preserved across the refresh by stable item identity even though
 * InternalMemoBomResolver::resolve() fully replaces the Menu's Material rows on every call —
 * a raw connection failure never wipes the last valid snapshot because the resolver fetches the
 * BOM before touching any existing row and wraps the replace in its own transaction.
 */
class RefreshInternalMemoMenuAction
{
    public function __construct(
        private readonly InternalMemoBomResolver $resolver,
        private readonly InternalMemoProductEnricher $productEnricher,
    ) {}

    public function execute(RndInternalMemoMenu $menu): RndInternalMemoMenu
    {
        $lock = Cache::lock("rnd-internal-memo.menu-refresh.{$menu->id}", 30);

        if (! $lock->get()) {
            throw new RuntimeException('Menu ini sedang disegarkan pada proses lain. Coba lagi sebentar.');
        }

        try {
            $preservedMinimumOrders = $this->captureMinimumOrders($menu);

            try {
                $result = $this->resolver->resolve($menu);
            } catch (Throwable $exception) {
                $menu->update([
                    'sync_status' => RndInternalMemoMenuSyncStatus::Failed,
                    'sync_error' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            $this->restoreMinimumOrders($menu, $preservedMinimumOrders);
            $this->productEnricher->enrichMenu($menu);

            $menu->update([
                'sync_status' => RndInternalMemoMenuSyncStatus::Synced,
                'synced_at' => now(),
                'sync_error' => $result['blockers'] === [] ? null : implode(' | ', $result['blockers']),
                'sync_warnings' => $result['warnings'] === [] ? null : $result['warnings'],
                'bom_snapshot' => $result['bom_snapshot'],
            ]);

            return $menu->fresh();
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, float> */
    private function captureMinimumOrders(RndInternalMemoMenu $menu): array
    {
        return $menu->materials()
            ->whereNotNull('minimum_order')
            ->get()
            ->mapWithKeys(fn (RndInternalMemoMaterial $material): array => [
                self::identityKey($material->esb_product_detail_id, $material->esb_product_id, $material->product_code, $material->product_name, $material->uom_name) => (float) $material->minimum_order,
            ])
            ->all();
    }

    /** @param array<string, float> $preserved */
    private function restoreMinimumOrders(RndInternalMemoMenu $menu, array $preserved): void
    {
        if ($preserved === []) {
            return;
        }

        foreach ($menu->materials()->get() as $material) {
            $key = self::identityKey($material->esb_product_detail_id, $material->esb_product_id, $material->product_code, $material->product_name, $material->uom_name);

            if (array_key_exists($key, $preserved)) {
                $material->update(['minimum_order' => $preserved[$key]]);
            }
        }
    }

    /**
     * Stable identity for reconciling an item across a refresh
     * (docs/rnd-internal-memo-simplification-prd.md §9.4 priority order): productDetailID, then
     * productID, then productCode, then productName — each of the last three paired with UOM
     * BOM since a proven Purchase UOM is not always available (Phase 0 audit finding).
     */
    public static function identityKey(?int $productDetailId, ?int $productId, ?string $productCode, string $productName, string $uomName): string
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
