<?php

namespace App\Filament\Technician\Resources\MaintenanceReports\Pages;

use App\Filament\Technician\Resources\MaintenanceReports\MaintenanceReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceReport extends CreateRecord
{
    protected static string $resource = MaintenanceReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['technician_id'] = auth()->id();

        return $data;
    }
}
