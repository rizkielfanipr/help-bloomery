<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions\Pages;

use App\Filament\Helpdesk\Resources\CasualPositions\CasualPositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCasualPositions extends ListRecords
{
    protected static string $resource = CasualPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
