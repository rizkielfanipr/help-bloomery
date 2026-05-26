<?php

namespace App\Filament\Helpdesk\Resources\CasualShifts\Pages;

use App\Filament\Helpdesk\Resources\CasualShifts\CasualShiftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCasualShift extends EditRecord
{
    protected static string $resource = CasualShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
