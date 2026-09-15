<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorComplianceIncident>
 */
class VendorComplianceIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_number' => 'VCI-'.fake()->unique()->numerify('########'),
            'goods_receipt_id' => GoodsReceipt::factory(),
            'goods_receipt_item_id' => GoodsReceiptItem::factory(),
            'supplier_id' => fake()->numberBetween(1, 1000),
            'supplier_name' => fake()->company(),
            'category' => 'quality', 'severity' => 'medium', 'demerit_points' => 10,
            'affected_quantity' => 5, 'status' => 'open', 'description' => fake()->sentence(),
            'evidence_photos' => [], 'reported_by' => User::factory(), 'occurred_at' => now(),
        ];
    }
}
