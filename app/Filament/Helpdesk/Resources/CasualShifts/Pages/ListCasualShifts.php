<?php

namespace App\Filament\Helpdesk\Resources\CasualShifts\Pages;

use App\Filament\Helpdesk\Resources\CasualShifts\CasualShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCasualShifts extends ListRecords
{
    protected static string $resource = CasualShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
