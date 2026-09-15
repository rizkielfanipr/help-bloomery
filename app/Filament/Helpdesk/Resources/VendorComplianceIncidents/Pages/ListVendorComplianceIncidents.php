<?php

namespace App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages;

use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorComplianceIncidents extends ListRecords
{
    protected static string $resource = VendorComplianceIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
