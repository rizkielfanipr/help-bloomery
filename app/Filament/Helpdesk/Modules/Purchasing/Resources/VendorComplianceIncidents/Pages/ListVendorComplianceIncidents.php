<?php

namespace App\Filament\Helpdesk\Modules\Purchasing\Resources\VendorComplianceIncidents\Pages;

use App\Filament\Helpdesk\Modules\Purchasing\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorComplianceIncidents extends ListRecords
{
    protected static string $resource = VendorComplianceIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
