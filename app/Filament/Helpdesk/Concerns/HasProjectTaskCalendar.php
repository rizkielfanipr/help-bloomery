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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Merged Kalender mode for the Project index (docs/rnd-project-task-calendar-prd.md §14, Phase 2).
 * Mirrors the existing release-calendar month-grid mechanics in `ListProjects::calendar()`, and
 * additionally overlays each week with that same month's project release dates (`taskCalendar()`'s
 * `projects` segments), so a single toggle shows both release dates and Task deadlines. The task
 * query itself stays scoped to the visible date range and to the user's branch access in SQL, per
 * §19 ("Kalender hanya mengambil data dalam rentang tanggal yang sedang ditampilkan") — the legacy
 * `ListProjects::calendar()` method (used standalone for users without Task permissions) keeps its
 * own looser, in-memory-filtered behavior unchanged.
 */
trait HasProjectTaskCalendar
{
    use WithFileUploads;

    public string $taskCalendarMonth = '';

    public string $taskFilterProjectId = '';

    public string $taskFilterBranchId = '';

    public string $taskFilterCategory = '';

    public string $taskFilterStatus = '';

    public string $taskFilterPicId = '';

    public bool $taskFilterMineOnly = false;

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

    public string $assignBranchId = '';

    public string $assignUserId = '';

    public string $followUpNotes = '';

    public string $followUpEstimatedDate = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $followUpAttachments = [];

    public string $reviewNote = '';

    public function showProjectTasks(): void
    {
        $this->projectView = 'tasks';
        $this->ensureTaskCalendarMonthIsSet();
    }

    public function previousTaskCalendarMonth(): void
    {
        $this->taskCalendarMonth = $this->selectedTaskCalendarMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextTaskCalendarMonth(): void
    {
        $this->taskCalendarMonth = $this->selectedTaskCalendarMonth()->addMonthNoOverflow()->format('Y-m');
    }

    public function currentTaskCalendarMonth(): void
    {
        $this->taskCalendarMonth = today()->format('Y-m');
    }

    public function resetTaskFilters(): void
    {
        $this->taskFilterProjectId = '';
        $this->taskFilterBranchId = '';
        $this->taskFilterCategory = '';
        $this->taskFilterStatus = '';
        $this->taskFilterPicId = '';
        $this->taskFilterMineOnly = false;
    }

    /** @return EloquentCollection<int, RndProjectTask> */
    public function taskCalendarTasks(): EloquentCollection
    {
        $month = $this->selectedTaskCalendarMonth();
        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $user = auth()->user();

        return RndProjectTask::query()
            ->with(['project', 'branches', 'assignments.user'])
            ->whereBetween('due_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
            ->when($this->taskFilterProjectId !== '', fn ($query) => $query->where('rnd_project_id', $this->taskFilterProjectId))
            ->when($this->taskFilterCategory !== '', fn ($query) => $query->where('task_type', $this->taskFilterCategory))
            ->when($this->taskFilterStatus !== '', fn ($query) => $query->where('status', $this->taskFilterStatus))
            ->when($this->taskFilterBranchId !== '', fn ($query) => $query->whereHas(
                'branches', fn ($query) => $query->where('branches.id', $this->taskFilterBranchId)
            ))
            ->when($this->taskFilterPicId !== '', fn ($query) => $query->whereHas(
                'assignments', fn ($query) => $query->where('user_id', $this->taskFilterPicId)
            ))
            ->when($this->taskFilterMineOnly, fn ($query) => $query->whereHas(
                'assignments', fn ($query) => $query->where('user_id', $user->id)
            ))
            ->when(
                ! $user->canAccessAllBranches() && ! $user->can('view all branch rnd project tasks'),
                fn ($query) => $query->where(function ($query) use ($user): void {
                    $query->whereHas('branches', fn ($query) => $query->whereIn('branches.id', $user->accessibleBranchIds()))
                        ->orWhereHas('assignments', fn ($query) => $query->where('user_id', $user->id));
                })
            )
            ->get();
    }

    /**
     * @return array{monthLabel: string, weeks: array<int, array{dates: array<int, array{date: Carbon, isCurrentMonth: bool, isToday: bool}>, tasks: array<int, array{task: RndProjectTask, dayColumn: int}>, projects: array<int, array{project: RndProject, dayColumn: int}>}>}
     */
    public function taskCalendar(): array
    {
        $month = $this->selectedTaskCalendarMonth();
        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $tasks = $this->taskCalendarTasks();
        $releaseProjects = $this->projects()
            ->filter(fn (RndProject $project): bool => $project->end_date->betweenIncluded($calendarStart, $calendarEnd));
        $weeks = [];

        for ($weekStart = $calendarStart->copy(); $weekStart->lte($calendarEnd); $weekStart->addWeek()) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            $dates = [];

            for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
                $dates[] = [
                    'date' => $date->copy(),
                    'isCurrentMonth' => $date->month === $month->month,
                    'isToday' => $date->isToday(),
                ];
            }

            $segments = $tasks
                ->filter(fn (RndProjectTask $task): bool => $task->due_date->betweenIncluded($weekStart, $weekEnd))
                ->sortBy(fn (RndProjectTask $task): string => $task->due_date->format('Y-m-d').'|'.str_pad((string) $task->id, 10, '0', STR_PAD_LEFT))
                ->map(fn (RndProjectTask $task): array => [
                    'task' => $task,
                    'dayColumn' => (int) $weekStart->diffInDays($task->due_date) + 1,
                ])
                ->values()
                ->all();

            $releaseSegments = $releaseProjects
                ->filter(fn (RndProject $project): bool => $project->end_date->betweenIncluded($weekStart, $weekEnd))
                ->sortBy(fn (RndProject $project): string => $project->end_date->format('Y-m-d').'|'.str_pad((string) $project->id, 10, '0', STR_PAD_LEFT))
                ->map(fn (RndProject $project): array => [
                    'project' => $project,
                    'dayColumn' => (int) $weekStart->diffInDays($project->end_date) + 1,
                ])
                ->values()
                ->all();

            $weeks[] = ['dates' => $dates, 'tasks' => $segments, 'projects' => $releaseSegments];
        }

        return [
            'monthLabel' => $month->translatedFormat('F Y'),
            'weeks' => $weeks,
        ];
    }

