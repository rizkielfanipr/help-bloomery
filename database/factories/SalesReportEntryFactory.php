<?php

namespace Database\Factories;

use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReportEntry>
 */
class SalesReportEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_report_id' => SalesReport::factory(),
            'shift_number' => 1,
            'label' => 'TAKEAWAY',
            'payment_method_name' => fake()->unique()->randomElement(['QRIS', 'BCA', 'Mandiri', 'Cash']),
            'sales_store_amount' => fake()->numberBetween(100000, 5000000),
            'notes' => fake()->optional(0.2)->sentence(5),
        ];
    }
}
