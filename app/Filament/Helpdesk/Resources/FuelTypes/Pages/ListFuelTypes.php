<?php

namespace App\Filament\Helpdesk\Resources\FuelTypes\Pages;

use App\Filament\Helpdesk\Resources\FuelTypes\FuelTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuelTypes extends ListRecords
{
    protected static string $resource = FuelTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
