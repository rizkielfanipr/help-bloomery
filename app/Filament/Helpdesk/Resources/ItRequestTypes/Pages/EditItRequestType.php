<?php

namespace App\Filament\Helpdesk\Resources\ItRequestTypes\Pages;

use App\Filament\Helpdesk\Resources\ItRequestTypes\ItRequestTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItRequestType extends EditRecord
{
    protected static string $resource = ItRequestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
