<?php

namespace App\Filament\Driver\Resources\DriverLogs\Pages;

use App\Filament\Driver\Resources\DriverLogs\DriverLogResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDriverLog extends EditRecord
{
    protected static string $resource = DriverLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
