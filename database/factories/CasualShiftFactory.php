<?php

namespace Database\Factories;

use App\Models\CasualShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CasualShift>
 */
class CasualShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Shift '.fake()->randomElement(['Pagi', 'Siang', 'Malam']),
            'start_time' => fake()->randomElement(['07:00:00', '08:00:00', '14:00:00']),
            'end_time' => fake()->randomElement(['15:00:00', '16:00:00', '22:00:00']),
            'tolerance_late_minutes' => 15,
            'tolerance_early_out_minutes' => 15,
            'location_required' => false,
            'location_lat' => null,
            'location_lng' => null,
            'location_radius_meters' => 100,
            'is_active' => true,
        ];
    }
}
