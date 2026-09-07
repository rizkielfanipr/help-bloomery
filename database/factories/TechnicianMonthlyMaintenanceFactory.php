<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\TechnicianMonthlyMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechnicianMonthlyMaintenanceFactory extends Factory
{
    protected $model = TechnicianMonthlyMaintenance::class;

    public function definition()
    {
        return [
            'branch_id' => Branch::factory(),
            'year' => $this->faker->year,
            'month' => $this->faker->month,
            'points' => $this->faker->numberBetween(0, 100),
        ];
    }
}
