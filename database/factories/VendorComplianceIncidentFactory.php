<?php

namespace Database\Factories;

use App\Enums\VendorComplianceLiabilityStatus;
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

    /** A header-level Late Delivery candidate waiting for Purchasing review, with 0 points. */
    public function lateDelivery(int $delayDays = 3, string $purchaseNumber = 'PO-LATE-001'): static
    {
        return $this->state(fn (): array => [
            'goods_receipt_item_id' => null,
            'category' => VendorComplianceIncident::CATEGORY_LATE_DELIVERY,
            'severity' => 'medium', 'demerit_points' => 0, 'affected_quantity' => null,
            'source_type' => VendorComplianceIncident::CATEGORY_LATE_DELIVERY,
            'source_key' => VendorComplianceIncident::lateDeliverySourceKey('BLSS', $purchaseNumber),
            'liability_status' => VendorComplianceLiabilityStatus::Pending,
            'required_date' => now()->subDays($delayDays)->toDateString(),
            'first_late_receipt_date' => now()->toDateString(),
            'latest_late_receipt_date' => now()->toDateString(),
            'delay_days' => $delayDays,
            'description' => "Penerimaan PO {$purchaseNumber} terdeteksi {$delayDays} hari setelah tanggal dibutuhkan.",
        ]);
    }
}
