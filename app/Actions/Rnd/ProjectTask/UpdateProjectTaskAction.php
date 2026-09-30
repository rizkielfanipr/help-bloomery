<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Models\RndProjectTask;
use App\Notifications\ProjectTaskDeadlineChangedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Updates a Task's own descriptive fields (docs/rnd-project-task-calendar-prd.md §11). Branch and
 * PIC changes go through `AssignProjectTaskAction` instead, since adding/replacing an assignment
 * has its own eligibility and history rules.
 *
 * @param  array{title: string, task_type: string, description: ?string, assigned_date: string, due_date: string, priority: string, instruction_attachments: ?array}  $data
 */
class UpdateProjectTaskAction
{
    public function execute(RndProjectTask $task, array $data): RndProjectTask
    {
        if ($task->status->isTerminal()) {
            throw new RuntimeException('Tugas yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        if (strtotime($data['due_date']) < strtotime($data['assigned_date'])) {
            throw ValidationException::withMessages(['due_date' => 'Deadline tidak boleh lebih awal dari tanggal assign.']);
        }

        $previousDueDate = $task->due_date->toDateString();
        $deadlineChanged = $previousDueDate !== $data['due_date'];

        $task->update([
            'title' => $data['title'],
            'task_type' => $data['task_type'],
            'description' => $data['description'] ?? null,
            'assigned_date' => $data['assigned_date'],
            'due_date' => $data['due_date'],
            'priority' => $data['priority'],
            'instruction_attachments' => $data['instruction_attachments'] ?? null,
        ]);

        $task = $task->fresh();

        if ($deadlineChanged) {
            foreach ($task->activeAssignments()->with('user')->get() as $assignment) {
                if ($assignment->user) {
                    Notification::send($assignment->user, new ProjectTaskDeadlineChangedNotification($task, $previousDueDate));
                }
            }
        }

        return $task;
    }
}
