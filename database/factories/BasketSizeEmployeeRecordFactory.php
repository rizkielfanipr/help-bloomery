<?php

namespace Database\Factories;

use App\Models\BasketSizeEmployeeRecord;
use App\Models\BasketSizeRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BasketSizeEmployeeRecord>
 */
class BasketSizeEmployeeRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'basket_size_record_id' => BasketSizeRecord::factory(),
            'employee_id' => Employee::factory(),
            'employee_code' => fake()->unique()->numerify('EMP######'),
            'employee_name' => fake()->name(),
            'employee_position' => fake()->jobTitle(),
            'basket_size_credit' => 40000,
        ];
    }
}
