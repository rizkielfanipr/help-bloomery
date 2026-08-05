<?php

namespace App\Services;

use App\Models\EsbPurchaseOrderItem;
use Illuminate\Support\Collection;

class ProductPriceIndexService
{
    public static function baseQuantitySql(): string
    {
        return 'CASE WHEN esb_purchase_order_items.stock_qty > 0 THEN esb_purchase_order_items.stock_qty ELSE esb_purchase_order_items.qty * (CASE WHEN esb_purchase_order_items.conversion_qty > 1 THEN esb_purchase_order_items.conversion_qty ELSE 1 END) END';
    }

    public static function netAmountSql(): string
    {
        // The leading 1.0 forces floating-point division (SQLite performs
        // integer division when both SUM() operands are integer-affinity).
        return '(1.0 * (esb_purchase_order_items.total - esb_purchase_order_items.vat) * esb_purchase_orders.rate)';
    }

    /**
     * Quantity-weighted average purchase price per productDetailID, computed
     * from synced ESB purchase order items over an optional date range.
     *
     * @param  array<int, int>  $productDetailIds
     * @return Collection<int, object{product_detail_id: int, average_price: float, po_count: int}>
     */
    public function weightedAveragePrices(array $productDetailIds, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $productDetailIds = array_values(array_unique(array_filter(array_map('intval', $productDetailIds))));

        if ($productDetailIds === []) {
            return collect();
        }

        $baseQuantity = self::baseQuantitySql();
        $netAmount = self::netAmountSql();

        return EsbPurchaseOrderItem::query()
            ->join('esb_purchase_orders', 'esb_purchase_orders.id', '=', 'esb_purchase_order_items.esb_purchase_order_id')
            ->whereIn('esb_purchase_order_items.product_detail_id', $productDetailIds)
            ->when($dateFrom, fn ($query) => $query->whereDate('esb_purchase_orders.purchase_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('esb_purchase_orders.purchase_date', '<=', $dateTo))
            ->selectRaw("
                esb_purchase_order_items.product_detail_id,
                SUM($netAmount) / NULLIF(SUM($baseQuantity), 0) as average_price,
                COUNT(DISTINCT esb_purchase_orders.id) as po_count
            ")
            ->groupBy('esb_purchase_order_items.product_detail_id')
            ->get()
            ->keyBy('product_detail_id');
    }
}
