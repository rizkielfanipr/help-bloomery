<?php

use App\Models\EsbPurchaseOrder;
use App\Models\EsbPurchaseOrderItem;
use App\Services\ProductPriceIndexService;

function makePurchase(string $date, int $productDetailId, float $qty, float $total, float $vat = 0, float $rate = 1): EsbPurchaseOrderItem
{
    $order = EsbPurchaseOrder::create([
        'purchase_num' => 'PO-'.uniqid(),
        'purchase_date' => $date,
        'rate' => $rate,
        'status_name' => 'Finished',
    ]);

    return EsbPurchaseOrderItem::create([
        'esb_purchase_order_id' => $order->id,
        'esb_detail_id' => random_int(1, 999999),
        'product_detail_id' => $productDetailId,
        'product_code' => 'BBMK001',
        'product_name' => 'Tepung Premium',
        'uom_name' => 'KG',
        'qty' => $qty,
        'conversion_qty' => 1,
        'stock_qty' => $qty,
        'total' => $total,
        'vat' => $vat,
    ]);
}

it('computes a quantity-weighted average price per productDetailID', function () {
    makePurchase('2026-06-01', 501, 10, 1_000_000);
    makePurchase('2026-06-15', 501, 5, 600_000);
    makePurchase('2026-06-10', 999, 2, 50_000);

    $prices = (new ProductPriceIndexService)->weightedAveragePrices([501, 999]);

    // (1,000,000 + 600,000) / (10 + 5) = 106,666.67
    expect((float) $prices[501]->average_price)->toEqualWithDelta(106666.67, 0.5)
        ->and((int) $prices[501]->po_count)->toBe(2)
        ->and((float) $prices[999]->average_price)->toBe(25000.0);
});

it('filters by purchase date range', function () {
    makePurchase('2026-05-01', 501, 10, 1_000_000);
    makePurchase('2026-06-15', 501, 5, 600_000);

    $prices = (new ProductPriceIndexService)->weightedAveragePrices([501], '2026-06-01', '2026-06-30');

    expect((float) $prices[501]->average_price)->toBe(120000.0)
        ->and((int) $prices[501]->po_count)->toBe(1);
});

it('returns an empty collection for products with no purchase history or no ids given', function () {
    expect((new ProductPriceIndexService)->weightedAveragePrices([12345]))->toBeEmpty()
        ->and((new ProductPriceIndexService)->weightedAveragePrices([]))->toBeEmpty();
});
