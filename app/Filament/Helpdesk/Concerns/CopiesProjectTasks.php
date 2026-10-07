<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Actions\Rnd\ProjectTask\CopyProjectTaskAction;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;

/**
 * "Copy Task" modal (docs/rnd-project-checkpoint-calendar-prd.md §10.3, §21.5), opened from the
 * Task detail on both calendars. The copy stays in the source Project on the MVP; the user
 * confirms the title, new dates, and Branch/PIC. Persistence and the field allowlist live in
 * `CopyProjectTaskAction`. Requires `InteractsWithProjectTasks` on the host.
 */
trait CopiesProjectTasks
{
    public bool $copyModalOpen = false;

    #[Locked]
    public ?int $copyingTaskId = null;

    public string $copyTitle = '';

    public string $copyAssignedDate = '';

    public string $copyDueDate = '';

    /** @var array<int, array{branch_id: string, user_ids: array<int, int|string>}> */
    public array $copyBranchRows = [];

    public function openCopyTaskModal(int $taskId): void
    {
        $source = $this->findProjectTask($taskId);
        abort_unless(auth()->user()->can('copy', $source), 403);

        $this->resetValidation();
        $this->copyingTaskId = $source->id;
        $this->copyTitle = $source->title;
        $this->copyAssignedDate = today()->toDateString();
        $this->copyDueDate = $this->copyDueDateKeepingDuration($source, $this->copyAssignedDate);
        $this->copyBranchRows = $this->copyBranchRowsFromSource($source);
        $this->viewingTaskId = null;
        $this->copyModalOpen = true;
    }

    public function closeCopyTaskModal(): void
    {
        $this->resetValidation();
        $this->copyModalOpen = false;
        $this->copyingTaskId = null;
    }

    /**
     * Livewire hook — keeps the source duration when the user moves the new assign date (§14.3.5).
     */
    public function updatedCopyAssignedDate(): void
    {
        if ($this->copyingTaskId === null || strtotime($this->copyAssignedDate) === false) {
            return;
        }

        $this->copyDueDate = $this->copyDueDateKeepingDuration($this->findProjectTask($this->copyingTaskId), $this->copyAssignedDate);
    }

    public function copyTask(): void
    {
        abort_if($this->copyingTaskId === null, 404);
        $source = $this->findProjectTask($this->copyingTaskId);
        abort_unless(auth()->user()->can('copy', $source), 403);

        $validated = $this->validate([
            'copyTitle' => ['required', 'string', 'max:255'],
            'copyAssignedDate' => ['required', 'date'],
            'copyDueDate' => ['required', 'date', 'after_or_equal:copyAssignedDate'],
            ...$this->branchRowRules('copyBranchRows'),
        ]);

        $copy = app(CopyProjectTaskAction::class)->execute($source, [
            'title' => $validated['copyTitle'],
            'assigned_date' => $validated['copyAssignedDate'],
            'due_date' => $validated['copyDueDate'],
            'branches' => $this->branchAssignmentsFromRows($validated['copyBranchRows']),
        ], auth()->user());

        $this->copyModalOpen = false;
        $this->copyingTaskId = null;
        $this->copiedProjectTask($copy);

        Notification::make()
            ->title('Task berhasil disalin')
            ->body("“{$copy->title}” dibuat dengan deadline {$copy->due_date->format('d M Y')}.")
            ->success()
            ->send();
    }

    /**
     * Host hook after a successful copy — e.g. the Project calendar jumps to the copy's month.
     */
    protected function copiedProjectTask(RndProjectTask $copy): void {}

    private function copyDueDateKeepingDuration(RndProjectTask $source, string $assignedDate): string
    {
        return app(CopyProjectTaskAction::class)->defaultDueDate($source, $assignedDate);
    }

    /**
     * Offers the source's Branch/PIC mapping only when every PIC is still eligible (§19.9);
     * otherwise the user starts from an empty row. Either way the Action re-validates on save.
     *
     * @return array<int, array{branch_id: string, user_ids: array<int, int|string>}>
     */
    private function copyBranchRowsFromSource(RndProjectTask $source): array
    {
        $assignments = $source->activeAssignments()->with('user')->get();
        $resolver = app(ProjectTaskAssigneeResolver::class);

        $allEligible = $assignments->isNotEmpty() && $assignments->every(
            fn (RndProjectTaskAssignment $assignment): bool => $assignment->user !== null
                && $resolver->isEligible($assignment->user, $assignment->branch_id)
        );

        if (! $allEligible) {
            return [$this->emptyBranchRow()];
        }

        return $assignments
            ->groupBy('branch_id')
            ->map(fn ($branchAssignments, int $branchId): array => [
                'branch_id' => (string) $branchId,
                'user_ids' => $branchAssignments->pluck('user_id')->map(fn (int $userId): string => (string) $userId)->values()->all(),
            ])
            ->values()
            ->all();
    }
}
