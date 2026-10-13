<?php

namespace Database\Factories;

use App\Enums\BriefingSubmissionType;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingRecordRequirement>
 */
class BriefingRecordRequirementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'briefing_record_id' => BriefingRecord::factory(),
            'task_key' => 'requirement_'.fake()->unique()->numerify('####'),
            'label' => fake()->sentence(3),
            'submission_type' => BriefingSubmissionType::CameraOnly->value,
            'is_required' => true,
            'include_in_score' => true,
            'sort_order' => 0,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => false,
        ]);
    }
}
