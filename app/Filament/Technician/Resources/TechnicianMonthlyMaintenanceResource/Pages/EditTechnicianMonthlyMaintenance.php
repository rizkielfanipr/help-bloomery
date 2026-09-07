<?php

namespace App\Filament\Technician\Resources\TechnicianMonthlyMaintenanceResource\Pages;

use App\Filament\Technician\Resources\TechnicianMonthlyMaintenanceResource;
use Filament\Resources\Pages\EditRecord;

class EditTechnicianMonthlyMaintenance extends EditRecord
{
    protected static string $resource = TechnicianMonthlyMaintenanceResource::class;

    protected function mutateFormDataBeforeEdit(array $data): array
    {
        // Ensure the branch_id is set to the technician's branch (should already be, but just in case)
        $data['branch_id'] = auth()->user()->branch_id;

        return $data;
    }
}
