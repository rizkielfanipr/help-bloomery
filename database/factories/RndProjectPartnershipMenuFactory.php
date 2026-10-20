<?php

namespace Database\Factories;

use App\Models\RndProject;
use App\Models\RndProjectPartnershipMenu;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectPartnershipMenu>
 */
class RndProjectPartnershipMenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_project_id' => RndProject::factory()->partnership(),
            'company_code' => 'BLSS',
            'branch_code' => 'BLS',
            'esb_menu_id' => fake()->unique()->numberBetween(1, 999999),
            'menu_code' => mb_strtoupper(fake()->bothify('MENU-####')),
            'menu_name' => fake()->words(3, true),
            'category_name' => 'FOOD - CAKE',
            'menu_snapshot' => [],
            'sort_order' => 0,
            'created_by' => User::factory(),
        ];
    }
}
