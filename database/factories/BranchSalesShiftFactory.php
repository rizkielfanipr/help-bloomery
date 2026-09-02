<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchSalesShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchSalesShift>
 */
class BranchSalesShiftFactory extends Factory
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
            'shift_number' => 1,
            'name' => 'Shift 1',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_active' => true,
        ];
    }
}
