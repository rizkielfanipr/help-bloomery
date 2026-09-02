<?php

namespace Database\Factories;

use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Models\SalesReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BasketSizeRecord>
 */
class BasketSizeRecordFactory extends Factory
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
            'branch_id' => Branch::factory(),
            'report_date' => fake()->date(),
            'shift_number' => 1,
            'shift_name' => 'Shift 1',
            'shift_start_time' => '07:00:00',
            'shift_end_time' => '15:00:00',
            'revenue' => 1000000,
            'total_pax' => 25,
            'basket_size' => 40000,
            'staff_count' => 1,
            'calculated_at' => now(),
        ];
    }
}
