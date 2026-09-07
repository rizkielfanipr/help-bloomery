<?php

namespace App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\Pages;

use App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\TechnicianMaintenanceChecklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTechnicianMaintenanceChecklist extends CreateRecord
{
    protected static string $resource = TechnicianMaintenanceChecklistResource::class;
}
