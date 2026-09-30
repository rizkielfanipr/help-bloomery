<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * `rnd_project_task_reminders.reminder_type` — deadline-proximity reminders that need the
 * `assignment_id + reminder_type + reminder_date` dedup key (docs/rnd-project-task-calendar-prd.md
 * §16). Event-triggered notifications (new assignment, PIC changed, revision requested, follow-up
 * submitted, approved, cancelled) are sent directly from Actions and do not use this enum or the
 * reminders table, since they are one-off and need no daily dedup.
 */
enum RndProjectTaskReminderType: string implements HasLabel
{
    case DueIn3Days = 'due_in_3_days';
    case DueIn1Day = 'due_in_1_day';
    case DueToday = 'due_today';
    case Overdue = 'overdue';

    public function getLabel(): string
    {
        return match ($this) {
            self::DueIn3Days => 'Deadline dalam 3 hari',
            self::DueIn1Day => 'Deadline dalam 1 hari',
            self::DueToday => 'Deadline hari ini',
            self::Overdue => 'Overdue',
        };
    }
}
