<?php

namespace App\Filament\Helpdesk\Resources\CasualRegistrationTokens\Pages;

use App\Filament\Helpdesk\Resources\CasualRegistrationTokens\CasualRegistrationTokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCasualRegistrationTokens extends ListRecords
{
    protected static string $resource = CasualRegistrationTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat Token Baru')];
    }
}
