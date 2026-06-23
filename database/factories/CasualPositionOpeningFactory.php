<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CasualPosition;
use App\Models\CasualPositionOpening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CasualPositionOpening>
 */
class CasualPositionOpeningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'casual_position_id' => CasualPosition::factory(),
            'branch_id' => Branch::factory(),
            'work_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'total_slots' => fake()->numberBetween(1, 10),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'posted_by' => User::factory(),
        ];
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes): array => ['total_slots' => 1]);
    }
}
