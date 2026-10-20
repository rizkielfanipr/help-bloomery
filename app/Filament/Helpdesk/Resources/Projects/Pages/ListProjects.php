<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Actions\ArchiveRndProjectAction;
use App\Actions\Rnd\Project\CreateRndProjectAction;
use App\Actions\Rnd\Project\UpdateRndProjectAction;
use App\Enums\RndProjectType;
use App\Filament\Helpdesk\Concerns\HasProjectTaskCalendar;
use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Models\Branch;
use App\Models\RndProject;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class ListProjects extends ListRecords
{
    use HasProjectTaskCalendar;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.helpdesk.rnd-projects.index';

    /**
     * Lets the dashboard's "Tugas yang Perlu Ditindaklanjuti" cards deep-link straight into a
     * Task's detail modal (docs/rnd-project-task-calendar-prd.md §15).
     */
    #[Url]
    public ?int $openTask = null;

    public string $projectSearch = '';

    public string $projectStatus = '';

    public string $projectTypeFilter = '';

    public string $projectView = 'list';

    public string $calendarMonth = '';

    public bool $createProjectModalOpen = false;

    public ?int $editingProjectId = null;

    public string $projectName = '';

    public string $projectDescription = '';

    public string $projectStartDate = '';

    public string $projectEndDate = '';

    public string $projectType = 'release_product';

    public string $partnershipEventName = '';

    /** @var list<int|string> */
    public array $partnershipBranchIds = [];

    public function getActiveBranchesProperty(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function togglePartnershipBranchId(int $branchId): void
    {
        $selectedIds = collect($this->partnershipBranchIds)->map(fn ($id): int => (int) $id);
        $this->partnershipBranchIds = $selectedIds->contains($branchId)
            ? $selectedIds->reject(fn (int $id): bool => $id === $branchId)->values()->all()
            : $selectedIds->push($branchId)->unique()->values()->all();
    }

    public function mount(): void
    {
        parent::mount();

        if ($this->openTask !== null) {
            $this->projectView = 'tasks';
            $this->openTaskDetail($this->openTask);
        }
    }

    public function projects(): Collection
    {
        return RndProject::query()
            ->when($this->projectStatus === 'archived', fn ($query) => $query->onlyTrashed())
            ->withCount(['products', 'premixAssemblies', 'partnershipMenus'])
            ->when($this->projectTypeFilter !== '', fn ($query) => $query->where('project_type', $this->projectTypeFilter))
            ->when($this->projectSearch !== '', fn ($query) => $query->where(function ($query): void {
                $query
                    ->where('name', 'like', '%'.$this->projectSearch.'%')
                    ->orWhere('description', 'like', '%'.$this->projectSearch.'%');
            }))
            ->when($this->projectStatus === 'upcoming', fn ($query) => $query->whereDate('start_date', '>', today()))
            ->when($this->projectStatus === 'active', fn ($query) => $query
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today()))
            ->when($this->projectStatus === 'completed', fn ($query) => $query->whereDate('end_date', '<', today()))
            ->latest('updated_at')
            ->get();
    }

    /**
     * @return array{monthLabel: string, weeks: array<int, array{dates: array<int, array{date: Carbon, isCurrentMonth: bool, isToday: bool}>, projects: array<int, array{project: RndProject, dayColumn: int}>}>}
     */
    public function calendar(): array
    {
        $month = $this->selectedCalendarMonth();
        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $projects = $this->projects()
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

            $segments = $projects
                ->filter(fn (RndProject $project): bool => $project->end_date->betweenIncluded($weekStart, $weekEnd))
                ->sortBy(fn (RndProject $project): string => $project->end_date->format('Y-m-d').'|'.str_pad((string) $project->id, 10, '0', STR_PAD_LEFT))
                ->map(fn (RndProject $project): array => [
                    'project' => $project,
                    'dayColumn' => (int) $weekStart->diffInDays($project->end_date) + 1,
                ])
                ->values()
                ->all();

            $weeks[] = ['dates' => $dates, 'projects' => $segments];
        }

        return [
            'monthLabel' => $month->translatedFormat('F Y'),
            'weeks' => $weeks,
        ];
    }

    public function showProjectList(): void
    {
        $this->projectView = 'list';
    }

    public function showProjectCalendar(): void
    {
        if ($this->projectStatus === 'archived') {
            return;
        }

        $this->projectView = 'calendar';
        $this->ensureCalendarMonthIsSet();
    }

    public function previousCalendarMonth(): void
    {
        $this->calendarMonth = $this->selectedCalendarMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextCalendarMonth(): void
    {
        $this->calendarMonth = $this->selectedCalendarMonth()->addMonthNoOverflow()->format('Y-m');
    }

    public function currentCalendarMonth(): void
    {
        $this->calendarMonth = today()->format('Y-m');
    }

    private function ensureCalendarMonthIsSet(): void
    {
        if ($this->calendarMonth === '') {
            $this->calendarMonth = today()->format('Y-m');
        }
    }

    private function selectedCalendarMonth(): Carbon
    {
        $this->ensureCalendarMonthIsSet();

        return Carbon::createFromFormat('Y-m-d', $this->calendarMonth.'-01')->startOfDay();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function openCreateProjectModal(): void
    {
        abort_unless(ProjectResource::canCreate(), 403);
        $this->resetValidation();
        $this->reset(['projectName', 'projectDescription', 'projectStartDate', 'projectEndDate', 'partnershipEventName', 'partnershipBranchIds']);
        $this->projectType = RndProjectType::ReleaseProduct->value;
        $this->editingProjectId = null;
        $this->createProjectModalOpen = true;
    }

    public function openEditProjectModal(int $projectId): void
    {
        $project = RndProject::query()->findOrFail($projectId);
        abort_unless(ProjectResource::canEdit($project), 403);
        $this->resetValidation();
        $this->editingProjectId = $project->id;
        $this->projectName = $project->name;
        $this->projectDescription = $project->description ?? '';
        $this->projectStartDate = $project->start_date->format('Y-m-d');
        $this->projectEndDate = $project->end_date->format('Y-m-d');
        $this->projectType = $project->project_type->value;
        $this->partnershipEventName = $project->partnership_event_name ?? '';
        $this->partnershipBranchIds = $project->partnershipBranches()->pluck('branches.id')->all();
        $this->createProjectModalOpen = true;
    }

    public function closeCreateProjectModal(): void
    {
        $this->resetValidation();
        $this->createProjectModalOpen = false;
    }

    public function archiveProject(int $projectId, ArchiveRndProjectAction $archiveProject): void
    {
        $project = RndProject::query()->findOrFail($projectId);
        abort_unless(ProjectResource::canDelete($project), 403);

        $archiveProject->execute($project);

        Notification::make()
            ->title('Project berhasil diarsipkan')
            ->body('Seluruh data dan attachment tetap tersimpan dan dapat dipulihkan.')
            ->success()
            ->send();
    }

    public function restoreProject(int $projectId): void
    {
        $project = RndProject::onlyTrashed()->findOrFail($projectId);
        abort_unless(ProjectResource::canRestore($project), 403);

        $project->restore();

        Notification::make()->title('Project berhasil dipulihkan')->success()->send();
    }

    public function createProject(CreateRndProjectAction $createProject, UpdateRndProjectAction $updateProject): void
    {
        $this->saveProject($createProject, $updateProject);
    }

    public function saveProject(CreateRndProjectAction $createProject, UpdateRndProjectAction $updateProject): void
    {
        $isEditing = $this->editingProjectId !== null;
        $persistedType = $isEditing
            ? RndProject::query()->findOrFail($this->editingProjectId)->project_type
            : RndProjectType::from($this->projectType);
        $validated = $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectDescription' => ['nullable', 'string'],
            'projectStartDate' => $isEditing ? ['required', 'date'] : ['nullable'],
            'projectEndDate' => $isEditing
                ? ['required', 'date', 'after_or_equal:projectStartDate']
                : ['required', 'date', 'after_or_equal:today'],
            'projectType' => $isEditing ? ['required', Rule::enum(RndProjectType::class)] : ['required', Rule::enum(RndProjectType::class)],
            'partnershipEventName' => [Rule::requiredIf($persistedType === RndProjectType::Partnership), 'nullable', 'string', 'max:255'],
            'partnershipBranchIds' => [
                Rule::requiredIf($persistedType === RndProjectType::Partnership),
                'array',
                Rule::when($persistedType === RndProjectType::Partnership, ['min:1']),
            ],
            'partnershipBranchIds.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('is_active', true)],
        ]);

        $type = RndProjectType::from($validated['projectType']);

        $projectData = [
            'name' => trim($validated['projectName']),
            'description' => trim($validated['projectDescription']) ?: null,
            'start_date' => $isEditing ? $validated['projectStartDate'] : today()->toDateString(),
            'end_date' => $validated['projectEndDate'],
            'partnership_event_name' => $validated['partnershipEventName'] ?? null,
            'partnership_branch_ids' => $persistedType === RndProjectType::Partnership
                ? collect($validated['partnershipBranchIds'])->map(fn ($branchId): int => (int) $branchId)->all()
                : [],
        ];

        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        if ($this->editingProjectId !== null) {
            $project = RndProject::query()->findOrFail($this->editingProjectId);
            abort_unless(ProjectResource::canEdit($project), 403);
            $project = $updateProject->execute($actor, $project, $projectData);
            $notificationTitle = 'Project berhasil diperbarui';
        } else {
            abort_unless(ProjectResource::canCreate(), 403);
            $project = $createProject->execute($actor, $projectData + ['project_type' => $type]);
            $notificationTitle = 'Project berhasil dibuat';
        }

        $this->createProjectModalOpen = false;
        Notification::make()->title($notificationTitle)->success()->send();

        if ($this->editingProjectId === null) {
            $this->redirect(ProjectResource::getUrl('view', ['record' => $project]), navigate: true);
        }
    }
}
