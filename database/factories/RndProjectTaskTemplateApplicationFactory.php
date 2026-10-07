<?php

namespace Database\Factories;

use App\Models\RndProject;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RndProjectTaskTemplateApplication>
 */
class RndProjectTaskTemplateApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rnd_project_id' => fn () => RndProject::query()->create([
                'name' => fake()->words(3, true).' Project',
                'start_date' => now()->subMonth(),
                'end_date' => now()->addMonth(),
            ])->id,
            'rnd_project_task_template_id' => RndProjectTaskTemplate::factory(),
            'template_name' => fn (array $attributes): string => RndProjectTaskTemplate::query()
                ->find($attributes['rnd_project_task_template_id'])?->name ?? 'Template',
            'idempotency_key' => (string) Str::uuid(),
            'checkpoint_snapshot' => [],
            'applied_at' => now(),
        ];
    }
}
