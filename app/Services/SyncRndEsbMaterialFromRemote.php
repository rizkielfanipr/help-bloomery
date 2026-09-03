<?php

namespace App\Services;

use App\Models\RndProductEsbMaterial;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncRndEsbMaterialFromRemote
{
    public function __construct(private EsbCoreService $esb) {}

    public function execute(RndProductEsbMaterial $material, ?array $remoteProduct = null): void
    {
        abort_unless($material->esb_product_id, 422, 'Bahan belum tertaut ke Master Product ESB.');

        $remoteProduct ??= $this->esb->findProductById($material->esb_product_id);
        if (! is_array($remoteProduct)) {
            throw new RuntimeException("Master Product ESB ID {$material->esb_product_id} tidak ditemukan atau sudah tidak aktif.");
        }

        DB::transaction(function () use ($material, $remoteProduct): void {
            $material->update([
                'category_id' => (int) ($remoteProduct['categoryID'] ?? $material->category_id),
                'category_name' => $remoteProduct['categoryName'] ?? $material->category_name,
                'sub_category_id' => (int) ($remoteProduct['subCategoryID'] ?? $material->sub_category_id),
                'sub_category_name' => $remoteProduct['subCategoryName'] ?? $material->sub_category_name,
                'product_code' => trim((string) ($remoteProduct['productCode'] ?? $material->product_code)),
                'product_name' => trim((string) ($remoteProduct['productName'] ?? $material->product_name)),
                'notes' => $remoteProduct['notes'] ?? $material->notes,
                'last_response' => $remoteProduct,
                'sync_error' => null,
                'synced_at' => now(),
            ]);

            $details = collect($remoteProduct['productDetails'] ?? [])->values();
            if ($details->isEmpty()) {
                return;
            }

            $existingUnits = $material->units()->get();
            $hasExplicitBase = $details->contains(fn (array $detail): bool => (bool) ($detail['isBase'] ?? false)
                || mb_strtolower((string) data_get($detail, 'defaultUnit.baseUnit', '')) === 'yes'
            );
            $material->units()->delete();
            $material->units()->createMany($details->map(function (array $detail, int $index) use ($existingUnits, $hasExplicitBase): array {
                $detailId = (int) ($detail['productDetailID'] ?? 0);
                $existing = $existingUnits->firstWhere('esb_product_detail_id', $detailId)
                    ?? $existingUnits->firstWhere('sku', (string) ($detail['sku'] ?? ''));
                $isBase = (bool) ($detail['isBase'] ?? false)
                    || mb_strtolower((string) data_get($detail, 'defaultUnit.baseUnit', '')) === 'yes'
                    || ($index === 0 && ! $hasExplicitBase);
                $uomId = (int) ($detail['uomID'] ?? $existing?->uom_id ?? 0);

                if ($uomId < 1) {
                    throw new RuntimeException('ESB tidak mengembalikan UOM ID untuk salah satu unit produk.');
                }

                return [
                    'uom_id' => $uomId,
                    'uom_name' => (string) ($detail['uomName'] ?? $detail['unit'] ?? $existing?->uom_name ?? ''),
                    'esb_product_detail_id' => $detailId > 0 ? $detailId : null,
                    'sku' => (string) ($detail['sku'] ?? $existing?->sku ?? ''),
                    'conversion_factor' => (float) ($detail['qty'] ?? $detail['conversionFactor'] ?? $existing?->conversion_factor ?? 1),
                    'base_price' => (float) ($detail['basePrice'] ?? $existing?->base_price ?? 0),
                    'is_base' => $isBase,
                    'is_stock' => (bool) ($detail['isStock'] ?? $isBase),
                    'is_purchase' => (bool) ($detail['isPurchase'] ?? $isBase),
                    'is_transfer' => (bool) ($detail['isTransfer'] ?? $isBase),
                    'is_sales' => (bool) ($detail['isSales'] ?? $isBase),
                    'flag_active' => (bool) ($detail['flagActive'] ?? true),
                ];
            })->all());

            $baseUnit = $material->units()->where('is_base', true)->first() ?? $material->units()->first();
            if ($baseUnit) {
                $material->update([
                    'uom_id' => $baseUnit->uom_id,
                    'uom_name' => $baseUnit->uom_name,
                    'sku' => $baseUnit->sku,
                    'conversion_factor' => $baseUnit->conversion_factor,
                    'base_price' => $baseUnit->base_price,
                ]);
            }
        });
    }
}
