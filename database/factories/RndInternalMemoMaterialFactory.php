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

    /** Purchase UOM proven for this item (docs/rnd-internal-memo-simplification-prd.md §8.1). */
    public function withPurchaseUom(?string $name = 'GR', ?int $id = 5): static
    {
        return $this->state(fn (): array => [
            'purchase_uom_id' => $id,
            'purchase_uom_name' => $name,
            'product_synced_at' => now(),
        ]);
    }

    public function withMinimumOrder(float $minimumOrder = 10): static
    {
        return $this->state(fn (): array => ['minimum_order' => $minimumOrder]);
    }
}
