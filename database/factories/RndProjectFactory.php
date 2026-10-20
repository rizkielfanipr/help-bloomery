<?php

namespace Database\Factories;

use App\Enums\RndProjectType;
use App\Models\RndProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProject>
 */
class RndProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'project_type' => RndProjectType::ReleaseProduct,
            'partnership_event_name' => null,
            'start_date' => today(),
            'end_date' => today()->addMonth(),
            'forecast_percentage' => 100,
        ];
    }

    public function premix(): static
    {
        return $this->state(fn (): array => ['project_type' => RndProjectType::Premix]);
    }

    public function partnership(): static
    {
        return $this->state(fn (): array => [
            'project_type' => RndProjectType::Partnership,
            'partnership_event_name' => fake()->company().' Collaboration',
        ]);
    }
}
