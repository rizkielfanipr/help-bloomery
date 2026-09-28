<?php

namespace App\Services;

use App\Models\ProductPriceSnapshot;
use App\Models\RndProjectBom;
use Illuminate\Support\Collection;

class WipPriceIndexService
{
    /** @return Collection<int, array<string, mixed>> */
    public function prices(): Collection
    {
        $latestSnapshot = ProductPriceSnapshot::query()->max('snapshot_date');
        $materialPrices = $latestSnapshot
            ? ProductPriceSnapshot::query()->where('snapshot_date', $latestSnapshot)->get()->keyBy('product_detail_id')
            : collect();
        $boms = RndProjectBom::query()
            ->where('is_active', true)
            ->whereNotNull('detail_snapshot')
            ->latest('last_synced_at')
            ->get()
            ->unique(fn (RndProjectBom $bom): int|string => (int) data_get($bom->detail_snapshot, 'productDetailID') ?: strtoupper((string) data_get($bom->detail_snapshot, 'productCode')))
            ->filter(fn (RndProjectBom $bom): bool => $this->isWip($bom->detail_snapshot ?? []));
        $byProductDetail = $boms->keyBy(fn (RndProjectBom $bom): int => (int) data_get($bom->detail_snapshot, 'productDetailID'));
        $byProductCode = $boms->keyBy(fn (RndProjectBom $bom): string => strtoupper((string) data_get($bom->detail_snapshot, 'productCode')));

        return $boms->map(function (RndProjectBom $bom) use ($materialPrices, $byProductDetail, $byProductCode): array {
            $visited = [];
            $result = $this->calculate($bom, $materialPrices, $byProductDetail, $byProductCode, $visited);

            return [
                'bom_id' => $bom->esb_bom_id,
                'product_detail_id' => (int) data_get($bom->detail_snapshot, 'productDetailID'),
                'product_code' => (string) data_get($bom->detail_snapshot, 'productCode', $bom->bom_code),
                'product_name' => (string) data_get($bom->detail_snapshot, 'productName', $bom->product_name ?: $bom->bom_name),
                'uom_name' => (string) data_get($bom->detail_snapshot, 'uomName', $bom->uom_name),
                'price' => $result['price'],
                'material_count' => $result['material_count'],
                'complete' => $result['complete'],
                'message' => $result['message'],
                'snapshot_date' => $materialPrices->first()?->snapshot_date,
            ];
        })->sortBy('product_name')->values();
    }

    /** @return array{price:float,material_count:int,complete:bool,message:string} */
    private function calculate(RndProjectBom $bom, Collection $materialPrices, Collection $byProductDetail, Collection $byProductCode, array &$visited): array
    {
        if (isset($visited[$bom->id])) {
            return ['price' => 0, 'material_count' => 0, 'complete' => false, 'message' => 'Siklus BOM terdeteksi'];
        }
        $visited[$bom->id] = true;
        $total = 0.0;
        $materialCount = 0;
        $complete = true;
        $messages = [];

        foreach (data_get($bom->detail_snapshot, 'bomDetails', []) as $component) {
            $qty = (float) ($component['qty'] ?? 0);
            $detailId = (int) ($component['productDetailID'] ?? 0);
            $code = strtoupper(trim((string) ($component['productCode'] ?? '')));
            $child = $byProductDetail->get($detailId) ?: $byProductCode->get($code);

            if ($this->isWip($component)) {
                if (! $child) {
                    $complete = false;
                    $messages[] = ($code ?: 'WIP').' belum memiliki BOM lokal';

                    continue;
                }
                $childResult = $this->calculate($child, $materialPrices, $byProductDetail, $byProductCode, $visited);
                $total += $qty * $childResult['price'];
                $materialCount += $childResult['material_count'];
                $complete = $complete && $childResult['complete'];
                if (! $childResult['complete']) {
                    $messages[] = $childResult['message'];
                }

                continue;
            }

            $snapshot = $materialPrices->get($detailId);
            if (! $snapshot) {
                $complete = false;
                $messages[] = ($code ?: 'Product '.$detailId).' belum memiliki harga purchase';

                continue;
            }
            $total += $qty * (float) $snapshot->weighted_average_price;
            $materialCount++;
        }

        unset($visited[$bom->id]);

        return [
            'price' => $total,
            'material_count' => $materialCount,
            'complete' => $complete,
            'message' => $complete ? 'Dihitung dari '.$materialCount.' bahan' : implode('; ', array_unique(array_filter($messages))),
        ];
    }

    private function isWip(array $product): bool
    {
        $code = strtoupper(trim((string) ($product['productCode'] ?? '')));
        $category = mb_strtolower(trim((string) ($product['categoryName'] ?? $product['category'] ?? '')));

        return str_starts_with($code, 'BW') || str_contains($category, 'barang wip');
    }
}
