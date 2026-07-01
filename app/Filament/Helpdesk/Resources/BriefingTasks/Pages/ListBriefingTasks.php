<?php

namespace App\Filament\Helpdesk\Resources\BriefingTasks\Pages;

use App\Filament\Helpdesk\Resources\BriefingTasks\BriefingTaskResource;
use App\Models\Branch;
use App\Models\BriefingTask;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class ListBriefingTasks extends Page
{
    protected static string $resource = BriefingTaskResource::class;

    protected string $view = 'filament.helpdesk.resources.briefing-tasks.index';

    /** null = branch cards, 0 = global tasks, N = branch N */
    public ?int $selectedBranchId = null;

    public ?string $selectedBranchName = null;

    public ?int $pendingDeleteId = null;

    public bool $copyPanelOpen = false;

    public ?int $copySourceBranchId = null;

    public function getTitle(): string|Htmlable
    {
        return 'Kelola Poin Briefing';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function selectBranch(int $branchId, string $branchName): void
    {
        $this->selectedBranchId = $branchId;
        $this->selectedBranchName = $branchName;
        $this->pendingDeleteId = null;
        $this->copyPanelOpen = false;
        $this->copySourceBranchId = null;
    }

    public function selectGlobal(): void
    {
        $this->selectedBranchId = 0;
        $this->selectedBranchName = 'Global (Semua Branch)';
        $this->pendingDeleteId = null;
        $this->copyPanelOpen = false;
        $this->copySourceBranchId = null;
    }

    public function clearSelection(): void
    {
        $this->selectedBranchId = null;
        $this->selectedBranchName = null;
        $this->pendingDeleteId = null;
        $this->copyPanelOpen = false;
        $this->copySourceBranchId = null;
    }

    public function confirmDelete(int $taskId): void
    {
        $this->pendingDeleteId = $taskId;
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteId = null;
    }

    public function deleteTask(int $taskId): void
    {
        BriefingTask::find($taskId)?->delete();
        $this->pendingDeleteId = null;
    }

    public function toggleCopyPanel(): void
    {
        $this->copyPanelOpen = ! $this->copyPanelOpen;
        $this->copySourceBranchId = null;
    }

    public function copyFromBranch(): void
    {
        if ($this->copySourceBranchId === null || $this->selectedBranchId === null) {
            return;
        }

        $sourceTasks = $this->sourceTasks;

        if ($sourceTasks->isEmpty()) {
            Notification::make()->title('Tidak ada poin untuk disalin.')->warning()->send();

            return;
        }

        $targetBranchId = $this->selectedBranchId === 0 ? null : $this->selectedBranchId;
        $copied = 0;

        foreach ($sourceTasks as $task) {
            $newKey = $this->resolveUniqueKey($task->key);

            BriefingTask::create([
                'branch_id' => $targetBranchId,
                'key' => $newKey,
                'label' => $task->label,
                'period' => $task->period->value,
                'submission_type' => $task->submission_type->value,
                'note_type' => $task->note_type,
                'group' => $task->group,
                'group_label' => $task->group_label,
                'sort_order' => $task->sort_order,
                'is_active' => $task->is_active,
                'deadline_enabled' => $task->deadline_enabled,
                'deadline_time' => $task->deadline_time,
                'deadline_day' => $task->deadline_day,
            ]);

            $copied++;
        }

        $this->copyPanelOpen = false;
        $this->copySourceBranchId = null;

        Notification::make()
            ->title("{$copied} poin berhasil disalin.")
            ->success()
            ->send();
    }

    private function resolveUniqueKey(string $baseKey): string
    {
        $key = $baseKey;
        $i = 2;
        while (BriefingTask::where('key', $key)->exists()) {
            $key = $baseKey.'_'.$i++;
        }

        return $key;
    }

    #[Computed]
    public function branches(): Collection
    {
        return Branch::where('is_active', true)
            ->withCount('briefingTasks')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function globalTaskCount(): int
    {
        return BriefingTask::whereNull('branch_id')->count();
    }

    #[Computed]
    public function tasks(): Collection
    {
        if ($this->selectedBranchId === null) {
            return new Collection;
        }

        if ($this->selectedBranchId === 0) {
            return BriefingTask::whereNull('branch_id')
                ->orderBy('period')
                ->orderBy('sort_order')
                ->get();
        }

        return BriefingTask::where('branch_id', $this->selectedBranchId)
            ->orderBy('period')
            ->orderBy('sort_order')
            ->get();
    }

    /** Options for the copy-source select: Global + all branches */
    #[Computed]
    public function copySourceOptions(): array
    {
        return [0 => 'Global (Semua Branch)']
            + Branch::where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
    }

    /** Preview of tasks that will be copied from the selected source */
    #[Computed]
    public function sourceTasks(): Collection
    {
        if ($this->copySourceBranchId === null) {
            return new Collection;
        }

        if ($this->copySourceBranchId === 0) {
            return BriefingTask::whereNull('branch_id')
                ->orderBy('period')
                ->orderBy('sort_order')
                ->get();
        }

        return BriefingTask::where('branch_id', $this->copySourceBranchId)
            ->orderBy('period')
            ->orderBy('sort_order')
            ->get();
    }

    public function getCreateUrl(): string
    {
        $url = BriefingTaskResource::getUrl('create');

        if ($this->selectedBranchId !== null && $this->selectedBranchId !== 0) {
            $url .= '?branch_id='.$this->selectedBranchId;
        }

        return $url;
    }

    public function getEditUrl(int $taskId): string
    {
        return BriefingTaskResource::getUrl('edit', ['record' => $taskId]);
    }
}
