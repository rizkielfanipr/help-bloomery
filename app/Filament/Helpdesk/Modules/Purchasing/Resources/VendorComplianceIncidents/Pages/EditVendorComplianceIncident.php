<?php

namespace App\Filament\Helpdesk\Modules\Purchasing\Resources\VendorComplianceIncidents\Pages;

use App\Filament\Helpdesk\Modules\Purchasing\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorComplianceIncident extends EditRecord
{
    protected static string $resource = VendorComplianceIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['handled_by'] = auth()->id();
        $data['resolved_at'] = $data['status'] === 'resolved' ? ($this->record->resolved_at ?? now()) : null;

        return $data;
    }
}
