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
    /**
     * CASE expressions (not MySQL's FIELD()/ISNULL(), which SQLite — used by the test suite —
     * doesn't support) so ordering works identically on both.
     */
    private const PERIOD_ORDER_SQL = "CASE period WHEN 'daily' THEN 1 WHEN 'weekly' THEN 2 WHEN 'monthly' THEN 3 ELSE 4 END";

    private const GROUP_ORDER_SQL = 'CASE WHEN `group` IS NULL THEN 1 ELSE 0 END, `group`';

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

    /** @var array{label: string, period: string, submission_type: string, deadline_enabled: bool, deadline_time: string} */
    public array $quickAdd = ['label' => '', 'period' => 'daily', 'submission_type' => 'photo', 'deadline_enabled' => false, 'deadline_time' => '23:59'];

    // ── Edit state ───────────────────────────────────────────────────────────
    public ?int $editingTaskId = null;

    public bool $editingTaskHasGroup = false;

    /** @var array{label: string, key: string, period: string, submission_type: string, note_type: ?string, group_label: ?string, is_active: bool, deadline_enabled: bool, deadline_time: string} */
    public array $editTask = [];

    // ── New-group creation state ─────────────────────────────────────────────
    public ?string $creatingGroupForPeriod = null;

    public string $newGroupLabel = '';

    /** @var array<int, array{label: string, submission_type: string}> */
    public array $newGroupTasks = [['label' => '', 'submission_type' => 'photo']];

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

    /**
     * Quick stats for the page header card, matching BriefingScoreWorkspacePage's visual
     * pattern (docs/products/daily-briefing/scoring-ui/prd.md §13.1).
     *
     * @return array{total: int, active: int, global: int, branchSpecific: int}
     */
    #[Computed]
    public function stats(): array
    {
        return BriefingTaskResource::summaryStats();
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
        $this->cancelEditTask();
        $this->pendingGroups = [];
    }

    // ── Quick-add ────────────────────────────────────────────────────────────

    public function startGroupQuickAdd(string $group, string $groupLabel, string $period): void
    {
        $this->addingToGroup = $group;
        $this->addingGroupLabel = $groupLabel;
        $this->addingToPeriod = null;
        $this->quickAdd = ['label' => '', 'period' => $period, 'submission_type' => 'photo', 'deadline_enabled' => false, 'deadline_time' => '23:59'];
        $this->pendingDeleteId = null;
        $this->creatingGroupForPeriod = null;
        $this->copyPanelOpen = false;
        $this->cancelEditTask();
    }

    public function startPeriodQuickAdd(string $period): void
    {
        $this->addingToPeriod = $period;
        $this->addingToGroup = null;
        $this->addingGroupLabel = null;
        $this->quickAdd = ['label' => '', 'period' => $period, 'submission_type' => 'photo', 'deadline_enabled' => false, 'deadline_time' => '23:59'];
        $this->pendingDeleteId = null;
        $this->creatingGroupForPeriod = null;
        $this->copyPanelOpen = false;
        $this->cancelEditTask();
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
            'quickAdd.deadline_enabled' => 'boolean',
            'quickAdd.deadline_time' => 'nullable',
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
            'deadline_enabled' => (bool) $this->quickAdd['deadline_enabled'],
            'deadline_time' => $this->quickAdd['deadline_enabled'] ? $this->quickAdd['deadline_time'] : null,
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
        $this->quickAdd['deadline_enabled'] = false;
        $this->quickAdd['deadline_time'] = '23:59';

        Notification::make()->title('Poin berhasil ditambahkan.')->success()->send();
    }

    // ── New group ────────────────────────────────────────────────────────────

    public function startNewGroup(string $period): void
    {
        $this->creatingGroupForPeriod = $period;
        $this->newGroupLabel = '';
        $this->newGroupTasks = [['label' => '', 'submission_type' => 'photo']];
        $this->addingToGroup = null;
        $this->addingGroupLabel = null;
        $this->addingToPeriod = null;
        $this->copyPanelOpen = false;
        $this->cancelEditTask();
    }

    public function addNewGroupTaskRow(): void
    {
        $this->newGroupTasks[] = ['label' => '', 'submission_type' => 'photo'];
    }

    public function removeNewGroupTaskRow(int $index): void
    {
        unset($this->newGroupTasks[$index]);
        $this->newGroupTasks = array_values($this->newGroupTasks);

        if ($this->newGroupTasks === []) {
            $this->newGroupTasks = [['label' => '', 'submission_type' => 'photo']];
        }
    }

    /**
     * Creates the new group together with all its filled-in points in one submit,
     * so the user doesn't have to open a second modal to add each point one by one.
     */
    public function saveNewGroup(): void
    {
        $this->validate([
            'newGroupLabel' => 'required|string|max:100',
            'newGroupTasks.*.label' => 'nullable|string|max:255',
            'newGroupTasks.*.submission_type' => 'required',
        ]);

        $filledTasks = collect($this->newGroupTasks)->filter(fn ($task) => filled($task['label'] ?? null))->values();

        if ($filledTasks->isEmpty()) {
            $this->addError('newGroupTasks.0.label', 'Tambahkan minimal satu poin.');

            return;
        }

        $period = $this->creatingGroupForPeriod;
        $groupLabel = $this->newGroupLabel;
        $branchId = $this->selectedBranchId === 0 ? null : $this->selectedBranchId;

        $groupKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($groupLabel));
        $groupKey = trim($groupKey, '_') ?: 'group';

        $maxSort = BriefingTask::when(
            $branchId === null,
            fn ($q) => $q->whereNull('branch_id'),
            fn ($q) => $q->where('branch_id', $branchId)
        )
            ->where('group', $groupKey)
            ->where('period', $period)
            ->max('sort_order') ?? 0;

        foreach ($filledTasks as $task) {
            $baseKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($task['label']));
            $baseKey = trim($baseKey, '_') ?: 'task';
            $taskKey = $baseKey;
            $i = 2;

            while (BriefingTask::where('key', $taskKey)->exists()) {
                $taskKey = $baseKey.'_'.$i++;
            }

            $maxSort += 10;

            BriefingTask::create([
                'branch_id' => $branchId,
                'key' => $taskKey,
                'label' => $task['label'],
                'period' => $period,
                'submission_type' => $task['submission_type'],
                'group' => $groupKey,
                'group_label' => $groupLabel,
                'sort_order' => $maxSort,
                'is_active' => true,
            ]);
        }

        $this->creatingGroupForPeriod = null;
        $this->newGroupLabel = '';
        $this->newGroupTasks = [['label' => '', 'submission_type' => 'photo']];

        Notification::make()->title("Grup \"{$groupLabel}\" dibuat dengan {$filledTasks->count()} poin.")->success()->send();
    }

    public function cancelNewGroup(): void
    {
        $this->creatingGroupForPeriod = null;
        $this->newGroupLabel = '';
        $this->newGroupTasks = [['label' => '', 'submission_type' => 'photo']];
    }

    // ── Reorder ──────────────────────────────────────────────────────────────

    public function reorderTask(int $draggedId, int $targetId): void
    {
        if ($draggedId === $targetId) {
            return;
        }

        $dragged = BriefingTask::find($draggedId);
        $target = BriefingTask::find($targetId);

        if (! $dragged || ! $target) {
            return;
        }

        [$dragged->sort_order, $target->sort_order] = [$target->sort_order, $dragged->sort_order];
        $dragged->save();
        $target->save();
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

    // ── Edit ─────────────────────────────────────────────────────────────────

    public function startEditTask(int $taskId): void
    {
        $task = BriefingTask::find($taskId);

        if (! $task) {
            return;
        }

        $this->editingTaskId = $task->id;
        $this->editingTaskHasGroup = $task->group !== null;
        $this->editTask = [
            'label' => $task->label,
            'key' => $task->key,
            'period' => $task->period->value,
            'submission_type' => $task->submission_type->value,
            'note_type' => $task->note_type,
            'group_label' => $task->group_label,
            'is_active' => $task->is_active,
            'deadline_enabled' => $task->deadline_enabled,
            'deadline_time' => $task->deadline_time ? substr($task->deadline_time, 0, 5) : '23:59',
        ];

        $this->pendingDeleteId = null;
        $this->cancelQuickAdd();
        $this->cancelNewGroup();
        $this->copyPanelOpen = false;
    }

    public function cancelEditTask(): void
    {
        $this->editingTaskId = null;
        $this->editingTaskHasGroup = false;
        $this->editTask = [];
    }

    public function saveEditTask(): void
    {
        $task = BriefingTask::find($this->editingTaskId);

        if (! $task) {
            $this->cancelEditTask();

            return;
        }

        $this->validate([
            'editTask.label' => 'required|string|max:255',
            'editTask.period' => 'required|in:daily,weekly,monthly',
            'editTask.submission_type' => 'required',
            'editTask.note_type' => 'nullable|string|max:100',
            'editTask.group_label' => 'nullable|string|max:100',
            'editTask.deadline_enabled' => 'boolean',
            'editTask.deadline_time' => 'nullable',
        ]);

        $task->update([
            'label' => $this->editTask['label'],
            'period' => $this->editTask['period'],
            'submission_type' => $this->editTask['submission_type'],
            'note_type' => $this->editTask['note_type'] ?: null,
            'group_label' => $task->group ? ($this->editTask['group_label'] ?: null) : $task->group_label,
            'is_active' => (bool) $this->editTask['is_active'],
            'deadline_enabled' => (bool) $this->editTask['deadline_enabled'],
            'deadline_time' => $this->editTask['deadline_enabled'] ? $this->editTask['deadline_time'] : null,
        ]);

        $this->cancelEditTask();

        Notification::make()->title('Poin berhasil diperbarui.')->success()->send();
    }

    public function deleteFromEditModal(int $taskId): void
    {
        $this->cancelEditTask();
        $this->confirmDelete($taskId);
    }

    // ── Copy panel ───────────────────────────────────────────────────────────

    public function toggleCopyPanel(): void
    {
        $this->copyPanelOpen = ! $this->copyPanelOpen;
        $this->copySourceBranchId = null;

        if ($this->copyPanelOpen) {
            $this->cancelQuickAdd();
            $this->cancelNewGroup();
            $this->cancelEditTask();
        }
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
            ->orderByRaw(self::PERIOD_ORDER_SQL)
            ->orderByRaw(self::GROUP_ORDER_SQL)
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
                ->orderByRaw(self::PERIOD_ORDER_SQL)
                ->orderBy('sort_order')
                ->get();
        }

        return BriefingTask::where('branch_id', $this->copySourceBranchId)
            ->orderByRaw(self::PERIOD_ORDER_SQL)
            ->orderBy('sort_order')
            ->get();
    }
}
