<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use App\Notifications\ProjectTaskAssignedNotification;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Adds or replaces a PIC assignment on an existing Task (docs/rnd-project-task-calendar-prd.md
 * §6, §11). Authorization is the caller's responsibility (`RndProjectTaskPolicy::assign`).
 */
class AssignProjectTaskAction
{
    public function __construct(private readonly ProjectTaskAssigneeResolver $assigneeResolver) {}

    /**
     * Adds a new PIC assignment for a Branch, attaching the Branch first if the Task doesn't
     * already target it. A Branch may have more than one PIC.
     */
    public function execute(RndProjectTask $task, int $branchId, int $userId): RndProjectTaskAssignment
    {
        $this->guardAssignable($task);
        $this->guardEligible($userId, $branchId);

        $assignment = DB::transaction(function () use ($task, $branchId, $userId): RndProjectTaskAssignment {
            $task->branches()->syncWithoutDetaching([$branchId]);

            return $task->assignments()->create([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'status' => RndProjectTaskAssignmentStatus::Assigned->value,
                'assigned_at' => now(),
            ]);
        });

        Notification::send($assignment->user, new ProjectTaskAssignedNotification($assignment));

        return $assignment;
    }

    /**
     * Replaces one assignment with a new PIC — e.g. the previous PIC was deactivated
     * (business rule #7). The old assignment is marked Cancelled, never deleted, so its history
     * (follow-ups, reminders) survives.
     */
    public function reassign(RndProjectTaskAssignment $assignment, int $newUserId): RndProjectTaskAssignment
    {
        $this->guardAssignable($assignment->task);
        $this->guardEligible($newUserId, $assignment->branch_id);

        $newAssignment = DB::transaction(function () use ($assignment, $newUserId): RndProjectTaskAssignment {
            $assignment->update(['status' => RndProjectTaskAssignmentStatus::Cancelled->value]);

            return $assignment->task->assignments()->create([
                'branch_id' => $assignment->branch_id,
                'user_id' => $newUserId,
                'status' => RndProjectTaskAssignmentStatus::Assigned->value,
                'assigned_at' => now(),
            ]);
        });

        Notification::send($newAssignment->user, new ProjectTaskAssignedNotification($newAssignment));

        return $newAssignment;
    }

    private function guardAssignable(RndProjectTask $task): void
    {
        if ($task->status->isTerminal()) {
            throw new RuntimeException('Tugas yang sudah selesai atau dibatalkan tidak dapat di-assign ulang.');
        }
    }

    private function guardEligible(int $userId, int $branchId): void
    {
        $user = User::query()->findOrFail($userId);

        if (! $this->assigneeResolver->isEligible($user, $branchId)) {
            throw ValidationException::withMessages([
                'user_id' => 'PIC yang dipilih harus pengguna aktif yang dapat mengakses Branch tersebut.',
            ]);
        }
    }
}
