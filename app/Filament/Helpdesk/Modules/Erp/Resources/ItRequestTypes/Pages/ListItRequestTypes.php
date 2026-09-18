<?php

namespace App\Filament\Helpdesk\Modules\Erp\Resources\ItRequestTypes\Pages;

use App\Filament\Helpdesk\Modules\Erp\Resources\ItRequestTypes\ItRequestTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItRequestTypes extends ListRecords
{
    protected static string $resource = ItRequestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
