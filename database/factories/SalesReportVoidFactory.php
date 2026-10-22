<?php

namespace Database\Factories;

use App\Enums\SalesReportDataSource;
use App\Models\SalesReport;
use App\Models\SalesReportVoid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReportVoid>
 */
class SalesReportVoidFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_report_id' => SalesReport::factory(),
            'shift_number' => 1,
            'sales_number' => (string) fake()->unique()->numerify('INV########'),
            'sales_number_source' => SalesReportDataSource::EsbSnapshot,
            'source_comcode' => 'TAKEAWAY_CODE',
            'source_branch_code' => 'BPL',
            'sales_transaction_at' => now()->setTime(12, 0),
            'transaction_amount' => 75000,
            'payment_method_id' => null,
            'payment_method_name' => 'QRIS',
            'payment_method_source' => SalesReportDataSource::EsbSnapshot,
            'reason' => 'Salah input menu oleh kasir.',
            'original_receipt_path' => 'sales-report-voids/original.jpg',
            'voided_receipt_path' => 'sales-report-voids/voided.jpg',
            'submitted_by' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (): array => [
            'sales_number_source' => SalesReportDataSource::Manual,
            'source_comcode' => null,
            'source_branch_code' => null,
            'sales_transaction_at' => null,
            'transaction_amount' => null,
        ]);
    }
}
