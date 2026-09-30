<?php

namespace Database\Factories;

use App\Enums\RndProjectTaskReminderType;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndProjectTaskReminder>
 */
class RndProjectTaskReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rnd_project_task_assignment_id' => RndProjectTaskAssignment::factory(),
            'reminder_type' => RndProjectTaskReminderType::DueIn3Days->value,
            'reminder_date' => now()->toDateString(),
        ];
    }
}
