<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\RndProjectTask;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a Task and every still-active assignment under it (docs/rnd-project-task-calendar-prd.md
 * §10, business rule #16). Cancelling stops reminders without deleting any history — no rows are
 * removed, only their status changes.
 */
class CancelProjectTaskAction
{
    public function execute(RndProjectTask $task): RndProjectTask
    {
        if ($task->status->isTerminal()) {
            throw new RuntimeException('Tugas ini sudah selesai atau dibatalkan.');
        }

        return DB::transaction(function () use ($task): RndProjectTask {
            $task->assignments()
                ->whereNot('status', RndProjectTaskAssignmentStatus::Cancelled->value)
                ->update(['status' => RndProjectTaskAssignmentStatus::Cancelled->value]);

            $task->update(['status' => RndProjectTaskStatus::Cancelled->value]);

            return $task->fresh('assignments');
        });
    }
}
