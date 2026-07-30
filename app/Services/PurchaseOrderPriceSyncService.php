<?php

namespace App\Services;

use App\Models\EsbPurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurchaseOrderPriceSyncService
{
    private const VALID_STATUS_IDS = [8, 11, 25];

    public function __construct(private readonly EsbCoreService $esb) {}

    /**
     * @return array{orders:int, items:int, failed:int, errors:array<int,string>}
     */
    public function sync(string $dateFrom, string $dateTo, int $maxPages = 50, int $maxOrders = 20): array
    {
        $page = 1;
        $orders = 0;
        $items = 0;
        $failed = 0;
        $errors = [];
        $attempted = 0;

        do {
            $result = $this->esb->getPurchaseOrders([
                'page' => $page,
                'limit' => 50,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'sort' => '-purchaseDate',
            ]);

            foreach ($result['data'] as $header) {
                $statusId = (int) ($header['statusID'] ?? 0);
                if (! in_array($statusId, self::VALID_STATUS_IDS, true)) {
                    continue;
                }

                $purchaseNum = trim((string) ($header['purchaseNum'] ?? ''));
                if ($purchaseNum === '') {
                    continue;
                }

                $remoteEditedAt = $this->date($header['editedDate'] ?? null);
                $existing = EsbPurchaseOrder::query()->where('purchase_num', $purchaseNum)->first();
                if ($existing?->last_synced_at && (
                    ! $remoteEditedAt
                    || $existing->esb_edited_at?->equalTo($remoteEditedAt)
                )) {
                    continue;
                }

                if ($attempted >= $maxOrders) {
                    break 2;
                }
                $attempted++;

                try {
                    $detail = $this->esb->getPurchaseOrder($purchaseNum);
                    $items += $this->store($detail);
                    $orders++;
                } catch (Throwable $exception) {
                    $failed++;
                    if (count($errors) < 10) {
                        $errors[] = $purchaseNum.': '.$exception->getMessage();
                    }
                }
            }

            $hasNext = filled($result['next'])
                || (($result['page'] * $result['limit']) < $result['count']);
            $page++;
        } while ($hasNext && $page <= $maxPages);

        return compact('orders', 'items', 'failed', 'errors');
    }

    private function store(array $detail): int
    {
        return DB::transaction(function () use ($detail): int {
            $purchaseNum = (string) $detail['purchaseNum'];
            $order = EsbPurchaseOrder::query()->updateOrCreate(
                ['purchase_num' => $purchaseNum],
                [
                    'purchase_date' => $this->date($detail['purchaseDate'] ?? null),
                    'required_date' => $this->date($detail['requiredDate'] ?? null),
                    'branch_id' => $detail['branchID'] ?? null,
                    'branch_name' => $detail['branchName'] ?? null,
                    'supplier_id' => $detail['supplierID'] ?? null,
                    'supplier_name' => $detail['supplierName'] ?? null,
                    'currency_id' => $detail['currencyID'] ?? null,
                    'currency_name' => $detail['currencyName'] ?? null,
                    'rate' => (float) ($detail['rate'] ?? 1),
                    'purchase_total' => (float) ($detail['purchaseTotal'] ?? 0),
                    'status_id' => $detail['statusID'] ?? null,
                    'status_name' => $detail['statusName'] ?? null,
                    'esb_edited_at' => $this->date($detail['editedDate'] ?? null),
                    'last_synced_at' => now(),
                    'raw_payload' => $detail,
                ],
            );

            $detailIds = [];
            foreach ($detail['purchaseDetails'] ?? [] as $item) {
                $detailId = (int) ($item['ID'] ?? 0);
                if ($detailId < 1 || (int) ($item['productDetailID'] ?? 0) < 1) {
                    continue;
                }

                $detailIds[] = $detailId;
                $order->items()->updateOrCreate(
                    ['esb_detail_id' => $detailId],
                    [
                        'product_detail_id' => (int) $item['productDetailID'],
                        'product_id' => $item['productID'] ?? null,
                        'product_code' => $item['productCode'] ?? null,
                        'product_name' => $item['productName'] ?? 'Product '.$item['productDetailID'],
                        'uom_id' => $item['uomID'] ?? null,
                        'uom_name' => $item['uomName'] ?? null,
                        'qty' => (float) ($item['qty'] ?? 0),
                        'conversion_qty' => (float) ($item['convertionQty'] ?? 1),
                        'stock_qty' => (float) ($item['stockQty'] ?? 0),
                        'pricelist_price' => (float) ($item['pricelistPrice'] ?? 0),
                        'price' => (float) ($item['price'] ?? 0),
                        'discount' => (float) ($item['discount'] ?? 0),
                        'discount_percent' => (float) ($item['discountPercent'] ?? 0),
                        'vat' => (float) ($item['vat'] ?? 0),
                        'total' => (float) ($item['total'] ?? 0),
                        'last_price' => (float) ($item['lastPrice'] ?? 0),
                        'last_price_date' => $this->date($item['lastPriceDate'] ?? null),
                        'notes' => filled($item['notes'] ?? null) ? $item['notes'] : null,
                    ],
                );
            }

            $order->items()->when(
                $detailIds !== [],
                fn ($query) => $query->whereNotIn('esb_detail_id', $detailIds),
            )->when($detailIds === [], fn ($query) => $query)->delete();

            return count($detailIds);
        });
    }

    private function date(mixed $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value) : null;
    }
}
