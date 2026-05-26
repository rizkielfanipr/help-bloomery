<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => User::factory(),
            'vehicle_id' => null,
            'trip_route_id' => TripRoute::factory(),
            'trip_date' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'status' => TripStatus::Completed,
            'started_at' => now()->subHours(8),
            'completed_at' => now()->subHours(2),
            'has_fuel_fillup' => false,
            'meal_allowance_amount' => fake()->randomElement([0, 25000, 35000, 50000]),
            'notes' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::InProgress,
            'started_at' => now()->subHours(2),
            'completed_at' => null,
            'has_fuel_fillup' => null,
        ]);
    }
}
