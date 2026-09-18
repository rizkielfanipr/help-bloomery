<?php

namespace App\Filament\Helpdesk\Modules\Casual\Resources\CasualClockRecords\Pages;

use App\Filament\Helpdesk\Modules\Casual\Resources\CasualClockRecords\CasualClockRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCasualClockRecord extends CreateRecord
{
    protected static string $resource = CasualClockRecordResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
