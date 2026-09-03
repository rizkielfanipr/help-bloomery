<?php

namespace App\Filament\Helpdesk\Resources\ComplimentTypes\Pages;

use App\Filament\Helpdesk\Resources\ComplimentTypes\ComplimentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComplimentTypes extends ListRecords
{
    protected static string $resource = ComplimentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
