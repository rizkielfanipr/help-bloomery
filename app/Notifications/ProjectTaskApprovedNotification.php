<?php

namespace App\Notifications;

use App\Models\RndProjectTaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to the PIC when their result is approved (docs/rnd-project-task-calendar-prd.md §16).
 */
class ProjectTaskApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RndProjectTaskAssignment $assignment) {}

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
            'type' => 'project_task_approved',
            'rnd_project_task_id' => $task->id,
            'rnd_project_task_assignment_id' => $this->assignment->id,
            'title' => $task->title,
            'message' => "Hasil Anda untuk \"{$task->title}\" telah disetujui.",
        ];
    }
}
