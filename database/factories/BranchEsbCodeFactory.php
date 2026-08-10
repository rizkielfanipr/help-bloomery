<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchEsbCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchEsbCode>
 */
class BranchEsbCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'esb_branch_code' => mb_strtoupper(fake()->unique()->lexify('???')),
            'esb_comcode' => mb_strtoupper(fake()->lexify('????')),
            'label' => 'Utama',
            'is_active' => true,
        ];
    }
}
