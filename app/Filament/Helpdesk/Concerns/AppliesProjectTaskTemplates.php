<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Actions\Rnd\ProjectTask\ApplyProjectTaskTemplateAction;
use App\Actions\Rnd\ProjectTask\CreateProjectTaskAction;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateCheckpoint;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * "Gunakan Template" wizard on the Project calendar (docs/rnd-project-checkpoint-calendar-prd.md
 * §10.2, §21.4) — one modal with three steps:
 *
 * 1. Pilih Template.
 * 2. Atur Template: per checkpoint include/exclude, title, priority, assign date and deadline (never
 *    after the Project release date); Branch & PIC come from the template and can be changed.
 * 3. Preview: a carousel of the final checkpoints, then create.
 *
 * Nothing is written before the last step; the batch is created by
 * `ApplyProjectTaskTemplateAction` with an idempotency key minted when the wizard opens, so double
 * submits and retries never duplicate Tasks. Requires `InteractsWithProjectTasks` and a non-null
 * `lockedTaskProject()`.
 */
trait AppliesProjectTaskTemplates
{
    public bool $applyTemplateModalOpen = false;

    public int $applyTemplateStep = 1;

    public string $applyTemplateId = '';

    /** @var list<array{checkpoint_id: int, selected: bool, title: string, task_type: string, priority: string, description: ?string, assigned_date: string, due_date: string, branch_rows: array<int, array{branch_id: string, user_ids: array<int, int|string>}>}> */
    public array $applyRows = [];

    public bool $applyConfirmDuplicate = false;

    #[Locked]
    public string $applyIdempotencyKey = '';

    public function openApplyTemplateModal(): void
    {
        abort_unless($this->canApplyTemplate(), 403);

        $this->resetValidation();
        $this->applyTemplateStep = 1;
        $this->applyTemplateId = '';
        $this->applyRows = [];
        $this->applyConfirmDuplicate = false;
        $this->applyIdempotencyKey = (string) Str::uuid();
        $this->applyTemplateModalOpen = true;
    }

    public function closeApplyTemplateModal(): void
    {
        $this->resetValidation();
        $this->applyTemplateModalOpen = false;
    }

    /**
     * Step 1 → 2: load the template's checkpoints with their Branch & PIC defaults.
     */
    public function selectApplyTemplate(): void
    {
        abort_unless($this->canApplyTemplate(), 403);

        $this->validate([
            'applyTemplateId' => ['required', 'integer', Rule::exists('rnd_project_task_templates', 'id')->where('is_active', true)],
        ], [
            'applyTemplateId.required' => 'Pilih template terlebih dahulu.',
            'applyTemplateId.exists' => 'Template tidak ditemukan atau sudah tidak aktif.',
        ]);

        $template = $this->selectedApplyTemplate();

        if ($template->checkpoints->isEmpty()) {
            throw ValidationException::withMessages(['applyTemplateId' => 'Template belum memiliki checkpoint.']);
        }

        $this->applyRows = $template->checkpoints
            ->map(fn (RndProjectTaskTemplateCheckpoint $checkpoint): array => [
                'checkpoint_id' => $checkpoint->id,
                'selected' => true,
                'title' => $checkpoint->title,
                'task_type' => $checkpoint->task_type->value,
                'priority' => $checkpoint->priority->value,
                'description' => $checkpoint->description,
                'assigned_date' => '',
                'due_date' => '',
                'branch_rows' => $this->defaultApplyBranchRows($checkpoint),
            ])
            ->values()
            ->all();

        $this->applyTemplateStep = 2;
    }

    /**
     * Step 2 → 3: everything the preview shows is validated first, including PIC eligibility.
     */
    public function continueToApplyPreview(): void
    {
        abort_unless($this->canApplyTemplate(), 403);

        $this->validateApplyRows();
        $this->applyTemplateStep = 3;
    }

    public function backToApplyStep(int $step): void
    {
        $this->applyTemplateStep = max(1, min($step, $this->applyTemplateStep));
    }

