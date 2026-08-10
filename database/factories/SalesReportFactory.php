<?php

namespace Database\Factories;

use App\Enums\SalesReportStatus;
use App\Models\Branch;
use App\Models\SalesReport;
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
            'report_date' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'submitted_at' => fake()->dateTimeBetween('-90 days', 'now'),
            'status' => SalesReportStatus::PendingSupervisor->value,
        ];
    }
}
