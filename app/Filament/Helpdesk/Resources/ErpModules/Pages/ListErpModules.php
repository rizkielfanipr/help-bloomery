<?php

namespace App\Filament\Helpdesk\Resources\ErpModules\Pages;

use App\Filament\Helpdesk\Resources\ErpModules\ErpModuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListErpModules extends ListRecords
{
    protected static string $resource = ErpModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
