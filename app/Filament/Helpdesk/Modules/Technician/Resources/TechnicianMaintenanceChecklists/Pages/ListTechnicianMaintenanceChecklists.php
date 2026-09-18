<?php

namespace App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMaintenanceChecklists\Pages;

use App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMaintenanceChecklists\TechnicianMaintenanceChecklistResource;
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
