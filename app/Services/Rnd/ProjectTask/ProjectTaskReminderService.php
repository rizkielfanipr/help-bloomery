<?php

namespace App\Services\Rnd\ProjectTask;

use App\Enums\RndProjectTaskReminderType;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds which (assignment, reminder type) pairs are due today and haven't been logged yet
 * (docs/rnd-project-task-calendar-prd.md §16). The `rnd_task_reminder_unique` constraint on
 * `RndProjectTaskReminder` is the final idempotency guard — this query is just an optimization to
 * avoid dispatching Jobs that would immediately no-op.
 */
class ProjectTaskReminderService
{
    /** @return Collection<int, array{assignment_id: int, reminder_type: string}> */
    public function dueReminders(): Collection
    {
        $today = today();

        return RndProjectTaskAssignment::query()
            ->with('task')
            ->whereIn('status', ['assigned', 'in_progress', 'revision_required'])
            ->get()
            ->map(function (RndProjectTaskAssignment $assignment) use ($today): ?array {
                $type = $this->reminderTypeFor($assignment, $today);

                if ($type === null) {
                    return null;
                }

                $alreadyLogged = RndProjectTaskReminder::query()
                    ->where('rnd_project_task_assignment_id', $assignment->id)
                    ->where('reminder_type', $type->value)
                    ->whereDate('reminder_date', $today)
                    ->exists();

                if ($alreadyLogged) {
                    return null;
                }

                return ['assignment_id' => $assignment->id, 'reminder_type' => $type->value];
            })
            ->filter()
            ->values();
    }

    private function reminderTypeFor(RndProjectTaskAssignment $assignment, Carbon $today): ?RndProjectTaskReminderType
    {
        $dueDate = $assignment->task->due_date;

        return match (true) {
            $dueDate->lt($today) => RndProjectTaskReminderType::Overdue,
            $dueDate->eq($today) => RndProjectTaskReminderType::DueToday,
            $dueDate->eq($today->copy()->addDay()) => RndProjectTaskReminderType::DueIn1Day,
            $dueDate->eq($today->copy()->addDays(3)) => RndProjectTaskReminderType::DueIn3Days,
            default => null,
        };
    }
}
