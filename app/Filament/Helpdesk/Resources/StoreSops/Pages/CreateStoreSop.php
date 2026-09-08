<?php

namespace App\Filament\Helpdesk\Resources\StoreSops\Pages;

use App\Filament\Helpdesk\Resources\StoreSops\StoreSopResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreSop extends CreateRecord
{
    protected static string $resource = StoreSopResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        return $data;
    }
}
