<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListErpRepairRequests extends ListRecords
{
    protected static string $resource = ErpRepairRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
