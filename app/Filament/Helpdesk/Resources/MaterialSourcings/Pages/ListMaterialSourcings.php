<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcings\Pages;

use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use Filament\Resources\Pages\ListRecords;

class ListMaterialSourcings extends ListRecords
{
    protected static string $resource = MaterialSourcingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
