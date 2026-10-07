<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Actions\Rnd\ProjectTask\AssignProjectTaskAction;
use App\Actions\Rnd\ProjectTask\CancelProjectTaskAction;
use App\Actions\Rnd\ProjectTask\CreateProjectTaskAction;
use App\Actions\Rnd\ProjectTask\ReviewProjectTaskFollowUpAction;
use App\Actions\Rnd\ProjectTask\StartProjectTaskAssignmentAction;
use App\Actions\Rnd\ProjectTask\SubmitProjectTaskFollowUpAction;
use App\Actions\Rnd\ProjectTask\UpdateProjectTaskAction;
use App\Enums\RndProjectTaskCategory;
use App\Enums\RndProjectTaskPriority;
use App\Enums\RndProjectTaskStatus;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * The existing R&D Task workflow UI — create/edit form, detail, PIC assignment, cancel, progress,
 * submission, and review (docs/rnd-project-task-calendar-prd.md) — shared by the global Kalender
 * (`HasProjectTaskCalendar` on `ListProjects`) and the per-Project calendar
 * (`App\Livewire\RndProjectTaskCalendar`), so neither duplicates validation, authorization, or
 * Action calls (docs/rnd-project-checkpoint-calendar-prd.md §7.4).
 *
 * Hosts narrow it through two hooks: `lockedTaskProject()` pins Task creation to one Project, and
 * `projectTaskQuery()` limits which Tasks may be opened or mutated.
 */
trait InteractsWithProjectTasks
{
    use CopiesProjectTasks;
    use WithFileUploads;

    public bool $taskModalOpen = false;

    public ?int $editingTaskId = null;

    public string $taskProjectId = '';

    public string $taskTitle = '';

    public string $taskCategory = '';

    public string $taskDescription = '';

    public string $taskAssignedDate = '';

    public string $taskDueDate = '';

    public string $taskPriority = 'medium';

    /** @var array<int, array{branch_id: string, user_ids: array<int, int|string>}> */
    public array $taskBranchRows = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $taskInstructionAttachments = [];

    /** @var list<string> */
    public array $existingTaskInstructionAttachments = [];

    public ?int $viewingTaskId = null;

    /**
     * Task detail to reopen once the edit form closes — only one primary modal is shown at a time
     * (docs/ui-consistency-prd.md §10).
     */
    #[Locked]
    public ?int $restoreTaskDetailId = null;

    public string $assignBranchId = '';

    public string $assignUserId = '';

    public string $followUpNotes = '';

    public string $followUpEstimatedDate = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $followUpAttachments = [];

    public string $reviewNote = '';

    /**
     * Per-request option caches (non-public, so Livewire never serializes them) — a form may render
     * many Branch/PIC pickers at once, e.g. one per template checkpoint.
     *
     * @var array{branches?: EloquentCollection<int, Branch>, pics?: array<int, array<int, string>>}
     */
    protected array $taskOptionCache = [];

    /**
     * The Project new Tasks are pinned to, or null when the user picks one (global Kalender).
     */
    protected function lockedTaskProject(): ?RndProject
    {
        return null;
    }

    /**
     * Base query for every Task this host may open or mutate by ID.
     *
     * @return Builder<RndProjectTask>
     */
    protected function projectTaskQuery(): Builder
    {
        return RndProjectTask::query();
    }

    protected function findProjectTask(int $taskId): RndProjectTask
    {
        return $this->projectTaskQuery()->findOrFail($taskId);
    }

    protected function canCreateTask(): bool
    {
        $lockedProject = $this->lockedTaskProject();

        return $lockedProject === null
            ? auth()->user()->can('create', RndProjectTask::class)
            : auth()->user()->can('createForProject', [RndProjectTask::class, $lockedProject]);
    }

