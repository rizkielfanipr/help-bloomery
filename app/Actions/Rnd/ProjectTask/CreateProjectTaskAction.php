<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\User;
use App\Notifications\ProjectTaskAssignedNotification;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Creates a Task and shares it in one step (docs/rnd-project-task-calendar-prd.md §11, §10.2):
 * the Tambah Tugas form already collects Branch and PIC per Branch up front, so a Task created
 * through it always satisfies the "Draft hanya dapat dibagikan jika..." completeness rule and
 * goes straight to Assigned with one `RndProjectTaskAssignment` row per (Branch, PIC) pair. A
 * Branch may have more than one PIC (§11: "minimal satu pengguna aktif per Branch").
 *
 * Authorization is the caller's responsibility (`RndProjectTaskPolicy::create`) — this Action
 * assumes it has already been granted.
 *
 * @param  array{title: string, task_type: string, description: ?string, assigned_date: string, due_date: string, priority: string, instruction_attachments: ?array, branches: list<array{branch_id: int, user_id: int}>}  $data
 */
class CreateProjectTaskAction
{
    public function __construct(private readonly ProjectTaskAssigneeResolver $assigneeResolver) {}

    public function execute(RndProject $project, array $data, User $actor): RndProjectTask
    {
        if ($data['branches'] === []) {
            throw ValidationException::withMessages(['branches' => 'Pilih minimal satu Branch beserta PIC-nya.']);
        }

        if (strtotime($data['due_date']) < strtotime($data['assigned_date'])) {
            throw ValidationException::withMessages(['due_date' => 'Deadline tidak boleh lebih awal dari tanggal assign.']);
        }

        $seenPairs = [];

        foreach ($data['branches'] as $branchAssignment) {
            $pic = User::query()->findOrFail($branchAssignment['user_id']);

            if (! $this->assigneeResolver->isEligible($pic, $branchAssignment['branch_id'])) {
                throw ValidationException::withMessages([
                    'branches' => 'PIC yang dipilih harus pengguna aktif yang dapat mengakses Branch tersebut.',
                ]);
            }

            $pairKey = $branchAssignment['branch_id'].':'.$branchAssignment['user_id'];
            if (isset($seenPairs[$pairKey])) {
                throw ValidationException::withMessages([
                    'branches' => 'PIC yang sama tidak boleh dipilih dua kali untuk Branch yang sama.',
                ]);
            }
            $seenPairs[$pairKey] = true;
        }

        $task = DB::transaction(function () use ($project, $data, $actor): RndProjectTask {
            $task = $project->tasks()->create([
                'title' => $data['title'],
                'task_type' => $data['task_type'],
                'description' => $data['description'] ?? null,
                'assigned_date' => $data['assigned_date'],
                'due_date' => $data['due_date'],
                'priority' => $data['priority'],
                'status' => RndProjectTaskStatus::Assigned->value,
                'instruction_attachments' => $data['instruction_attachments'] ?? null,
                'created_by' => $actor->id,
            ]);

            $task->branches()->attach(collect($data['branches'])->pluck('branch_id')->unique()->all());

            foreach ($data['branches'] as $branchAssignment) {
                $task->assignments()->create([
                    'branch_id' => $branchAssignment['branch_id'],
                    'user_id' => $branchAssignment['user_id'],
                    'status' => RndProjectTaskAssignmentStatus::Assigned->value,
                    'assigned_at' => now(),
                ]);
            }

            return $task->fresh(['branches', 'assignments']);
        });

        foreach ($task->assignments as $assignment) {
            if ($assignment->user) {
                Notification::send($assignment->user, new ProjectTaskAssignedNotification($assignment));
            }
        }

        return $task;
    }
}
