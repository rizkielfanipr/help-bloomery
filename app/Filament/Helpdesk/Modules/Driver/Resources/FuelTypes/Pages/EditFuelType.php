<?php

namespace App\Filament\Helpdesk\Modules\Driver\Resources\FuelTypes\Pages;

use App\Filament\Helpdesk\Modules\Driver\Resources\FuelTypes\FuelTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelType extends EditRecord
{
    protected static string $resource = FuelTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
