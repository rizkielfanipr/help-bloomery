<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Models\RndProject;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.helpdesk.rnd-projects.index';

    public string $projectSearch = '';

    public string $projectStatus = '';

    public bool $createProjectModalOpen = false;

    public ?int $editingProjectId = null;

    public string $projectName = '';

    public string $projectDescription = '';

    public string $projectStartDate = '';

    public string $projectEndDate = '';

    public function projects(): Collection
    {
        return RndProject::query()
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

    public function createProject(): void
    {
        $this->saveProject();
    }

    public function saveProject(): void
    {
        $validated = $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectDescription' => ['nullable', 'string'],
            'projectStartDate' => ['required', 'date'],
            'projectEndDate' => ['required', 'date', 'after_or_equal:projectStartDate'],
        ]);

        $projectData = [
            'name' => trim($validated['projectName']),
            'description' => trim($validated['projectDescription']) ?: null,
            'start_date' => $validated['projectStartDate'],
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
