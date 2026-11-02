<?php

namespace Database\Factories;

use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NonPoReceipt>
 */
class NonPoReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_key' => (string) Str::uuid(), 'status' => NonPoReceiptStatus::Draft, 'company_code' => 'BLSS',
            'esb_branch_id' => 10, 'esb_branch_code' => 'BLS', 'branch_name' => 'Central Kitchen', 'location_id' => 9, 'location_name' => 'Warehouse',
            'receipt_date' => now()->toDateString(), 'supplier_text' => fake()->company(), 'reason' => 'PO belum tersedia saat barang datang.',
            'document_type' => 'delivery_note', 'document_number' => 'DO-'.fake()->unique()->numerify('#####'), 'document_date' => now()->toDateString(),
            'document_match' => true, 'document_photos' => ['non-po-receipts/documents/document.jpg'], 'goods_photos' => ['non-po-receipts/goods/goods.jpg'],
            'created_by' => User::factory(),
        ];
    }

    public function awaitingPurchasing(): static
    {
        return $this->state(fn (): array => [
            'status' => NonPoReceiptStatus::AwaitingPurchasing, 'receipt_number' => 'NPR-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'submitted_at' => now(),
        ]);
    }

    public function poInProgress(): static
    {
        return $this->awaitingPurchasing()->state(fn (): array => ['status' => NonPoReceiptStatus::PoInProgress, 'processed_at' => now()]);
    }
}
