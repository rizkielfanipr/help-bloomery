<?php

namespace App\Filament\Helpdesk\Resources\TechnicianMonthlyMaintenanceResource\Pages;

use App\Filament\Helpdesk\Resources\TechnicianMonthlyMaintenanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTechnicianMonthlyMaintenances extends ListRecords
{
    protected static string $resource = TechnicianMonthlyMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
