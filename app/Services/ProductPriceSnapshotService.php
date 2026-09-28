<?php

namespace App\Services;

use App\Models\ProductPriceSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductPriceSnapshotService
{
    public function create(Carbon $snapshotDate, Carbon $periodStart, Carbon $periodEnd): int
    {
        $baseQuantity = ProductPriceIndexService::baseQuantitySql();
        $netAmount = ProductPriceIndexService::netAmountSql();
        $syncedAt = now();

        $rows = DB::table('esb_purchase_order_items')
            ->join('esb_purchase_orders', 'esb_purchase_orders.id', '=', 'esb_purchase_order_items.esb_purchase_order_id')
            ->whereDate('esb_purchase_orders.purchase_date', '>=', $periodStart)
            ->whereDate('esb_purchase_orders.purchase_date', '<=', $periodEnd)
            ->selectRaw("\n                esb_purchase_order_items.product_detail_id,\n                MAX(esb_purchase_order_items.product_id) as product_id,\n                MAX(esb_purchase_order_items.product_code) as product_code,\n                MAX(esb_purchase_order_items.product_name) as product_name,\n                MAX(esb_purchase_order_items.uom_name) as uom_name,\n                SUM($baseQuantity) as total_quantity,\n                SUM($netAmount) as total_amount,\n                SUM($netAmount) / NULLIF(SUM($baseQuantity), 0) as weighted_average_price,\n                COUNT(DISTINCT esb_purchase_orders.id) as purchase_count\n            ")
            ->groupBy('esb_purchase_order_items.product_detail_id')
            ->get();

        foreach ($rows->chunk(500) as $chunk) {
            ProductPriceSnapshot::query()->upsert(
                $chunk->map(fn (object $row): array => [
                    'snapshot_date' => $snapshotDate->toDateString(),
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'product_detail_id' => (int) $row->product_detail_id,
                    'product_id' => $row->product_id ? (int) $row->product_id : null,
                    'product_code' => $row->product_code,
                    'product_name' => $row->product_name,
                    'uom_name' => $row->uom_name,
                    'total_quantity' => (float) $row->total_quantity,
                    'total_amount' => (float) $row->total_amount,
                    'weighted_average_price' => (float) $row->weighted_average_price,
                    'purchase_count' => (int) $row->purchase_count,
                    'synced_at' => $syncedAt,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ])->all(),
                ['snapshot_date', 'product_detail_id'],
                ['period_start', 'period_end', 'product_id', 'product_code', 'product_name', 'uom_name', 'total_quantity', 'total_amount', 'weighted_average_price', 'purchase_count', 'synced_at', 'updated_at'],
            );
        }

        return $rows->count();
    }
}
