<?php

namespace App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\Pages;

use App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\TechnicianMaintenanceChecklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTechnicianMaintenanceChecklists extends ListRecords
{
    protected static string $resource = TechnicianMaintenanceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
