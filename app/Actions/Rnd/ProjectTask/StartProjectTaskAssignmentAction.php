<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Models\RndProjectTaskAssignment;
use RuntimeException;

/**
 * The PIC's "Mulai Mengerjakan" action (docs/rnd-project-task-calendar-prd.md §12). Ownership is
 * the caller's responsibility (`RndProjectTaskAssignmentPolicy::respond`).
 */
class StartProjectTaskAssignmentAction
{
    public function execute(RndProjectTaskAssignment $assignment): RndProjectTaskAssignment
    {
        if (! in_array($assignment->status, [RndProjectTaskAssignmentStatus::Assigned, RndProjectTaskAssignmentStatus::RevisionRequired], true)) {
            throw new RuntimeException('Assignment ini tidak dapat dimulai dari status saat ini.');
        }

        $assignment->update([
            'status' => RndProjectTaskAssignmentStatus::InProgress->value,
            'started_at' => $assignment->started_at ?? now(),
        ]);

        return $assignment->fresh();
    }
}
