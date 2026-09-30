<?php

namespace App\Notifications;

use App\Enums\RndProjectTaskReminderType;
use App\Models\RndProjectTaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Deadline-proximity reminder dispatched by `SendProjectTaskReminderJob`
 * (docs/rnd-project-task-calendar-prd.md §16).
 */
class ProjectTaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly RndProjectTaskAssignment $assignment,
        public readonly RndProjectTaskReminderType $reminderType,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $task = $this->assignment->task;

        return [
            'type' => 'project_task_reminder',
            'reminder_type' => $this->reminderType->value,
            'rnd_project_task_id' => $task->id,
            'rnd_project_task_assignment_id' => $this->assignment->id,
            'title' => $task->title,
            'due_date' => $task->due_date->toDateString(),
            'message' => "\"{$task->title}\" — {$this->reminderType->getLabel()} ({$task->due_date->format('d M Y')}).",
        ];
    }
}
