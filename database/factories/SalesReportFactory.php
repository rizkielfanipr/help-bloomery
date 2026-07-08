<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReport>
 */
class SalesReportFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-90 days', 'now');
        $shift1At = (clone $date)->modify('+8 hours');
        $shift2At = (clone $date)->modify('+16 hours');

        return [
            'branch_id' => Branch::inRandomOrder()->value('id'),
            'submitted_by' => User::inRandomOrder()->value('id'),
            'report_date' => $date->format('Y-m-d'),
            'modal_shift_1' => fake()->numberBetween(500000, 2000000),
            'modal_shift_2' => fake()->numberBetween(500000, 2000000),
            'shift_1_submitted_at' => $shift1At,
            'shift_2_submitted_at' => $shift2At,
        ];
    }
}
