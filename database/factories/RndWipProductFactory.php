<?php

namespace Database\Factories;

use App\Models\RndProductEsbShelfLife;
use App\Models\RndWipProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndWipProduct>
 */
class RndWipProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_code' => RndProductEsbShelfLife::DEFAULT_COMPANY_CODE,
            'esb_product_id' => $this->faker->numberBetween(1, 999999),
            'product_detail_id' => $this->faker->unique()->numberBetween(1, 999999),
            'product_code' => 'BW'.$this->faker->unique()->numerify('#####'),
            'product_name' => ucwords($this->faker->words(2, true)),
            'uom_name' => 'GRAM',
            'is_base' => true,
            'category_name' => 'Barang WIP',
            'is_active' => true,
            'last_synced_at' => now(),
        ];
    }

    /**
     * Another unit of the same ESB Product.
     */
    public function unitOf(RndWipProduct $base, string $uom = 'PORSI'): static
    {
        return $this->state([
            'esb_product_id' => $base->esb_product_id,
            'product_code' => $base->product_code,
            'product_name' => $base->product_name,
            'uom_name' => $uom,
            'is_base' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
