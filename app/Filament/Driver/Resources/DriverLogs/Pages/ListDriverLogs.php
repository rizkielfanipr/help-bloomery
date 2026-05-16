<?php

namespace App\Filament\Driver\Resources\DriverLogs\Pages;

use App\Filament\Driver\Resources\DriverLogs\DriverLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDriverLogs extends ListRecords
{
    protected static string $resource = DriverLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
