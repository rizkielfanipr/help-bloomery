<?php

namespace App\Services\Rnd\ProjectTask;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\RndProjectTask;

/**
 * Recomputes a Task's own status from its active (non-Cancelled) assignments
 * (docs/rnd-project-task-calendar-prd.md §10, business rule #14-15). Never touches a Task that
 * already reached a terminal status (Completed/Cancelled) — Cancelled only happens through
 * `CancelProjectTaskAction`, and a Completed Task does not reopen automatically.
 */
class ProjectTaskStatusService
{
    public function sync(RndProjectTask $task): RndProjectTask
    {
        if ($task->status->isTerminal()) {
            return $task;
        }

        $task->loadMissing('assignments');
        $active = $task->assignments->reject(
            fn ($assignment): bool => $assignment->status === RndProjectTaskAssignmentStatus::Cancelled
        );

        if ($active->isEmpty()) {
            return $task;
        }

        $newStatus = match (true) {
            $active->every(fn ($assignment): bool => $assignment->status === RndProjectTaskAssignmentStatus::Approved) => RndProjectTaskStatus::Completed,
            $active->contains(fn ($assignment): bool => $assignment->status === RndProjectTaskAssignmentStatus::RevisionRequired) => RndProjectTaskStatus::RevisionRequired,
            $active->every(fn ($assignment): bool => in_array($assignment->status, [
                RndProjectTaskAssignmentStatus::Submitted, RndProjectTaskAssignmentStatus::Approved,
            ], true)) => RndProjectTaskStatus::Submitted,
            $active->contains(fn ($assignment): bool => $assignment->status !== RndProjectTaskAssignmentStatus::Assigned) => RndProjectTaskStatus::InProgress,
            default => RndProjectTaskStatus::Assigned,
        };

        if ($task->status !== $newStatus) {
            $task->update([
                'status' => $newStatus->value,
                'completed_at' => $newStatus === RndProjectTaskStatus::Completed ? now() : null,
            ]);
        }

        return $task->fresh();
    }
}
