<?php

namespace App\Filament\Helpdesk\Resources\PrefixNames\Pages;

use App\Filament\Helpdesk\Resources\PrefixNames\PrefixNameResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrefixName extends EditRecord
{
    protected static string $resource = PrefixNameResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
