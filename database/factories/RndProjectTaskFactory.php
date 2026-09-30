<?php

namespace Database\Factories;

use App\Enums\RndProjectTaskCategory;
use App\Enums\RndProjectTaskPriority;
use App\Enums\RndProjectTaskStatus;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectTask>
 */
class RndProjectTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rnd_project_id' => fn () => RndProject::query()->create([
                'name' => fake()->words(3, true).' Project',
                'start_date' => now()->subMonth(),
                'end_date' => now()->addMonth(),
            ])->id,
            'title' => fake()->sentence(4),
            'task_type' => fake()->randomElement(RndProjectTaskCategory::cases())->value,
            'description' => fake()->sentence(),
            'assigned_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'priority' => RndProjectTaskPriority::Medium->value,
            'status' => RndProjectTaskStatus::Draft->value,
        ];
    }
}
