<?php

namespace App\Filament\Technician\Resources\TechnicianMonthlyMaintenanceResource\Pages;

use App\Filament\Technician\Resources\TechnicianMonthlyMaintenanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTechnicianMonthlyMaintenance extends CreateRecord
{
    protected static string $resource = TechnicianMonthlyMaintenanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Automatically set the branch_id to the technician's branch
        $data['branch_id'] = auth()->user()->branch_id;

        return $data;
    }
}
