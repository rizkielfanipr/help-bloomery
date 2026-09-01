<?php

namespace Database\Factories;

use App\Models\RndProductSalesProjection;
use App\Models\RndProjectProduct;
use App\Models\SalesRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProductSalesProjection>
 */
class RndProductSalesProjectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_project_product_id' => fn (): int => RndProjectProduct::query()->inRandomOrder()->firstOrFail()->id,
            'sales_region_id' => fn (): int => SalesRegion::query()->inRandomOrder()->firstOrFail()->id,
            'projection_month' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-01'),
            'channel' => fake()->randomElement(['all', 'offline', 'online']),
            'target_quantity' => fake()->numberBetween(100, 10000),
            'target_revenue' => fake()->numberBetween(1000000, 500000000),
            'target_outlets' => fake()->numberBetween(1, 100),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
