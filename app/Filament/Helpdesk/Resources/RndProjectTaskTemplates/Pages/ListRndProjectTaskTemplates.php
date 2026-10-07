<?php

namespace App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\Pages;

use App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\RndProjectTaskTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRndProjectTaskTemplates extends ListRecords
{
    protected static string $resource = RndProjectTaskTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