    private function ensureTaskCalendarMonthIsSet(): void
    {
        if ($this->taskCalendarMonth === '') {
            $this->taskCalendarMonth = today()->format('Y-m');
        }
    }

    private function selectedTaskCalendarMonth(): Carbon
    {
        $this->ensureTaskCalendarMonthIsSet();

        return Carbon::createFromFormat('Y-m-d', $this->taskCalendarMonth.'-01')->startOfDay();
    }

    /** @return EloquentCollection<int, RndProject> */
    public function taskFilterProjects(): EloquentCollection
    {
        return RndProject::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return EloquentCollection<int, Branch> */
    public function taskFilterBranches(): EloquentCollection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** @return EloquentCollection<int, User> */
    public function taskFilterPics(): EloquentCollection
    {
        return User::query()
            ->whereHas('projectTaskAssignments')
            ->orderBy('username')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);
    }

    public function openTaskModal(?string $date = null): void
    {
        abort_unless(auth()->user()->can('create', RndProjectTask::class), 403);
        $this->resetValidation();
        $this->editingTaskId = null;
        $this->taskProjectId = '';
        $this->taskTitle = '';
        $this->taskCategory = '';
        $this->taskDescription = '';
        $this->taskAssignedDate = $date ?: today()->toDateString();
        $this->taskDueDate = '';
        $this->taskPriority = 'medium';
        $this->taskBranchRows = [['branch_id' => '', 'user_ids' => []]];
        $this->taskInstructionAttachments = [];
        $this->existingTaskInstructionAttachments = [];
        $this->taskModalOpen = true;
    }

    public function openEditTaskModal(int $taskId): void
    {
        $task = RndProjectTask::query()->findOrFail($taskId);
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
        $this->taskModalOpen = true;
    }

    public function removeInstructionAttachment(int $taskId, int $index): void
    {
        $task = RndProjectTask::query()->findOrFail($taskId);
        abort_unless(auth()->user()->can('update', $task), 403);

        $attachments = $task->instruction_attachments ?? [];
        abort_unless(array_key_exists($index, $attachments), 404);

        $path = $attachments[$index];
        unset($attachments[$index]);
        $attachments = array_values($attachments);
        $task->update(['instruction_attachments' => $attachments ?: null]);
        Storage::disk('b2')->delete($path);
        $this->existingTaskInstructionAttachments = $attachments;
    }

    public function closeTaskModal(): void
    {
        $this->resetValidation();
        $this->taskModalOpen = false;
        $this->taskInstructionAttachments = [];
        $this->existingTaskInstructionAttachments = [];
    }

    public function addTaskBranchRow(): void
    {
        $this->taskBranchRows[] = ['branch_id' => '', 'user_ids' => []];
    }

    public function removeTaskBranchRow(int $index): void
    {
        unset($this->taskBranchRows[$index]);
        $this->taskBranchRows = array_values($this->taskBranchRows);
    }

