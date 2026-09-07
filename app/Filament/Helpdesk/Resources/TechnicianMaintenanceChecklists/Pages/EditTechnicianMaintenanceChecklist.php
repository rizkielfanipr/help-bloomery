<?php

namespace App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\Pages;

use App\Filament\Helpdesk\Resources\TechnicianMaintenanceChecklists\TechnicianMaintenanceChecklistResource;
use Filament\Resources\Pages\EditRecord;

class EditTechnicianMaintenanceChecklist extends EditRecord
{
    protected static string $resource = TechnicianMaintenanceChecklistResource::class;
}
