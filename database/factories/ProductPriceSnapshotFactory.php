<?php

namespace Database\Factories;

use App\Models\ProductPriceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPriceSnapshot>
 */
class ProductPriceSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'snapshot_date' => today(),
            'period_start' => today()->subDays(89),
            'period_end' => today(),
            'product_detail_id' => fake()->unique()->numberBetween(1, 999999),
            'product_id' => fake()->numberBetween(1, 99999),
            'product_code' => fake()->unique()->bothify('BBMK###'),
            'product_name' => fake()->words(3, true),
            'uom_name' => 'GR',
            'total_quantity' => 100,
            'total_amount' => 100000,
            'weighted_average_price' => 1000,
            'purchase_count' => 1,
            'synced_at' => now(),
        ];
    }
}
