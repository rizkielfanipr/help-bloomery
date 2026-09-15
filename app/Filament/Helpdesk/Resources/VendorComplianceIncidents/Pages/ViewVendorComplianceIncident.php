<?php

namespace App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages;

use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
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
