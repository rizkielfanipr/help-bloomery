<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptItem>
 */
class GoodsReceiptItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(), 'product_id' => fake()->numberBetween(1, 1000),
            'product_detail_id' => fake()->numberBetween(1, 1000), 'product_code' => fake()->bothify('PD-###'),
            'product_name' => fake()->words(3, true), 'uom_name' => 'PCS', 'ordered_qty' => 10,
            'outstanding_qty' => 10, 'received_qty' => 5, 'deviation_value' => 0,
        ];
    }
}
