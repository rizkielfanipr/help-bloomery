<?php

namespace App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages;

use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\RndProjectTaskTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRndProjectTaskTemplate extends CreateRecord
{
    protected static string $resource = RndProjectTaskTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
