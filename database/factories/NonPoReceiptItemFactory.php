<?php

namespace Database\Factories;

use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonPoReceiptItem>
 */
class NonPoReceiptItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'non_po_receipt_id' => NonPoReceipt::factory(), 'product_id' => fake()->numberBetween(1, 1000), 'product_detail_id' => fake()->unique()->numberBetween(1, 100000),
            'product_code' => fake()->unique()->bothify('PRD-####'), 'product_name' => fake()->words(2, true), 'uom_name' => 'KG',
            'physical_qty' => 10, 'accepted_qty' => 10, 'hold_qty' => 0, 'rejected_qty' => 0, 'disposition' => 'accepted',
            'qc_data' => NonPoReceiptItem::defaultQcData(), 'evidence_photos' => [],
        ];
    }

    /** All qty held or rejected with the evidence QC requires. */
    public function exception(string $qtyField): static
    {
        return $this->state(fn (): array => [
            'accepted_qty' => 0, 'hold_qty' => $qtyField === 'hold_qty' ? 10 : 0, 'rejected_qty' => $qtyField === 'rejected_qty' ? 10 : 0,
            'disposition' => $qtyField === 'hold_qty' ? 'hold' : 'rejected', 'evidence_photos' => ['non-po-receipts/items/evidence.jpg'],
            'qc_data' => array_replace(NonPoReceiptItem::defaultQcData(), [
                'condition' => 'problem', 'quarantineLocation' => 'Karantina A', 'rejectionCategory' => 'quality', 'rejectionReason' => 'Kemasan rusak',
            ]),
        ]);
    }
}
