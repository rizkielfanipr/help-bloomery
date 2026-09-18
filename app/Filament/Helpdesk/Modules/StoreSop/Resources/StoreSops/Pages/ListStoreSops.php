<?php

namespace App\Filament\Helpdesk\Modules\StoreSop\Resources\StoreSops\Pages;

use App\Filament\Helpdesk\Modules\StoreSop\Resources\StoreSops\StoreSopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreSops extends ListRecords
{
    protected static string $resource = StoreSopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
