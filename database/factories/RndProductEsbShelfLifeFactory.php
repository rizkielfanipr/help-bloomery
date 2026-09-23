<?php

namespace Database\Factories;

use App\Models\RndProductEsbShelfLife;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProductEsbShelfLife>
 */
class RndProductEsbShelfLifeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_code' => 'BLSS',
            'esb_menu_id' => $this->faker->numberBetween(1000, 9999),
            'product_name' => $this->faker->words(3, true),
            'shelf_life_value' => $this->faker->numberBetween(1, 12),
            'shelf_life_unit' => $this->faker->randomElement(['hari', 'minggu', 'bulan']),
            'storage_condition' => $this->faker->randomElement(['Chiller', 'Freezer', 'Suhu Ruang']),
            'is_active' => true,
        ];
    }
}
