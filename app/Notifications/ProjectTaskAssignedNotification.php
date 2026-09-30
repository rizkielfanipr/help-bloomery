<?php

namespace App\Notifications;

use App\Models\RndProjectTaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent when a PIC is assigned to a Task, whether the assignment is brand new or a replacement
 * for a previous PIC via `AssignProjectTaskAction::reassign()` (docs/rnd-project-task-calendar-prd.md
 * §16 — "assignment baru dibuat" / "PIC berubah").
 */
class ProjectTaskAssignedNotification extends Notification implements ShouldQueue
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
            'type' => 'project_task_assigned',
            'rnd_project_task_id' => $task->id,
            'rnd_project_task_assignment_id' => $this->assignment->id,
            'title' => $task->title,
            'branch_name' => $this->assignment->branch->name,
            'due_date' => $task->due_date->toDateString(),
            'message' => "Anda ditugaskan pada \"{$task->title}\" ({$this->assignment->branch->name}), deadline {$task->due_date->format('d M Y')}.",
        ];
    }
}
