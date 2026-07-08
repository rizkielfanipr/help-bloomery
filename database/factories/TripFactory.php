<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-60 days', 'now');
        $startedAt = (clone $date)->modify('+7 hours');
        $completedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(2, 8).' hours');

        return [
            'driver_id' => User::role('DRIVER')->inRandomOrder()->value('id'),
            'vehicle_id' => Vehicle::inRandomOrder()->value('id'),
            'trip_route_id' => TripRoute::inRandomOrder()->value('id'),
            'trip_date' => $date->format('Y-m-d'),
            'status' => TripStatus::Completed,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'has_fuel_fillup' => fake()->boolean(30),
            'meal_allowance_amount' => fake()->randomElement([0, 25000, 35000, 50000]),
            'notes' => null,
            'odo_start' => fake()->numberBetween(10000, 80000),
            'odo_end' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::InProgress,
            'started_at' => now()->subHours(fake()->numberBetween(1, 4)),
            'completed_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }
}
