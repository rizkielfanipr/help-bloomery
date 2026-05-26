<?php

namespace Database\Factories;

use App\Models\TripRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripRoute>
 */
class TripRouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = ['Jakarta', 'Bandung', 'Semarang', 'Yogyakarta', 'Solo', 'Surabaya', 'Malang', 'Bali'];
        $from = fake()->randomElement($cities);
        $to = fake()->randomElement(array_filter($cities, fn ($c) => $c !== $from));

        return [
            'name' => $from.' - '.$to,
            'description' => null,
            'meal_allowance_amount' => null,
            'requires_waypoint_attachment' => fake()->boolean(30),
            'is_active' => true,
        ];
    }
}