    /** @return array<int, string> */
    public function eligiblePicsForBranch(int|string $branchId): array
    {
        if ($branchId === '' || $branchId === null) {
            return [];
        }

        return app(ProjectTaskAssigneeResolver::class)
            ->eligibleUsersForBranch((int) $branchId)
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->display_username])
            ->all();
    }

    public function saveTask(): void
    {
        $isEditing = $this->editingTaskId !== null;
        $task = null;

        if ($isEditing) {
            $task = RndProjectTask::query()->findOrFail($this->editingTaskId);
            abort_unless(auth()->user()->can('update', $task), 403);
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
            $rules['taskProjectId'] = ['required', 'integer', 'exists:rnd_projects,id'];
            $rules['taskBranchRows'] = ['required', 'array', 'min:1'];
            $rules['taskBranchRows.*.branch_id'] = ['required', 'integer', 'exists:branches,id'];
            $rules['taskBranchRows.*.user_ids'] = ['required', 'array', 'min:1'];
            $rules['taskBranchRows.*.user_ids.*'] = ['required', 'integer', 'exists:users,id'];
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
            abort_unless(auth()->user()->can('create', RndProjectTask::class), 403);
            $project = RndProject::query()->findOrFail($validated['taskProjectId']);

            $branches = [];
            foreach ($validated['taskBranchRows'] as $row) {
                foreach ($row['user_ids'] as $userId) {
                    $branches[] = ['branch_id' => (int) $row['branch_id'], 'user_id' => (int) $userId];
                }
            }

            $task = app(CreateProjectTaskAction::class)->execute($project, $data + ['branches' => $branches], auth()->user());
        }

        if ($this->taskInstructionAttachments !== []) {
            $newPaths = [];
            foreach ($this->taskInstructionAttachments as $file) {
                $newPaths[] = $file->store("rnd/project-tasks/{$task->id}/instructions", 'b2');
            }
            $task->update(['instruction_attachments' => array_merge($task->instruction_attachments ?? [], $newPaths)]);
        }

        $this->taskModalOpen = false;
        $this->taskInstructionAttachments = [];
        $this->existingTaskInstructionAttachments = [];
        Notification::make()->title('Tugas berhasil disimpan')->success()->send();
    }

    public function openTaskDetail(int $taskId): void
    {
        $task = RndProjectTask::query()->findOrFail($taskId);
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

        return RndProjectTask::query()
            ->with(['project', 'branches', 'assignments.user', 'assignments.branch', 'assignments.followUps', 'creator'])
            ->find($this->viewingTaskId);
    }

    public function assignTaskPic(): void
    {
        $task = RndProjectTask::query()->findOrFail($this->viewingTaskId);
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
        $task = RndProjectTask::query()->findOrFail($taskId);
        abort_unless(auth()->user()->can('cancel', $task), 403);

        app(CancelProjectTaskAction::class)->execute($task);

        Notification::make()->title('Tugas dibatalkan')->success()->send();
    }

    /** @return EloquentCollection<int, RndProjectTaskAssignment> */
    public function myAssignmentsForTask(RndProjectTask $task): EloquentCollection
    {
        return $task->assignments->where('user_id', auth()->id())->values();
    }

    /**
     * "Tugas Saya" sidebar next to the merged Kalender (docs/rnd-project-task-calendar-prd.md §15)
     * — the current user's own non-Cancelled assignments, ordered overdue first, then deadline
     * today, then Urgent/High priority, then nearest deadline. Mirrors the Dashboard's "Tugas yang
     * Perlu Ditindaklanjuti" ordering but also surfaces Submitted assignments awaiting review.
     *
     * @return EloquentCollection<int, RndProjectTaskAssignment>
     */
    public function myOpenTaskAssignments(): EloquentCollection
    {
        return RndProjectTaskAssignment::query()
            ->with(['task.project', 'branch'])
            ->where('user_id', auth()->id())
            ->whereIn('status', ['assigned', 'in_progress', 'submitted', 'revision_required'])
            ->get()
            ->sortBy(fn (RndProjectTaskAssignment $assignment): string => sprintf(
                '%d|%s',
                match (true) {
                    $assignment->task->isOverdue() => 0,
                    $assignment->task->due_date->isToday() => 1,
                    $assignment->task->priority->isUrgentOrHigh() => 2,
                    default => 3,
                },
                $assignment->task->due_date->format('Y-m-d'),
            ))
            ->values();
    }

    public function startAssignment(int $assignmentId): void
    {
        $assignment = RndProjectTaskAssignment::query()->findOrFail($assignmentId);
        abort_unless(auth()->user()->can('respond', $assignment), 403);

        app(StartProjectTaskAssignmentAction::class)->execute($assignment);

        Notification::make()->title('Tugas dimulai')->success()->send();
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
                'b2'
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
