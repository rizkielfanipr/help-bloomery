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

    public ?string $addingToGroup = null;

    public ?string $addingGroupLabel = null;

    /** @var array{label: string, period: string, submission_type: string} */
    public array $quickAdd = ['label' => '', 'period' => 'daily', 'submission_type' => 'checkbox'];

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

    public function startQuickAdd(string $group, string $groupLabel): void
    {
        $this->addingToGroup = $group;
        $this->addingGroupLabel = $groupLabel;
        $this->quickAdd = ['label' => '', 'period' => 'daily', 'submission_type' => 'checkbox'];
        $this->pendingDeleteId = null;
    }

    public function cancelQuickAdd(): void
    {
        $this->addingToGroup = null;
        $this->addingGroupLabel = null;
    }

    public function saveQuickTask(): void
    {
        $this->validate([
            'quickAdd.label' => 'required|string|max:255',
            'quickAdd.period' => 'required|in:daily,weekly,monthly',
            'quickAdd.submission_type' => 'required',
        ]);

        $branchId = $this->selectedBranchId === 0 ? null : $this->selectedBranchId;
        $group = $this->addingToGroup ?: null;

        $baseKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($this->quickAdd['label']));
        $baseKey = trim($baseKey, '_') ?: 'task';
        $key = $baseKey;
        $i = 2;
        while (BriefingTask::where('key', $key)->exists()) {
            $key = $baseKey.'_'.$i++;
        }

        $maxSort = BriefingTask::when(
            $branchId === null,
            fn ($q) => $q->whereNull('branch_id'),
            fn ($q) => $q->where('branch_id', $branchId)
        )
            ->where(fn ($q) => $group ? $q->where('group', $group) : $q->whereNull('group'))
            ->where('period', $this->quickAdd['period'])
            ->max('sort_order') ?? 0;

        BriefingTask::create([
            'branch_id' => $branchId,
            'key' => $key,
            'label' => $this->quickAdd['label'],
            'period' => $this->quickAdd['period'],
            'submission_type' => $this->quickAdd['submission_type'],
            'group' => $group,
            'group_label' => $this->addingGroupLabel ?: null,
            'sort_order' => $maxSort + 10,
            'is_active' => true,
        ]);

        $this->cancelQuickAdd();

        Notification::make()->title('Poin berhasil ditambahkan.')->success()->send();
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

        // Delete existing tasks for target before replacing
        BriefingTask::when(
            $targetBranchId === null,
            fn ($q) => $q->whereNull('branch_id'),
            fn ($q) => $q->where('branch_id', $targetBranchId)
        )->delete();

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
                ->orderByRaw('ISNULL(`group`), `group`')
                ->orderByRaw("FIELD(period, 'daily', 'weekly', 'monthly')")
                ->orderBy('sort_order')
                ->get();
        }

        return BriefingTask::where('branch_id', $this->selectedBranchId)
            ->orderByRaw('ISNULL(`group`), `group`')
            ->orderByRaw("FIELD(period, 'daily', 'weekly', 'monthly')")
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

    public function getCreateUrlForGroup(?string $group, ?string $groupLabel): string
    {
        $params = [];

        if ($this->selectedBranchId !== null && $this->selectedBranchId !== 0) {
            $params['branch_id'] = $this->selectedBranchId;
        }

        if ($group !== null) {
            $params['group'] = $group;
            $params['group_label'] = $groupLabel;
        }

        $url = BriefingTaskResource::getUrl('create');

        return empty($params) ? $url : $url.'?'.http_build_query($params);
    }

    public function getEditUrl(int $taskId): string
    {
        return BriefingTaskResource::getUrl('edit', ['record' => $taskId]);
    }
}
