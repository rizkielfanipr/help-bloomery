<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_code' => 'BLSS', 'reference_number' => 'PO'.fake()->unique()->numerify('########'),
            'goods_receipt_date' => now()->toDateString(), 'esb_branch_id' => fake()->numberBetween(1, 20),
            'local_branch_id' => Branch::factory(),
            'branch_name' => fake()->city(), 'supplier_id' => fake()->numberBetween(1, 100),
            'supplier_name' => fake()->company(), 'location_id' => fake()->numberBetween(1, 100),
            'location_name' => 'Warehouse '.fake()->city(), 'status' => GoodsReceipt::STATUS_SUCCEEDED,
            'submitted_by' => User::factory(), 'submitted_at' => now(),
        ];
    }
}
