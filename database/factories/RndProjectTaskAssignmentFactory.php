<?php

namespace Database\Factories;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Models\Branch;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectTaskAssignment>
 */
class RndProjectTaskAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rnd_project_task_id' => RndProjectTask::factory(),
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'status' => RndProjectTaskAssignmentStatus::Assigned->value,
            'assigned_at' => now(),
        ];
    }
}
