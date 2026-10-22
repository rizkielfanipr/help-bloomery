<?php

namespace Database\Factories;

use App\Enums\SalesReportDataSource;
use App\Models\ComplimentType;
use App\Models\SalesReport;
use App\Models\SalesReportCompliment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReportCompliment>
 */
class SalesReportComplimentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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
            'compliment_type_id' => ComplimentType::factory(),
            'compliment_type_name' => 'Compliment Owner',
            'attachment_paths' => ['sales-report-compliments/example.jpg'],
            'notes' => fake()->sentence(),
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
        ]);
    }

    /**
     * A row saved before the Sales Number requirement (PDF evidence was still accepted).
     */
    public function legacy(): static
    {
        return $this->state(fn (): array => [
            'sales_number' => null,
            'sales_number_source' => null,
            'source_comcode' => null,
            'source_branch_code' => null,
            'sales_transaction_at' => null,
            'attachment_paths' => ['sales-report-compliments/legacy-nota.pdf'],
        ]);
    }
}
