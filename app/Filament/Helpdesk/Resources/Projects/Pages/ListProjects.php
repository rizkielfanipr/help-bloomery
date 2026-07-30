<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.helpdesk.rnd-projects.index';

    public string $projectSearch = '';

    public string $projectStatus = '';

    public function projects(): Collection
    {
        return \App\Models\RndProject::query()
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
        return [
            CreateAction::make()->label('Buat Project'),
        ];
    }
}
