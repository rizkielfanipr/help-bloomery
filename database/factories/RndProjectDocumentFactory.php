<?php

namespace Database\Factories;

use App\Models\RndProject;
use App\Models\RndProjectDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectDocument>
 */
class RndProjectDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_project_id' => RndProject::factory(),
            'name' => fake()->words(3, true),
            'file_path' => 'rnd/projects/documents/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1000, 1000000),
            'created_by' => User::factory(),
        ];
    }
}
