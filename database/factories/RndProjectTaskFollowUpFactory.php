<?php

namespace Database\Factories;

use App\Enums\RndProjectTaskFollowUpType;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectTaskFollowUp>
 */
class RndProjectTaskFollowUpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rnd_project_task_assignment_id' => RndProjectTaskAssignment::factory(),
            'submitted_by' => User::factory(),
            'follow_up_type' => RndProjectTaskFollowUpType::Progress->value,
            'notes' => fake()->sentence(),
        ];
    }
}
