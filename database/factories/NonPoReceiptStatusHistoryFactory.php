<?php

namespace Database\Factories;

use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonPoReceiptStatusHistory>
 */
class NonPoReceiptStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['non_po_receipt_id' => NonPoReceipt::factory(), 'from_status' => null, 'to_status' => NonPoReceiptStatus::Draft];
    }
}
