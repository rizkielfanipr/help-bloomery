<?php

namespace App\Filament\Helpdesk\Resources\ServiceTemplates\Pages;

use App\Filament\Helpdesk\Resources\ServiceTemplates\ServiceTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceTemplate extends EditRecord
{
    protected static string $resource = ServiceTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
