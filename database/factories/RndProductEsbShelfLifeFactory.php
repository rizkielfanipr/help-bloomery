<?php

namespace Database\Factories;

use App\Enums\RndShelfLifeUnit;
use App\Enums\RndStorageCondition;
use App\Models\RndProductEsbShelfLife;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a WIP master (company + Product Detail ID, docs/rnd-wip-shelf-life-prd.md §9.1).
 * Use `legacyMenu()` for rows shaped like the retired Menu master.
 *
 * @extends Factory<RndProductEsbShelfLife>
 */
class RndProductEsbShelfLifeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_code' => RndProductEsbShelfLife::DEFAULT_COMPANY_CODE,
            'esb_product_detail_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'product_code' => 'BW'.$this->faker->unique()->numerify('#####'),
            'product_name' => $this->faker->words(3, true),
            'shelf_life_value' => $this->faker->numberBetween(1, 12),
            'shelf_life_unit' => $this->faker->randomElement(RndShelfLifeUnit::cases())->value,
            'storage_condition' => $this->faker->randomElement(RndStorageCondition::cases())->value,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /**
     * A row of the retired Menu master: Menu ID only, legacy Indonesian units and free-text storage.
     */
    public function legacyMenu(): static
    {
        return $this->state(fn (array $attributes): array => [
            'esb_product_detail_id' => null,
            'esb_menu_id' => $this->faker->numberBetween(1000, 9999),
            'shelf_life_unit' => $this->faker->randomElement(['hari', 'minggu', 'bulan']),
            'storage_condition' => $this->faker->randomElement(['Chiller', 'Freezer', 'Suhu Ruang']),
        ]);
    }
}
