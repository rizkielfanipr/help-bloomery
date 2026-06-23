<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CasualClockRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CasualClockRecord>
 */
class CasualClockRecordFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');
        $clockIn = Carbon::instance($date)->setTime(fake()->numberBetween(7, 9), fake()->randomElement([0, 15, 30, 45]));
        $clockOut = (clone $clockIn)->addHours(fake()->numberBetween(6, 9))->addMinutes(fake()->randomElement([0, 15, 30]));

        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'date' => $clockIn->toDateString(),
            'clock_in_at' => $clockIn,
            'clock_in_lat' => fake()->latitude(-7.0, -6.8),
            'clock_in_lng' => fake()->longitude(107.5, 107.7),
            'clock_out_at' => $clockOut,
            'clock_out_lat' => fake()->latitude(-7.0, -6.8),
            'clock_out_lng' => fake()->longitude(107.5, 107.7),
            'notes' => null,
        ];
    }

    public function clockedInOnly(): static
    {
        return $this->state(fn () => [
            'clock_out_at' => null,
            'clock_out_lat' => null,
            'clock_out_lng' => null,
        ]);
    }
}
