<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Actions\Rnd\ShelfLife\SyncWipProductCatalogAction;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoExtraProduct;
use App\Models\RndInternalMemoMaterial;
use App\Models\User;
use App\Services\EsbCoreClient;
use App\Services\Rnd\InternalMemo\InternalMemoItemIdentity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adds a product by hand to a Memo's Product Active summary — WIP or RAW, for Store or Kitchen.
 * Only the Product ID and Product Detail ID come from the picker; the product is re-read from
 * ESB Core BLSS (`/product/{id}`), so its unit, category, and Purchase UOM are never taken from
 * the browser. WIP must be category "Barang WIP" and RAW must not; an item the Menu BOMs already
 * place in the same Store/Kitchen section is refused.
 */
class AddExtraProductToInternalMemoAction
{
    public function __construct(private readonly EsbCoreClient $esbCore) {}

    public function execute(RndInternalMemo $memo, string $scope, string $kind, int $productId, int $productDetailId, User $actor): RndInternalMemoExtraProduct
    {
        if (! $actor->can('update', $memo)) {
            throw new AuthorizationException('Anda tidak berhak mengubah Memo Internal ini.');
        }

        if (! in_array($scope, [InternalMemoItemIdentity::SCOPE_STORE, InternalMemoItemIdentity::SCOPE_KITCHEN], true)
            || ! in_array($kind, [RndInternalMemoExtraProduct::KIND_WIP, RndInternalMemoExtraProduct::KIND_RAW], true)) {
            throw ValidationException::withMessages(['product' => 'Bagian Product Active tidak valid.']);
        }

        $product = $this->esbCore->successfulResult(
            $this->esbCore->request(RndInternalMemo::COMPANY_CODE, 'get', '/product/'.$productId),
            'mengambil detail product',
            RndInternalMemo::COMPANY_CODE,
            '/product/'.$productId,
        );
        $details = collect(is_array($product['productDetails'] ?? null) ? $product['productDetails'] : [])->filter(fn ($detail): bool => is_array($detail));
        $detail = $details->first(fn (array $detail): bool => (int) ($detail['productDetailID'] ?? 0) === $productDetailId);

        if ($detail === null || ! (bool) ($detail['flagActive'] ?? true) || ! (bool) ($product['flagActive'] ?? true)) {
            throw ValidationException::withMessages(['product' => 'Product atau satuannya tidak aktif di ESB BLSS.']);
        }

        $isWip = SyncWipProductCatalogAction::isWipCategory($product);
        if ($kind === RndInternalMemoExtraProduct::KIND_WIP && ! $isWip) {
            throw ValidationException::withMessages(['product' => 'Hanya product kategori Barang WIP yang dapat ditambahkan ke WIP.']);
        }
        if ($kind === RndInternalMemoExtraProduct::KIND_RAW && $isWip) {
            throw ValidationException::withMessages(['product' => 'Product kategori Barang WIP tidak dapat ditambahkan ke RAW.']);
        }

        if ($this->existsFromBom($memo, $scope, $productDetailId)) {
            throw ValidationException::withMessages(['product' => 'Product ini sudah ada dari BOM Menu pada bagian yang sama.']);
        }

        $purchaseUnits = $details->filter(fn (array $unit): bool => (bool) ($unit['isPurchase'] ?? false) && (bool) ($unit['flagActive'] ?? true))->values();
        $purchase = $purchaseUnits->count() === 1 ? $purchaseUnits->first() : null;

        try {
            $extra = $memo->extraProducts()->create([
                'scope' => $scope,
                'kind' => $kind,
                'esb_product_id' => $productId,
                'esb_product_detail_id' => $productDetailId,
                'product_code' => filled($product['productCode'] ?? null) ? (string) $product['productCode'] : null,
                'product_name' => (string) ($product['productName'] ?? 'Product '.$productId),
                'uom_name' => (string) ($detail['uomName'] ?? $detail['unit'] ?? '-'),
                'category_name' => filled($product['categoryName'] ?? null) ? (string) $product['categoryName'] : null,
                'purchase_uom_id' => $purchase !== null && (int) ($purchase['uomID'] ?? 0) > 0 ? (int) $purchase['uomID'] : null,
                'purchase_uom_name' => $purchase !== null && filled($purchase['uomName'] ?? null) ? (string) $purchase['uomName'] : null,
                'product_detail_snapshot' => ['matchedProductDetail' => $detail, 'purchaseProductDetail' => $purchase],
                'product_synced_at' => now(),
                'created_by' => $actor->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['product' => 'Product ini sudah ditambahkan pada bagian yang sama.']);
        }

        Log::info('rnd internal memo extra product added', ['memo_id' => $memo->id, 'extra_product_id' => $extra->id, 'scope' => $scope, 'kind' => $kind, 'user_id' => $actor->id]);

        return $extra;
    }

    private function existsFromBom(RndInternalMemo $memo, string $scope, int $productDetailId): bool
    {
        return RndInternalMemoMaterial::query()
            ->whereIn('rnd_internal_memo_menu_id', $memo->menus()->select('id'))
            ->where('esb_product_detail_id', $productDetailId)
            ->when(
                $scope === InternalMemoItemIdentity::SCOPE_STORE,
                fn ($query) => $query->where('depth', 0),
                fn ($query) => $query->where('depth', '>', 0),
            )
            ->exists();
    }
}
