<?php

namespace App\Models;

use App\Enums\OrderingConfidence;
use App\Enums\OrderingTrendLabel;
use Database\Factories\OrderingForecastItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recommendation per branch, company pair, product, and UOM (docs/products/analytics/ordering-recommendation/prd.md §11.4).
 */
class OrderingForecastItem extends Model
{
    /** @use HasFactory<OrderingForecastItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valid_days' => 'integer',
            'historical_demand' => 'decimal:4',
            'average_daily_demand' => 'decimal:4',
            'weekday_baseline' => 'array',
            'trend_raw' => 'decimal:4',
            'trend_final' => 'decimal:4',
            'trend_label' => OrderingTrendLabel::class,
            'historical_waste' => 'decimal:4',
            'waste_rate_raw' => 'decimal:4',
            'waste_rate_capped' => 'decimal:4',
            'forecast_demand' => 'decimal:4',
            'expected_waste' => 'decimal:4',
            'buffer_percent' => 'decimal:4',
            'safety_buffer' => 'decimal:4',
            'available_stock' => 'decimal:4',
            'stock_observed_at' => 'datetime',
            'confirmed_inbound' => 'decimal:4',
            'gross_requirement' => 'decimal:4',
            'raw_recommendation' => 'decimal:4',
            'final_recommendation' => 'decimal:4',
            'coverage_days' => 'decimal:2',
            'predicted_stockout_date' => 'date',
            'confidence' => OrderingConfidence::class,
            'reason_codes' => 'array',
            'daily_breakdown' => 'array',
            'backtest' => 'array',
            'priority_score' => 'decimal:4',
        ];
    }

    /** Percentage change of the capped trend factor; null for New/No-demand/unknown trends. */
    public function trendPercent(): ?float
    {
        return in_array($this->trend_label, [OrderingTrendLabel::Rising, OrderingTrendLabel::Falling, OrderingTrendLabel::Stable], true) && $this->trend_final !== null
            ? round(((float) $this->trend_final - 1) * 100, 1)
            : null;
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OrderingForecastRun::class, 'ordering_forecast_run_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
