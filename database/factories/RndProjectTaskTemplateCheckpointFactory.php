<?php

namespace Database\Factories;

use App\Enums\RndProjectTaskCategory;
use App\Enums\RndProjectTaskPriority;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateCheckpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectTaskTemplateCheckpoint>
 */
class RndProjectTaskTemplateCheckpointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rnd_project_task_template_id' => RndProjectTaskTemplate::factory(),
            'title' => fake()->sentence(3),
            'task_type' => fake()->randomElement(RndProjectTaskCategory::cases())->value,
            'description' => fake()->sentence(),
            'priority' => RndProjectTaskPriority::Medium->value,
            'sort_order' => 0,
        ];
    }
}
