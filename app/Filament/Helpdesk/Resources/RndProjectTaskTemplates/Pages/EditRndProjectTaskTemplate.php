<?php

namespace App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages;

use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\RndProjectTaskTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRndProjectTaskTemplate extends EditRecord
{
    protected static string $resource = RndProjectTaskTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