    public function applySelectedTemplate(): void
    {
        abort_unless($this->canApplyTemplate(), 403);

        try {
            $this->validateApplyRows();
        } catch (ValidationException $exception) {
            $this->applyTemplateStep = 2;

            throw $exception;
        }

        try {
            $application = app(ApplyProjectTaskTemplateAction::class)->execute($this->lockedTaskProject(), $this->selectedApplyTemplate(), [
                'idempotency_key' => $this->applyIdempotencyKey,
                'allow_duplicate' => $this->applyConfirmDuplicate,
                'checkpoints' => collect($this->selectedApplyRows())
                    ->map(fn (array $row): array => [
                        'checkpoint_id' => (int) $row['checkpoint_id'],
                        'title' => $row['title'],
                        'priority' => $row['priority'],
                        'assigned_date' => $row['assigned_date'],
                        'due_date' => $row['due_date'],
                        'branches' => $this->branchAssignmentsFromRows($row['branch_rows']),
                    ])
                    ->all(),
            ], auth()->user());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $errorKey = $this->applyErrorKey($key);
                $this->addError($errorKey, $messages[0]);

                if (str_starts_with($errorKey, 'applyRows.')) {
                    $this->applyTemplateStep = 2;
                }
            }

            return;
        }

        $this->applyTemplateModalOpen = false;
        $this->appliedProjectTaskTemplate($application->tasks);

