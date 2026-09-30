<?php

namespace App\Notifications;

use App\Models\RndProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to every active PIC when a Task's deadline changes via `UpdateProjectTaskAction`
 * (docs/rnd-project-task-calendar-prd.md §16).
 */
class ProjectTaskDeadlineChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RndProjectTask $task, public readonly string $previousDueDate) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project_task_deadline_changed',
            'rnd_project_task_id' => $this->task->id,
            'title' => $this->task->title,
            'previous_due_date' => $this->previousDueDate,
            'due_date' => $this->task->due_date->toDateString(),
            'message' => "Deadline \"{$this->task->title}\" berubah menjadi {$this->task->due_date->format('d M Y')}.",
        ];
    }
}
