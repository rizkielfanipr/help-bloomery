<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use App\Notifications\ProjectTaskApprovedNotification;
use App\Notifications\ProjectTaskRevisionRequestedNotification;
use App\Services\Rnd\ProjectTask\ProjectTaskStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Reviewer decision on one Submitted assignment (docs/rnd-project-task-calendar-prd.md §13).
 * A revision request requires a note; approving does not. Either decision re-syncs the Task's own
 * status via `ProjectTaskStatusService`, and re-activates the PIC's obligation on revision
 * (business rule #8). Ownership/permission is the caller's responsibility
 * (`RndProjectTaskAssignmentPolicy::review`).
 */
class ReviewProjectTaskFollowUpAction
{
    public function __construct(private readonly ProjectTaskStatusService $statusService) {}

    public function execute(RndProjectTaskAssignment $assignment, string $decision, ?string $note, User $reviewer): RndProjectTaskAssignment
    {
        if ($assignment->status !== RndProjectTaskAssignmentStatus::Submitted) {
            throw new RuntimeException('Hanya assignment berstatus Submitted yang dapat direview.');
        }

        if (! in_array($decision, ['approve', 'revision'], true)) {
            throw new RuntimeException('Keputusan review tidak dikenali.');
        }

        if ($decision === 'revision' && trim((string) $note) === '') {
            throw ValidationException::withMessages(['review_note' => 'Catatan wajib diisi ketika meminta revisi.']);
        }

        $assignment = DB::transaction(function () use ($assignment, $decision, $note, $reviewer): RndProjectTaskAssignment {
            $assignment->update([
                'status' => $decision === 'approve'
                    ? RndProjectTaskAssignmentStatus::Approved->value
                    : RndProjectTaskAssignmentStatus::RevisionRequired->value,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'review_note' => $note !== '' ? $note : null,
            ]);

            $this->statusService->sync($assignment->task);

            return $assignment->fresh();
        });

        if ($assignment->user) {
            Notification::send($assignment->user, $decision === 'approve'
                ? new ProjectTaskApprovedNotification($assignment)
                : new ProjectTaskRevisionRequestedNotification($assignment));
        }

        return $assignment;
    }
}
