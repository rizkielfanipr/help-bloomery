<?php

namespace App\Filament\Helpdesk\Modules\Technician\Resources\Assets\Pages;

use App\Filament\Helpdesk\Modules\Technician\Resources\Assets\AssetResource;
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
