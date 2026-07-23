<?php

namespace App\Filament\Helpdesk\Resources\Projects\Pages;

use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Project'),
        ];
    }
}
