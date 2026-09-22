<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Actions\ArchiveRndProjectAction;
use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Models\RndProject;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.helpdesk.rnd-projects.index';

    public string $projectSearch = '';

    public string $projectStatus = '';

    public string $projectView = 'list';

    public string $calendarMonth = '';

    public bool $createProjectModalOpen = false;

    public ?int $editingProjectId = null;

    public string $projectName = '';

    public string $projectDescription = '';

    public string $projectStartDate = '';

    public string $projectEndDate = '';

    public function projects(): Collection
    {
        return RndProject::query()
            ->when($this->projectStatus === 'archived', fn ($query) => $query->onlyTrashed())
            ->withCount('products')
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
        $this->reset(['projectName', 'projectDescription', 'projectStartDate', 'projectEndDate']);
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

    public function createProject(): void
    {
        $this->saveProject();
    }

    public function saveProject(): void
    {
        $isEditing = $this->editingProjectId !== null;
        $validated = $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectDescription' => ['nullable', 'string'],
            'projectStartDate' => $isEditing ? ['required', 'date'] : ['nullable'],
            'projectEndDate' => $isEditing
                ? ['required', 'date', 'after_or_equal:projectStartDate']
                : ['required', 'date', 'after_or_equal:today'],
        ]);

        $projectData = [
            'name' => trim($validated['projectName']),
            'description' => trim($validated['projectDescription']) ?: null,
            'start_date' => $isEditing ? $validated['projectStartDate'] : today()->toDateString(),
            'end_date' => $validated['projectEndDate'],
        ];

        if ($this->editingProjectId !== null) {
            $project = RndProject::query()->findOrFail($this->editingProjectId);
            abort_unless(ProjectResource::canEdit($project), 403);
            $project->update($projectData);
            $notificationTitle = 'Project berhasil diperbarui';
        } else {
            abort_unless(ProjectResource::canCreate(), 403);
            $project = RndProject::query()->create($projectData + ['created_by' => auth()->id()]);
            $notificationTitle = 'Project berhasil dibuat';
        }

        $this->createProjectModalOpen = false;
        Notification::make()->title($notificationTitle)->success()->send();

        if ($this->editingProjectId === null) {
            $this->redirect(ProjectResource::getUrl('view', ['record' => $project]), navigate: true);
        }
    }
}
