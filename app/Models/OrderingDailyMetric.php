<?php

namespace App\Models;

use Database\Factories\OrderingDailyMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily fact per branch, company pair, product code, and UOM (docs/products/analytics/ordering-recommendation/prd.md §11.2).
 */
class OrderingDailyMetric extends Model
{
    /** @use HasFactory<OrderingDailyMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id', 'company_code', 'esb_branch_code', 'metric_date', 'product_code', 'product_detail_id',
        'product_name', 'category_name', 'uom', 'sales_driven_usage', 'pos_sales_usage', 'waste_quantity',
        'goods_in_quantity', 'ending_stock', 'stock_observed_at', 'usage_sync_run_id', 'stock_sync_run_id', 'input_version',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'sales_driven_usage' => 'decimal:4',
            'pos_sales_usage' => 'decimal:4',
            'waste_quantity' => 'decimal:4',
            'goods_in_quantity' => 'decimal:4',
            'ending_stock' => 'decimal:4',
            'stock_observed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
