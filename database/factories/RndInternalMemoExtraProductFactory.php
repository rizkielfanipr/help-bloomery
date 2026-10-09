<?php

namespace Database\Factories;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoExtraProduct;
use App\Services\Rnd\InternalMemo\InternalMemoItemIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemoExtraProduct>
 */
class RndInternalMemoExtraProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rnd_internal_memo_id' => RndInternalMemo::factory(),
            'scope' => InternalMemoItemIdentity::SCOPE_STORE,
            'kind' => RndInternalMemoExtraProduct::KIND_RAW,
            'esb_product_id' => $this->faker->numberBetween(1, 99999),
            'esb_product_detail_id' => $this->faker->unique()->numberBetween(1, 999999),
            'product_code' => 'RM-'.$this->faker->unique()->numerify('#####'),
            'product_name' => ucwords($this->faker->words(2, true)),
            'uom_name' => 'GR',
            'category_name' => 'Bahan Baku Makanan',
            'product_synced_at' => now(),
        ];
    }

    public function wip(): static
    {
        return $this->state(['kind' => RndInternalMemoExtraProduct::KIND_WIP, 'category_name' => 'Barang WIP', 'product_code' => 'BW'.$this->faker->unique()->numerify('####')]);
    }

    public function kitchen(): static
    {
        return $this->state(['scope' => InternalMemoItemIdentity::SCOPE_KITCHEN]);
    }
}
