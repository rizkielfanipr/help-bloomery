<?php

namespace App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMonthlyMaintenanceResource\Pages;

use App\Filament\Helpdesk\Modules\Technician\Resources\TechnicianMonthlyMaintenanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTechnicianMonthlyMaintenance extends CreateRecord
{
    protected static string $resource = TechnicianMonthlyMaintenanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['technician_id'] ??= auth()->id();

        return $data;
    }
}
