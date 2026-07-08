<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
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
            'payment_method_id' => PaymentMethod::inRandomOrder()->value('id'),
            'shift_1_amount' => fake()->numberBetween(0, 5000000),
            'shift_2_amount' => fake()->numberBetween(0, 5000000),
            'notes' => fake()->optional(0.2)->sentence(5),
        ];
    }
}
