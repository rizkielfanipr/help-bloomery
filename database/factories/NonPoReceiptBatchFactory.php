<?php

namespace Database\Factories;

use App\Models\NonPoReceiptBatch;
use App\Models\NonPoReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonPoReceiptBatch>
 */
class NonPoReceiptBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'non_po_receipt_item_id' => NonPoReceiptItem::factory(), 'batch_number' => fake()->bothify('BATCH-###'),
            'manufactured_date' => now()->subMonth()->toDateString(), 'expired_date' => now()->addYear()->toDateString(),
            'quantity' => 10, 'accepted_qty' => 10, 'hold_qty' => 0, 'rejected_qty' => 0,
        ];
    }
}
