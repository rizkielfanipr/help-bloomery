<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions\Pages;

use App\Filament\Helpdesk\Resources\CasualPositions\CasualPositionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCasualPosition extends ViewRecord
{
    protected static string $resource = CasualPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
