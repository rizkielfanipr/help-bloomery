<?php

namespace App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMaintenanceChecklists\Pages;

use App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMaintenanceChecklists\TechnicianMaintenanceChecklistResource;
use Filament\Resources\Pages\EditRecord;

class EditTechnicianMaintenanceChecklist extends EditRecord
{
    protected static string $resource = TechnicianMaintenanceChecklistResource::class;
}