    /** @return EloquentCollection<int, RndProject> */
    public function taskFilterProjects(): EloquentCollection
    {
        return RndProject::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return EloquentCollection<int, Branch> */
    public function taskFilterBranches(): EloquentCollection
    {
        return $this->taskOptionCache['branches'] ??= Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function openTaskModal(?string $date = null): void
    {
        abort_unless($this->canCreateTask(), 403);
        $this->resetValidation();
        $this->editingTaskId = null;
        $this->taskProjectId = (string) ($this->lockedTaskProject()?->id ?? '');
        $this->taskTitle = '';
        $this->taskCategory = '';
        $this->taskDescription = '';
        $this->taskAssignedDate = $date ?: today()->toDateString();
        $this->taskDueDate = '';
        $this->taskPriority = 'medium';
        $this->taskBranchRows = [$this->emptyBranchRow()];
        $this->taskInstructionAttachments = [];
        $this->existingTaskInstructionAttachments = [];
        $this->restoreTaskDetailId = null;
        $this->taskModalOpen = true;
    }

    public function openEditTaskModal(int $taskId): void
    {
        $task = $this->findProjectTask($taskId);
        abort_unless(auth()->user()->can('update', $task), 403);
        $this->resetValidation();
        $this->editingTaskId = $task->id;
        $this->taskProjectId = (string) $task->rnd_project_id;
        $this->taskTitle = $task->title;
        $this->taskCategory = $task->task_type;
        $this->taskDescription = (string) $task->description;
        $this->taskAssignedDate = $task->assigned_date->toDateString();
        $this->taskDueDate = $task->due_date->toDateString();
        $this->taskPriority = $task->priority->value;
        $this->taskBranchRows = [];
        $this->taskInstructionAttachments = [];
        $this->existingTaskInstructionAttachments = $task->instruction_attachments ?? [];
        $this->restoreTaskDetailId = $this->viewingTaskId;
        $this->viewingTaskId = null;
        $this->taskModalOpen = true;
    }

    public function removeInstructionAttachment(int $taskId, int $index): void
    {
        $task = $this->findProjectTask($taskId);
        abort_unless(auth()->user()->can('update', $task), 403);

        $attachments = $task->instruction_attachments ?? [];
        abort_unless(array_key_exists($index, $attachments), 404);

        $path = $attachments[$index];
        unset($attachments[$index]);
        $attachments = array_values($attachments);
        $task->update(['instruction_attachments' => $attachments ?: null]);
        Storage::disk(RndProjectTask::attachmentDisk())->delete($path);
        $this->existingTaskInstructionAttachments = $attachments;
    }

    public function closeTaskModal(): void
    {
        $this->resetValidation();
        $this->taskModalOpen = false;
        $this->taskInstructionAttachments = [];
        $this->existingTaskInstructionAttachments = [];
        $this->restoreTaskDetailAfterForm();
    }

    /**
     * Returns to the Task detail the edit form was opened from, re-authorizing on the way.
     */
    protected function restoreTaskDetailAfterForm(): void
    {
        $taskId = $this->restoreTaskDetailId;
        $this->restoreTaskDetailId = null;

        if ($taskId !== null) {
            $this->openTaskDetail($taskId);
        }
    }

    public function addTaskBranchRow(): void
    {
        $this->addBranchRow('taskBranchRows');
    }

    public function removeTaskBranchRow(int $index): void
    {
        $this->removeBranchRow('taskBranchRows', $index);
    }

    /**
     * Shared by the create, copy, and apply-template forms so all of them render one Branch/PIC
     * picker. The path is allowlisted because it arrives from the browser.
     */
    public function addBranchRow(string $path): void
    {
        abort_unless($this->allowsBranchRowPath($path), 404);

        $rows = data_get($this, $path);
        $rows[] = $this->emptyBranchRow();
        data_set($this, $path, $rows);
    }

    public function removeBranchRow(string $path, int $index): void
    {
        abort_unless($this->allowsBranchRowPath($path), 404);

        $rows = data_get($this, $path);
        unset($rows[$index]);
        data_set($this, $path, array_values($rows));
    }

    protected function allowsBranchRowPath(string $path): bool
    {
        return in_array($path, ['taskBranchRows', 'copyBranchRows'], true);
    }

    /** @return array{branch_id: string, user_ids: array<int, int|string>} */
    protected function emptyBranchRow(): array
    {
        return ['branch_id' => '', 'user_ids' => []];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function branchRowRules(string $property): array
    {
        return [
            $property => ['required', 'array', 'min:1'],
            "{$property}.*.branch_id" => ['required', 'integer', 'exists:branches,id'],
            "{$property}.*.user_ids" => ['required', 'array', 'min:1'],
            "{$property}.*.user_ids.*" => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Flattens the picker rows into the `(branch_id, user_id)` pairs `CreateProjectTaskAction`
     * expects; eligibility itself is re-checked by the Action.
     *
     * @param  array<int, array{branch_id: int|string, user_ids: array<int, int|string>}>  $rows
     * @return list<array{branch_id: int, user_id: int}>
     */
    protected function branchAssignmentsFromRows(array $rows): array
    {
        $branches = [];

        foreach ($rows as $row) {
            foreach ($row['user_ids'] as $userId) {
                $branches[] = ['branch_id' => (int) $row['branch_id'], 'user_id' => (int) $userId];
            }
        }

        return $branches;
    }

    /** @return array<int, string> */
    public function eligiblePicsForBranch(int|string|null $branchId): array
    {
        if ($branchId === '' || $branchId === null) {
            return [];
        }

        return $this->taskOptionCache['pics'][(int) $branchId] ??= app(ProjectTaskAssigneeResolver::class)
            ->eligibleUsersForBranch((int) $branchId)
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->display_username])
            ->all();
    }

    public function saveTask(): void
    {
        $isEditing = $this->editingTaskId !== null;
        $task = null;
        $lockedProject = $this->lockedTaskProject();

        if ($isEditing) {
            $task = $this->findProjectTask($this->editingTaskId);
            abort_unless(auth()->user()->can('update', $task), 403);
        } else {
            abort_unless($this->canCreateTask(), 403);
        }

        $rules = [
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskCategory' => ['required', Rule::in(array_column(RndProjectTaskCategory::cases(), 'value'))],
            'taskDescription' => ['nullable', 'string'],
            'taskAssignedDate' => ['required', 'date'],
            'taskDueDate' => ['required', 'date', 'after_or_equal:taskAssignedDate'],
            'taskPriority' => ['required', Rule::in(array_column(RndProjectTaskPriority::cases(), 'value'))],
        ];

        $rules['taskInstructionAttachments'] = ['array', 'max:5'];
        $rules['taskInstructionAttachments.*'] = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'];

        if (! $isEditing) {
            if ($lockedProject === null) {
                $rules['taskProjectId'] = ['required', 'integer', 'exists:rnd_projects,id'];
            }

            $rules += $this->branchRowRules('taskBranchRows');
        }

        $validated = $this->validate($rules);

        $existingAttachments = $task?->instruction_attachments ?? [];

        if (count($existingAttachments) + count($this->taskInstructionAttachments) > 5) {
            throw ValidationException::withMessages([
                'taskInstructionAttachments' => 'Total Attachment Task maksimal 5 file.',
            ]);
        }

        $data = [
            'title' => $validated['taskTitle'],
            'task_type' => $validated['taskCategory'],
            'description' => filled($validated['taskDescription'] ?? null) ? $validated['taskDescription'] : null,
            'assigned_date' => $validated['taskAssignedDate'],
            'due_date' => $validated['taskDueDate'],
            'priority' => $validated['taskPriority'],
            'instruction_attachments' => $existingAttachments ?: null,
        ];

        if ($isEditing) {
            $task = app(UpdateProjectTaskAction::class)->execute($task, $data);
        } else {
            $project = $lockedProject ?? RndProject::query()->findOrFail($validated['taskProjectId']);
            abort_unless(auth()->user()->can('createForProject', [RndProjectTask::class, $project]), 403);

            $task = app(CreateProjectTaskAction::class)->execute(
                $project,
                $data + ['branches' => $this->branchAssignmentsFromRows($validated['taskBranchRows'])],
                auth()->user(),
            );
        }

        if ($this->taskInstructionAttachments !== []) {
            $newPaths = [];
            foreach ($this->taskInstructionAttachments as $file) {
                $newPaths[] = $file->store("rnd/project-tasks/{$task->id}/instructions", RndProjectTask::attachmentDisk());
            }
            $task->update(['instruction_attachments' => array_merge($task->instruction_attachments ?? [], $newPaths)]);
        }

        $this->taskModalOpen = false;
        $this->taskInstructionAttachments = [];
        $this->existingTaskInstructionAttachments = [];
        $this->restoreTaskDetailAfterForm();
        Notification::make()->title('Tugas berhasil disimpan')->success()->send();
    }

    public function openTaskDetail(int $taskId): void
    {
        $task = $this->findProjectTask($taskId);
        abort_unless(auth()->user()->can('view', $task), 403);
        $this->viewingTaskId = $taskId;
        $this->assignBranchId = '';
        $this->assignUserId = '';
    }

    public function closeTaskDetail(): void
    {
        $this->viewingTaskId = null;
    }

    public function viewingTask(): ?RndProjectTask
    {
        if ($this->viewingTaskId === null) {
            return null;
        }

        return $this->projectTaskQuery()
            ->with(['project' => fn ($query) => $query->withTrashed(), 'branches', 'assignments.user', 'assignments.branch', 'assignments.reviewedBy', 'assignments.followUps.submittedBy', 'creator', 'copiedFromTask', 'templateApplication'])
            ->find($this->viewingTaskId);
    }

    public function assignTaskPic(): void
    {
        $task = $this->findProjectTask($this->viewingTaskId);
        abort_unless(auth()->user()->can('assign', $task), 403);

        $validated = $this->validate([
            'assignBranchId' => ['required', 'integer', 'exists:branches,id'],
            'assignUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        app(AssignProjectTaskAction::class)->execute($task, (int) $validated['assignBranchId'], (int) $validated['assignUserId']);

        $this->assignBranchId = '';
        $this->assignUserId = '';
        Notification::make()->title('PIC berhasil ditambahkan')->success()->send();
    }

    public function cancelTask(int $taskId): void
    {
        $task = $this->findProjectTask($taskId);
        abort_unless(auth()->user()->can('cancel', $task), 403);

        app(CancelProjectTaskAction::class)->execute($task);

        Notification::make()->title('Tugas dibatalkan')->success()->send();
    }

    /** @return EloquentCollection<int, RndProjectTaskAssignment> */
    public function myAssignmentsForTask(RndProjectTask $task): EloquentCollection
    {
        return $task->assignments->where('user_id', auth()->id())->values();
    }

    public function startAssignment(int $assignmentId): void
    {
        $assignment = RndProjectTaskAssignment::query()->findOrFail($assignmentId);
        abort_unless(auth()->user()->can('respond', $assignment), 403);

        app(StartProjectTaskAssignmentAction::class)->execute($assignment);

        Notification::make()->title('Tugas dimulai')->success()->send();
    }

    public function removeFollowUpAttachment(int $index): void
    {
        unset($this->followUpAttachments[$index]);
        $this->followUpAttachments = array_values($this->followUpAttachments);
    }

    public function saveFollowUp(int $assignmentId, string $type): void
    {
        $assignment = RndProjectTaskAssignment::query()->findOrFail($assignmentId);
        abort_unless(auth()->user()->can('respond', $assignment), 403);

        $this->validate([
            'followUpNotes' => ['nullable', 'string', 'max:2000'],
            'followUpEstimatedDate' => ['nullable', 'date'],
            'followUpAttachments' => ['array', 'max:5'],
            'followUpAttachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $paths = [];
        foreach ($this->followUpAttachments as $file) {
            $paths[] = $file->store(
                "rnd/project-tasks/{$assignment->rnd_project_task_id}/assignments/{$assignment->id}/results",
                RndProjectTask::attachmentDisk()
            );
        }

        try {
            app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
                'follow_up_type' => $type,
                'notes' => $this->followUpNotes !== '' ? $this->followUpNotes : null,
                'estimated_completion_date' => $this->followUpEstimatedDate !== '' ? $this->followUpEstimatedDate : null,
                'result_attachments' => $paths !== [] ? $paths : null,
            ], auth()->user());
        } catch (RuntimeException $exception) {
            Notification::make()->title('Gagal menyimpan tindak lanjut')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->followUpNotes = '';
        $this->followUpEstimatedDate = '';
        $this->followUpAttachments = [];

        Notification::make()
            ->title($type === 'submission' ? 'Tindak lanjut berhasil dikirim' : 'Progress berhasil disimpan')
            ->success()
            ->send();
    }

    public function approveFollowUp(int $assignmentId): void
    {
        $assignment = RndProjectTaskAssignment::query()->findOrFail($assignmentId);
        abort_unless(auth()->user()->can('review', $assignment), 403);

        try {
            app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'approve', null, auth()->user());
        } catch (RuntimeException $exception) {
            Notification::make()->title('Gagal menyetujui')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Hasil disetujui')->success()->send();
    }

    public function requestRevision(int $assignmentId): void
    {
        $assignment = RndProjectTaskAssignment::query()->findOrFail($assignmentId);
        abort_unless(auth()->user()->can('review', $assignment), 403);

        $this->validate(['reviewNote' => ['required', 'string', 'max:2000']]);

        try {
            app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'revision', $this->reviewNote, auth()->user());
        } catch (RuntimeException $exception) {
            Notification::make()->title('Gagal meminta revisi')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->reviewNote = '';
        Notification::make()->title('Revisi diminta')->success()->send();
    }

    /** @return array<string, string> */
    public function taskCategoryOptions(): array
    {
        return collect(RndProjectTaskCategory::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->all();
    }

    /** @return array<string, string> */
    public function taskPriorityOptions(): array
    {
        return collect(RndProjectTaskPriority::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->all();
    }

    /** @return array<string, string> */
    public function taskStatusOptions(): array
    {
        return collect(RndProjectTaskStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->all();
    }
}
