<?php

namespace App\Filament\Driver\Resources\DriverLogs\Pages;

use App\Filament\Driver\Resources\DriverLogs\DriverLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDriverLog extends CreateRecord
{
    protected static string $resource = DriverLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['driver_id'] = auth()->id();

        return $data;
    }
}
