<?php

namespace Database\Factories;

use App\Enums\OrderingConfidence;
use App\Enums\OrderingTrendLabel;
use App\Models\OrderingForecastItem;
use App\Models\OrderingForecastRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderingForecastItem>
 */
class OrderingForecastItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ordering_forecast_run_id' => OrderingForecastRun::factory(),
            'branch_id' => fn (array $attributes): int => OrderingForecastRun::query()->findOrFail($attributes['ordering_forecast_run_id'])->branch_id,
            'company_code' => 'BLSS',
            'esb_branch_code' => 'BPL',
            'product_code' => 'BW-'.fake()->unique()->numerify('####'),
            'product_name' => fake()->words(2, true),
            'category_name' => 'Barang WIP',
            'uom' => 'PCS',
            'valid_days' => 56,
            'historical_demand' => 560,
            'average_daily_demand' => 10,
            'trend_raw' => 1.1,
            'trend_final' => 1.1,
            'trend_label' => OrderingTrendLabel::Rising,
            'historical_waste' => 20,
            'waste_rate_raw' => 0.0345,
            'waste_rate_capped' => 0.0345,
            'forecast_demand' => 77,
            'expected_waste' => 2.6565,
            'buffer_percent' => 0.10,
            'safety_buffer' => 7.7,
            'available_stock' => 30,
            'stock_observed_at' => now()->subHours(6),
            'confirmed_inbound' => null,
            'gross_requirement' => 87.3565,
            'raw_recommendation' => 57.3565,
            'final_recommendation' => 57.3565,
            'coverage_days' => 3,
            'predicted_stockout_date' => now()->addDays(3)->toDateString(),
            'confidence' => OrderingConfidence::Medium,
            'reason_codes' => ['inbound_not_available'],
            'explanation' => 'Contoh penjelasan.',
            'daily_breakdown' => [],
            'selected_method' => 'weekday_weighted',
            'backtest' => null,
            'priority_score' => 0.6,
        ];
    }

    public function blocked(string $reason = 'stock_not_available'): static
    {
        return $this->state(fn (): array => [
            'confidence' => OrderingConfidence::Blocked,
            'reason_codes' => [$reason],
            'available_stock' => null,
            'gross_requirement' => null,
            'raw_recommendation' => null,
            'final_recommendation' => null,
            'coverage_days' => null,
            'predicted_stockout_date' => null,
            'priority_score' => null,
        ]);
    }
}
