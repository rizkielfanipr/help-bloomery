<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions\Pages;

use App\Filament\Helpdesk\Resources\CasualPositions\CasualPositionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCasualPosition extends EditRecord
{
    protected static string $resource = CasualPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
