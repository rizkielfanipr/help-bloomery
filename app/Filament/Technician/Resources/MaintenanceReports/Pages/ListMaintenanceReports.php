<?php

namespace App\Filament\Technician\Resources\MaintenanceReports\Pages;

use App\Filament\Technician\Resources\MaintenanceReports\MaintenanceReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceReports extends ListRecords
{
    protected static string $resource = MaintenanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
