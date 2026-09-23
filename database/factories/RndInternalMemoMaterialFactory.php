<?php

namespace Database\Factories;

use App\Models\RndInternalMemoMaterial;
use App\Models\RndInternalMemoMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemoMaterial>
 */
class RndInternalMemoMaterialFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $qty = $this->faker->randomFloat(4, 1, 500);

        return [
            'rnd_internal_memo_menu_id' => RndInternalMemoMenu::factory(),
            'source_bom_id' => $this->faker->numberBetween(1, 100000),
            'source_path' => ['Menu', 'Bahan'],
            'depth' => 0,
            'product_code' => strtoupper($this->faker->bothify('RAW-####')),
            'product_name' => $this->faker->words(2, true),
            'category_name' => 'Bahan Baku Makanan',
            'uom_name' => $this->faker->randomElement(['GR', 'ML', 'PCS']),
            'quantity_per_menu' => $qty,
            'net_quantity' => $qty,
            'is_wip' => false,
            'is_packaging' => false,
        ];
    }
}
