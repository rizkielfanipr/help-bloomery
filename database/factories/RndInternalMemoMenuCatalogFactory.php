<?php

namespace Database\Factories;

use App\Models\RndInternalMemoMenuCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemoMenuCatalog>
 */
class RndInternalMemoMenuCatalogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_code' => 'BLSS',
            'branch_code' => 'BLS',
            'menu_id' => fake()->unique()->numberBetween(1, 999999),
            'menu_code' => mb_strtoupper(fake()->bothify('MENU-####')),
            'menu_name' => fake()->words(3, true),
            'bom_id' => fake()->numberBetween(0, 999999),
            'bom_name' => fake()->words(3, true),
            'category_detail' => 'FOOD - CAKE',
            'flag_active' => true,
            'raw_snapshot' => [],
            'synced_at' => now(),
        ];
    }
}
