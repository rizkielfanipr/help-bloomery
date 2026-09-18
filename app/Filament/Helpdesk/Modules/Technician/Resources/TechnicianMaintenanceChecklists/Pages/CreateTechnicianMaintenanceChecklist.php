<?php

namespace App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMaintenanceChecklists\Pages;

use App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMaintenanceChecklists\TechnicianMaintenanceChecklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTechnicianMaintenanceChecklist extends CreateRecord
{
    protected static string $resource = TechnicianMaintenanceChecklistResource::class;
}
