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
        return [
            'branch_id' => Branch::inRandomOrder()->value('id'),
            'submitted_by' => User::inRandomOrder()->value('id'),
            'report_date' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'submitted_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
