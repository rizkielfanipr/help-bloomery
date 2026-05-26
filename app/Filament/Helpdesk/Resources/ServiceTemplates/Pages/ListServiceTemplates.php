<?php

namespace App\Filament\Helpdesk\Resources\ServiceTemplates\Pages;

use App\Filament\Helpdesk\Resources\ServiceTemplates\ServiceTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceTemplates extends ListRecords
{
    protected static string $resource = ServiceTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
