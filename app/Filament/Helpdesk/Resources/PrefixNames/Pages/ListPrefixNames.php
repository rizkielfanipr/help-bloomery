<?php

namespace App\Filament\Helpdesk\Resources\PrefixNames\Pages;

use App\Filament\Helpdesk\Resources\PrefixNames\PrefixNameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrefixNames extends ListRecords
{
    protected static string $resource = PrefixNameResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
