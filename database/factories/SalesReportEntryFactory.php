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
            'payment_method_name' => fake()->unique()->randomElement(['QRIS', 'BCA', 'Mandiri', 'Cash']),
            'sales_system_amount' => fake()->numberBetween(100000, 5000000),
            'sales_store_amount' => fake()->numberBetween(100000, 5000000),
            'notes' => fake()->optional(0.2)->sentence(5),
        ];
    }
}
