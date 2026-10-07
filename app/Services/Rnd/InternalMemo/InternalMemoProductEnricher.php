<?php

namespace App\Services\Rnd\InternalMemo;

use App\Models\RndInternalMemoMaterial;
use App\Models\RndInternalMemoMenu;
use App\Services\EsbCoreClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Enriches a Menu's Material rows with Product data from ESB Core (docs/rnd-internal-memo-
 * simplification-prd.md §7.2 step 6). Each ESB Product is read once per Menu through
 * `GET /product/{productID}`, in concurrent batches, using the Menu's own Company Code.
 *
 * Purchase UOM comes from the Product's units: `/product/{id}` lists every Product Detail with an
 * `isPurchase` flag, and a Product has exactly one active purchasing unit (verified on live BLSS
 * data, e.g. "PACK@525GR" for a WIP measured in "Whole" in its BOM). When there is no such unit,
 * or more than one, Purchase UOM stays empty instead of being guessed and the UI shows "Purchase
 * UOM belum tersedia". A failed product read never fails Add/Refresh Menu.
 */
class InternalMemoProductEnricher
{
    private const CONCURRENCY = 25;

    public function __construct(private readonly EsbCoreClient $esbCore) {}

    public function enrichMenu(RndInternalMemoMenu $menu): void
    {
        $materials = $menu->materials()->get();

        /** @var Collection<int, Collection<int, RndInternalMemoMaterial>> $byProductId */
        $byProductId = $materials
            ->filter(fn (RndInternalMemoMaterial $material): bool => $material->esb_product_id !== null)
            ->groupBy('esb_product_id');

        foreach ($byProductId->keys()->chunk(self::CONCURRENCY) as $productIds) {
            $responses = $this->fetchProducts($menu->company_code, $productIds->map(fn ($id): int => (int) $id)->values()->all());

            foreach ($productIds as $productId) {
                $product = $this->productResult($responses[(string) $productId] ?? null);

                foreach ($byProductId[$productId] as $material) {
                    $this->applyProduct($material, $product);
                }
            }
        }

        // Materials without a Product ID cannot be looked up at all — still stamp them as
        // "checked" so the UI does not confuse "no identity" with "not yet synced".
        $materials
            ->filter(fn (RndInternalMemoMaterial $material): bool => $material->esb_product_id === null)
            ->each(fn (RndInternalMemoMaterial $material) => $material->update(['product_synced_at' => now()]));
    }

    /**
     * @param  list<int>  $productIds
     * @return array<string, Response|null>
     */
    private function fetchProducts(string $companyCode, array $productIds): array
    {
        try {
            return $this->esbCore->poolGetPaths(
                $companyCode,
                collect($productIds)->mapWithKeys(fn (int $id): array => [(string) $id => '/product/'.$id])->all(),
            );
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    private function productResult(?Response $response): ?array
    {
        if ($response === null || $response->failed()) {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) && ($payload['status'] ?? null) === 'ok' && is_array($payload['result'] ?? null)
            ? $payload['result']
            : null;
    }

    /** @param array<string, mixed>|null $product */
    private function applyProduct(RndInternalMemoMaterial $material, ?array $product): void
    {
        if ($product === null) {
            $material->update(['product_synced_at' => now()]);

            return;
        }

        $details = collect(is_array($product['productDetails'] ?? null) ? $product['productDetails'] : [])->filter(fn ($detail): bool => is_array($detail));
        $purchaseUnits = $details->filter(fn (array $detail): bool => (bool) ($detail['isPurchase'] ?? false) && (bool) ($detail['flagActive'] ?? true))->values();
        $purchase = $purchaseUnits->count() === 1 ? $purchaseUnits->first() : null;
        $matched = $details->first(fn (array $detail): bool => (int) ($detail['productDetailID'] ?? 0) === (int) $material->esb_product_detail_id);

        $material->update([
            'purchase_uom_id' => $purchase !== null && (int) ($purchase['uomID'] ?? 0) > 0 ? (int) $purchase['uomID'] : null,
            'purchase_uom_name' => $purchase !== null && filled($purchase['uomName'] ?? null) ? (string) $purchase['uomName'] : null,
            'product_detail_snapshot' => [
                'productID' => $material->esb_product_id,
                'productCode' => $product['productCode'] ?? null,
                'productName' => $product['productName'] ?? null,
                'matchedProductDetail' => $matched,
                'purchaseProductDetail' => $purchase,
            ],
            'product_synced_at' => now(),
        ]);
    }
}
