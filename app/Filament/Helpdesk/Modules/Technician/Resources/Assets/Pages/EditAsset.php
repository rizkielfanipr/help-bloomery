<?php

namespace App\Filament\Helpdesk\Modules\Technician\Resources\Assets\Pages;

use App\Filament\Helpdesk\Modules\Technician\Resources\Assets\AssetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
