<?php

namespace App\Notifications;

use App\Models\RndProjectTaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to eligible reviewers when a PIC submits a result for review
 * (docs/rnd-project-task-calendar-prd.md §16).
 */
class ProjectTaskFollowUpSubmittedNotification extends Notification implements ShouldQueue
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
            'type' => 'project_task_follow_up_submitted',
            'rnd_project_task_id' => $task->id,
            'rnd_project_task_assignment_id' => $this->assignment->id,
            'title' => $task->title,
            'pic_name' => $this->assignment->user?->display_username,
            'message' => "{$this->assignment->user?->display_username} mengirim hasil untuk \"{$task->title}\", menunggu review.",
        ];
    }
}
