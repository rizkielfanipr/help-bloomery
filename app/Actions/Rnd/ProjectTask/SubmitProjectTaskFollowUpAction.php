<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskFollowUpType;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskFollowUp;
use App\Models\User;
use App\Services\Rnd\ProjectTask\ProjectTaskStatusService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records one append-only follow-up entry from a PIC (docs/rnd-project-task-calendar-prd.md §12).
 * A `Progress` entry never changes the assignment's status (business rule #12) — only a
 * `Submission` moves it to Submitted, which also re-syncs the Task's own status. Ownership is
 * the caller's responsibility (`RndProjectTaskAssignmentPolicy::respond`).
 *
 * @param  array{follow_up_type: string, notes: ?string, estimated_completion_date: ?string, result_attachments: ?array}  $data
 */
class SubmitProjectTaskFollowUpAction
{
    public function __construct(private readonly ProjectTaskStatusService $statusService) {}

    public function execute(RndProjectTaskAssignment $assignment, array $data, User $actor): RndProjectTaskFollowUp
    {
        if ($assignment->status === RndProjectTaskAssignmentStatus::Cancelled) {
            throw new RuntimeException('Assignment ini sudah dibatalkan.');
        }

        $type = RndProjectTaskFollowUpType::from($data['follow_up_type']);

        if ($type === RndProjectTaskFollowUpType::Submission
            && in_array($assignment->status, [RndProjectTaskAssignmentStatus::Submitted, RndProjectTaskAssignmentStatus::Approved], true)) {
            throw new RuntimeException('Tindak lanjut ini sudah dikirim dan menunggu atau sudah selesai direview.');
        }

        return DB::transaction(function () use ($assignment, $data, $actor, $type): RndProjectTaskFollowUp {
            $followUp = $assignment->followUps()->create([
                'submitted_by' => $actor->id,
                'follow_up_type' => $type->value,
                'notes' => $data['notes'] ?? null,
                'estimated_completion_date' => $data['estimated_completion_date'] ?? null,
                'result_attachments' => $data['result_attachments'] ?? null,
            ]);

            if ($assignment->status === RndProjectTaskAssignmentStatus::Assigned) {
                $assignment->update([
                    'status' => RndProjectTaskAssignmentStatus::InProgress->value,
                    'started_at' => $assignment->started_at ?? now(),
                ]);
            }

            if ($type === RndProjectTaskFollowUpType::Submission) {
                $assignment->update([
                    'status' => RndProjectTaskAssignmentStatus::Submitted->value,
                    'submitted_at' => now(),
                ]);
                $this->statusService->sync($assignment->task);
            }

            return $followUp;
        });
    }
}
