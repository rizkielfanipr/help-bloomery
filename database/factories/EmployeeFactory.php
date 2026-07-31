<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'employee_code' => fake()->unique()->numerify('EMP######'),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'is_active' => true,
        ];
    }
}
