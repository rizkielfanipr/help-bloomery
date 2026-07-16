<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\StockCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCard>
 */
class StockCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'submitted_by' => null,
            'report_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'flag_unit' => 'stockUnit',
            'submitted_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
        ]);
    }
}
