<?php

namespace App\Filament\Helpdesk\Resources\ContentRequests\Pages;

use App\Filament\Helpdesk\Resources\ContentRequests\ContentRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListContentRequests extends ListRecords
{
    protected static string $resource = ContentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
