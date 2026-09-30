<?php

namespace App\Notifications;

use App\Models\RndProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to every PIC who had an active assignment when the Task was cancelled
 * (docs/rnd-project-task-calendar-prd.md §16).
 */
class ProjectTaskCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RndProjectTask $task) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project_task_cancelled',
            'rnd_project_task_id' => $this->task->id,
            'title' => $this->task->title,
            'message' => "Tugas \"{$this->task->title}\" telah dibatalkan.",
        ];
    }
}
