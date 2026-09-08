<?php

namespace App\Filament\Helpdesk\Resources\StoreSops\Pages;

use App\Filament\Helpdesk\Resources\StoreSops\StoreSopResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreSop extends EditRecord
{
    protected static string $resource = StoreSopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
