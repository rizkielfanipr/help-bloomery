<?php

namespace Database\Factories;

use App\Models\RndBomDocumentMaterial;
use App\Models\RndProjectBom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndBomDocumentMaterial>
 */
class RndBomDocumentMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_project_bom_id' => fn (): int => RndProjectBom::query()->value('id')
                ?? throw new \LogicException('Create an RndProjectBom before using this factory.'),
            'name' => fake()->randomElement(['Air', 'Es Batu', 'Garam']),
            'quantity' => fake()->randomFloat(2, 1, 1000),
            'unit' => fake()->randomElement(['ml', 'gram', 'pcs']),
            'notes' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
