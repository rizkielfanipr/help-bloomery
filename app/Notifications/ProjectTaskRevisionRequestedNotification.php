<?php

namespace App\Notifications;

use App\Models\RndProjectTaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to the PIC when a reviewer requests revision (docs/rnd-project-task-calendar-prd.md §16).
 */
class ProjectTaskRevisionRequestedNotification extends Notification implements ShouldQueue
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
            'type' => 'project_task_revision_requested',
            'rnd_project_task_id' => $task->id,
            'rnd_project_task_assignment_id' => $this->assignment->id,
            'title' => $task->title,
            'review_note' => $this->assignment->review_note,
            'message' => "Revisi diminta untuk \"{$task->title}\": {$this->assignment->review_note}",
        ];
    }
}
