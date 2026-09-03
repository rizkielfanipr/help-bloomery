<?php

namespace Database\Factories;

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
            'compliment_type_id' => ComplimentType::factory(),
            'compliment_type_name' => 'Compliment Owner',
            'attachment_paths' => ['sales-report-compliments/example.jpg'],
            'notes' => fake()->sentence(),
            'submitted_by' => null,
        ];
    }
}
