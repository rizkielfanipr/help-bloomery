<?php

namespace App\Filament\Helpdesk\Modules\Purchasing\Resources\VendorComplianceIncidents\Pages;

use App\Filament\Helpdesk\Modules\Purchasing\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorComplianceIncident extends ViewRecord
{
    protected static string $resource = VendorComplianceIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
