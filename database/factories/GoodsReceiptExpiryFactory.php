<?php

namespace Database\Factories;

use App\Models\GoodsReceiptExpiry;
use App\Models\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptExpiry>
 */
class GoodsReceiptExpiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goods_receipt_item_id' => GoodsReceiptItem::factory(),
            'expired_date' => now()->addYear()->toDateString(), 'quantity' => 5,
        ];
    }
}
