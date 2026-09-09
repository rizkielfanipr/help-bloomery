<?php

namespace App\Filament\Helpdesk\Resources\Assets\Pages;

use App\Filament\Helpdesk\Resources\Assets\AssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