        Notification::make()
            ->title('Template berhasil diterapkan')
            ->body("{$application->tasks->count()} Task dibuat dari “{$application->template_name}”.")
            ->success()
            ->send();
    }

    /** @return EloquentCollection<int, RndProjectTaskTemplate> */
    public function applicableTemplates(): EloquentCollection
    {
        return RndProjectTaskTemplate::query()
            ->active()
            ->whereHas('checkpoints')
            ->withCount('checkpoints')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);
    }

    /**
     * Drives the "template ini sudah pernah diterapkan" warning on the preview step.
     */
    public function applyTemplateWasAppliedBefore(): bool
    {
        if ($this->applyTemplateId === '' || ! $this->applyTemplateModalOpen) {
            return false;
        }

        $template = RndProjectTaskTemplate::query()->find((int) $this->applyTemplateId);

        return $template !== null
            && app(ApplyProjectTaskTemplateAction::class)->hasBeenAppliedTo($this->lockedTaskProject(), $template);
    }

    /** @return list<array{checkpoint_id: int, selected: bool, title: string, task_type: string, priority: string, description: ?string, assigned_date: string, due_date: string, branch_rows: array<int, array{branch_id: string, user_ids: array<int, int|string>}>}> */
    public function selectedApplyRows(): array
    {
        return array_values(array_filter($this->applyRows, fn (array $row): bool => (bool) $row['selected']));
    }

    /**
     * Display-ready slides for the preview carousel, in checkpoint order.
     *
     * @return list<array{number: int, title: string, category: string, priority: string, description: ?string, assigned_date: string, due_date: string, branches: list<array{name: string, pics: list<string>}>}>
     */
    public function applyPreviewSlides(): array
    {
        $branchNames = $this->taskFilterBranches()->pluck('name', 'id');
        $categories = $this->taskCategoryOptions();
        $priorities = $this->taskPriorityOptions();

        return collect($this->applyRows)
            ->filter(fn (array $row): bool => (bool) $row['selected'])
            ->map(fn (array $row, int $index): array => [
                'number' => $index + 1,
                'title' => $row['title'],
                'category' => $categories[$row['task_type']] ?? $row['task_type'],
                'priority' => $priorities[$row['priority']] ?? $row['priority'],
                'description' => $row['description'] ?? null,
                'assigned_date' => Carbon::parse($row['assigned_date'])->translatedFormat('d M Y'),
                'due_date' => Carbon::parse($row['due_date'])->translatedFormat('d M Y'),
                'branches' => collect($row['branch_rows'])
                    ->map(fn (array $branchRow): array => [
                        'name' => $branchNames[(int) $branchRow['branch_id']] ?? '-',
                        'pics' => collect($branchRow['user_ids'])
                            ->map(fn ($userId): string => $this->eligiblePicsForBranch($branchRow['branch_id'])[(int) $userId] ?? '-')
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    protected function canApplyTemplate(): bool
    {
        $project = $this->lockedTaskProject();

        return $project !== null && auth()->user()->can('applyTemplate', [RndProjectTask::class, $project]);
    }

    /**
     * Host hook after a successful apply — e.g. the calendar jumps to the first new deadline.
     *
     * @param  EloquentCollection<int, RndProjectTask>  $tasks
     */
    protected function appliedProjectTaskTemplate(EloquentCollection $tasks): void {}

    private function selectedApplyTemplate(): RndProjectTaskTemplate
    {
        return RndProjectTaskTemplate::query()
            ->with(['checkpoints.branches', 'checkpoints.picUsers'])
            ->findOrFail((int) $this->applyTemplateId);
    }

    /**
     * Dates, fields, Branch/PIC shape, and PIC eligibility of every selected checkpoint.
     */
    private function validateApplyRows(): void
    {
        $selectedCount = count($this->selectedApplyRows());

        if ($selectedCount === 0) {
            throw ValidationException::withMessages(['applyRows' => 'Pilih minimal satu checkpoint.']);
        }

        if ($selectedCount > RndProjectTaskTemplate::MAX_CHECKPOINTS) {
            throw ValidationException::withMessages([
                'applyRows' => 'Maksimal '.RndProjectTaskTemplate::MAX_CHECKPOINTS.' checkpoint dapat diterapkan dalam satu kali proses.',
            ]);
        }

        $applyAction = app(ApplyProjectTaskTemplateAction::class);
        $project = $this->lockedTaskProject();
        $rules = [];

        foreach ($this->applyRows as $index => $row) {
            if ($row['selected']) {
                $rules += $applyAction->checkpointRowRules($project, "applyRows.{$index}");
                $rules += $this->branchRowRules("applyRows.{$index}.branch_rows");
            }
        }

        $this->validate($rules, [
            ...$applyAction->checkpointRowMessages($project, 'applyRows'),
            'applyRows.*.branch_rows.required' => 'Pilih minimal satu Branch beserta PIC-nya.',
            'applyRows.*.branch_rows.*.branch_id.required' => 'Pilih Branch.',
            'applyRows.*.branch_rows.*.user_ids.required' => 'Pilih minimal satu PIC.',
        ]);

        $createProjectTask = app(CreateProjectTaskAction::class);

        foreach ($this->applyRows as $index => $row) {
            if (! $row['selected']) {
                continue;
            }

            try {
                $createProjectTask->assertValidAssignments($this->branchAssignmentsFromRows($row['branch_rows']));
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    "applyRows.{$index}.branch_rows" => collect($exception->errors())->flatten()->first(),
                ]);
            }
        }
    }

    /**
     * The template's Branch & PIC defaults, skipping Branches that were deactivated since.
     *
     * @return array<int, array{branch_id: string, user_ids: array<int, int|string>}>
     */
    private function defaultApplyBranchRows(RndProjectTaskTemplateCheckpoint $checkpoint): array
    {
        $activeBranchIds = $this->taskFilterBranches()->pluck('id')->map(fn (int $id): string => (string) $id)->all();

        $rows = array_values(array_filter(
            $checkpoint->branchPicRows(),
            fn (array $row): bool => in_array($row['branch_id'], $activeBranchIds, true),
        ));

        return $rows !== [] ? $rows : [$this->emptyBranchRow()];
    }

    /**
     * Maps Action-level errors onto the wizard's own fields so they show next to the right input.
     * The Action indexes checkpoints by their position among the selected rows.
     */
    private function applyErrorKey(string $actionKey): string
    {
        if (preg_match('/^checkpoints\.(\d+)\.branches/', $actionKey, $matches) === 1) {
            $rowIndex = array_keys(array_filter($this->applyRows, fn (array $row): bool => (bool) $row['selected']))[(int) $matches[1]] ?? null;

            if ($rowIndex !== null) {
                return "applyRows.{$rowIndex}.branch_rows";
            }
        }

        return $actionKey === 'allow_duplicate' ? 'applyConfirmDuplicate' : 'applyTemplate';
    }
}
