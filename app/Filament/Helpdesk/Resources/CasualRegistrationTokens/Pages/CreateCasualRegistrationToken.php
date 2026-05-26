<?php

namespace App\Filament\Helpdesk\Resources\CasualRegistrationTokens\Pages;

use App\Filament\Helpdesk\Resources\CasualRegistrationTokens\CasualRegistrationTokenResource;
use App\Models\CasualRegistrationToken;
use Filament\Resources\Pages\CreateRecord;

class CreateCasualRegistrationToken extends CreateRecord
{
    protected static string $resource = CasualRegistrationTokenResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = CasualRegistrationToken::generate();
        $data['created_by'] = auth()->id();

        return $data;
    }
}
