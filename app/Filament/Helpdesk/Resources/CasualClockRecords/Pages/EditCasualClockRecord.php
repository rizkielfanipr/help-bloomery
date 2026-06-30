<?php

namespace App\Filament\Helpdesk\Resources\CasualClockRecords\Pages;

use App\Filament\Helpdesk\Resources\CasualClockRecords\CasualClockRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCasualClockRecord extends EditRecord
{
    protected static string $resource = CasualClockRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
