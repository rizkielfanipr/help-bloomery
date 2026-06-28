<?php

namespace App\Filament\Helpdesk\Resources\DesignRequests\Pages;

use App\Filament\Helpdesk\Resources\DesignRequests\DesignRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDesignRequests extends ListRecords
{
    protected static string $resource = DesignRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
