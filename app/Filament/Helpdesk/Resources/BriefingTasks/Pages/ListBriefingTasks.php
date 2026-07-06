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

    // ── Quick-add state ──────────────────────────────────────────────────────
    /** group key being added to, or null for ungrouped */
    public ?string $addingToGroup = null;

    public ?string $addingGroupLabel = null;

    /** period section being added to (for ungrouped quick-add) */
    public ?string $addingToPeriod = null;

    /** @var array{label: string, period: string, submission_type: string} */
    public array $quickAdd = ['label' => '', 'period' => 'daily', 'submission_type' => 'checkbox'];

    // ── New-group creation state ─────────────────────────────────────────────
    public ?string $creatingGroupForPeriod = null;

    public string $newGroupLabel = '';

    /**
     * Groups created in this session but with no DB tasks yet.
     * Shape: ['daily' => [['key' => 'xxx', 'label' => 'Xxx'], ...], ...]
     *
     * @var array<string, array<int, array{key: string, label: string}>>
     */
    public array $pendingGroups = [];

    // ── Copy panel state ─────────────────────────────────────────────────────
    public bool $copyPanelOpen = false;

    public ?int $copySourceBranchId = null;

    // ── Page helpers ─────────────────────────────────────────────────────────

    public function getTitle(): string|Htmlable
    {
        return 'Kelola Poin Briefing';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    // ── Branch selection ─────────────────────────────────────────────────────

    public function selectBranch(int $branchId, string $branchName): void
    {
        $this->selectedBranchId = $branchId;
        $this->selectedBranchName = $branchName;
        $this->resetInteractionState();
    }

    public function selectGlobal(): void
    {
        $this->selectedBranchId = 0;
        $this->selectedBranchName = 'Global (Semua Branch)';
        $this->resetInteractionState();
    }

    public function clearSelection(): void
    {
        $this->selectedBranchId = null;
        $this->selectedBranchName = null;
        $this->resetInteractionState();
    }

    private function resetInteractionState(): void
    {
        $this->pendingDeleteId = null;
        $this->copyPanelOpen = false;
        $this->copySourceBranchId = null;
        $this->cancelQuickAdd();
        $this->cancelNewGroup();
        $this->pendingGroups = [];
    }

    // ── Quick-add ────────────────────────────────────────────────────────────

    public function startGroupQuickAdd(string $group, string $groupLabel, string $period): void
    {
        $this->addingToGroup = $group;
        $this->addingGroupLabel = $groupLabel;
        $this->addingToPeriod = null;
        $this->quickAdd = ['label' => '', 'period' => $period, 'submission_type' => 'checkbox'];
        $this->pendingDeleteId = null;
        $this->creatingGroupForPeriod = null;
    }

    public function startPeriodQuickAdd(string $period): void
    {
        $this->addingToPeriod = $period;
        $this->addingToGroup = null;
        $this->addingGroupLabel = null;
        $this->quickAdd = ['label' => '', 'period' => $period, 'submission_type' => 'checkbox'];
        $this->pendingDeleteId = null;
        $this->creatingGroupForPeriod = null;
    }

    public function cancelQuickAdd(): void
    {
        // If the group is a pending (empty) group, remove it so it disappears
        if ($this->addingToGroup !== null) {
            $period = $this->quickAdd['period'] ?? null;

            if ($period && isset($this->pendingGroups[$period])) {
                $this->pendingGroups[$period] = array_values(array_filter(
                    $this->pendingGroups[$period],
                    fn ($g) => $g['key'] !== $this->addingToGroup
                ));
            }
        }

        $this->addingToGroup = null;
        $this->addingGroupLabel = null;
        $this->addingToPeriod = null;
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

        // Remove from pendingGroups now that a task exists
        $period = $this->quickAdd['period'];

        if ($group && isset($this->pendingGroups[$period])) {
            $this->pendingGroups[$period] = array_values(array_filter(
                $this->pendingGroups[$period],
                fn ($g) => $g['key'] !== $group
            ));
        }

        // Keep the quick-add open so user can add more tasks to same context
        $this->quickAdd['label'] = '';

        Notification::make()->title('Poin berhasil ditambahkan.')->success()->send();
    }

    // ── New group ────────────────────────────────────────────────────────────

    public function startNewGroup(string $period): void
    {
        $this->creatingGroupForPeriod = $period;
        $this->newGroupLabel = '';
        $this->addingToGroup = null;
        $this->addingGroupLabel = null;
        $this->addingToPeriod = null;
    }

    public function saveNewGroup(): void
    {
        $this->validate(['newGroupLabel' => 'required|string|max:100']);

        $key = preg_replace('/[^a-z0-9]+/', '_', strtolower($this->newGroupLabel));
        $key = trim($key, '_') ?: 'group';

        // Ensure key is unique within this period's pending groups
        $existing = collect($this->pendingGroups[$this->creatingGroupForPeriod] ?? [])->pluck('key')->all();
        $finalKey = $key;
        $suffix = 2;
        while (in_array($finalKey, $existing)) {
            $finalKey = $key.'_'.$suffix++;
        }

        $this->pendingGroups[$this->creatingGroupForPeriod][] = [
            'key' => $finalKey,
            'label' => $this->newGroupLabel,
        ];

        // Immediately open quick-add for the new group
        $this->startGroupQuickAdd($finalKey, $this->newGroupLabel, $this->creatingGroupForPeriod);
        $this->creatingGroupForPeriod = null;
        $this->newGroupLabel = '';
    }

    public function cancelNewGroup(): void
    {
        $this->creatingGroupForPeriod = null;
        $this->newGroupLabel = '';
    }

    // ── Delete ───────────────────────────────────────────────────────────────

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

    // ── Copy panel ───────────────────────────────────────────────────────────

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
        $this->pendingGroups = [];

        Notification::make()->title("{$copied} poin berhasil disalin.")->success()->send();
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

    // ── Computed ─────────────────────────────────────────────────────────────

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

        $query = $this->selectedBranchId === 0
            ? BriefingTask::whereNull('branch_id')
            : BriefingTask::where('branch_id', $this->selectedBranchId);

        return $query
            ->orderByRaw("FIELD(period, 'daily', 'weekly', 'monthly')")
            ->orderByRaw('ISNULL(`group`), `group`')
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function copySourceOptions(): array
    {
        return [0 => 'Global (Semua Branch)']
            + Branch::where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
    }

    #[Computed]
    public function sourceTasks(): Collection
    {
        if ($this->copySourceBranchId === null) {
            return new Collection;
        }

        if ($this->copySourceBranchId === 0) {
            return BriefingTask::whereNull('branch_id')
                ->orderByRaw("FIELD(period, 'daily', 'weekly', 'monthly')")
                ->orderBy('sort_order')
                ->get();
        }

        return BriefingTask::where('branch_id', $this->copySourceBranchId)
            ->orderByRaw("FIELD(period, 'daily', 'weekly', 'monthly')")
            ->orderBy('sort_order')
            ->get();
    }

    // ── URL helpers ──────────────────────────────────────────────────────────

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
