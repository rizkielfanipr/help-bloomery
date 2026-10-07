<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Models\RndProjectTask;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * "Copy Task" (docs/rnd-project-checkpoint-calendar-prd.md §14, §17.1): creates a brand-new Task
 * from the descriptive blueprint of an existing one. Only an allowlist is copied — category,
 * description, and priority, with the title and dates confirmed by the user. Status, assignments,
 * follow-ups, reviews, reminders, completion data, and attachments are never carried over; the new
 * Task starts from the regular create workflow with freshly confirmed Branch/PIC pairs. The source
 * Task is never mutated.
 *
 * Copies stay in the source Project on the MVP. Authorization is the caller's responsibility
 * (`RndProjectTaskPolicy::copy`).
 */
class CopyProjectTaskAction
{
    public function __construct(private readonly CreateProjectTaskAction $createProjectTask) {}

    /**
     * @param  array{title: string, assigned_date: string, due_date: string, branches: list<array{branch_id: int, user_id: int}>}  $data
     */
    public function execute(RndProjectTask $source, array $data, User $actor): RndProjectTask
    {
        $project = $source->project()->withTrashed()->firstOrFail();

        if ($project->trashed()) {
            throw ValidationException::withMessages(['project' => 'Project sudah diarsipkan sehingga tidak dapat menerima Task baru.']);
        }

        return $this->createProjectTask->execute($project, [
            ...$this->copyableAttributes($source),
            'title' => trim($data['title']),
            'assigned_date' => $data['assigned_date'],
            'due_date' => $data['due_date'],
            'instruction_attachments' => null,
            'branches' => $data['branches'],
        ], $actor, [
            'copied_from_task_id' => $source->id,
        ]);
    }

    /**
     * Default deadline for a copy: keeps the source Task's duration from the new assign date
     * (§14.3.5). The user may still correct it before saving.
     */
    public function defaultDueDate(RndProjectTask $source, string $newAssignedDate): string
    {
        $durationDays = (int) $source->assigned_date->diffInDays($source->due_date);

        return Carbon::parse($newAssignedDate)->addDays($durationDays)->toDateString();
    }

    /**
     * The explicit allowlist of descriptive fields a copy inherits (§14.1).
     *
     * @return array{task_type: string, description: ?string, priority: string}
     */
    public function copyableAttributes(RndProjectTask $source): array
    {
        return [
            'task_type' => $source->task_type,
            'description' => $source->description,
            'priority' => $source->priority->value,
        ];
    }
}
