<?php

namespace App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages;

use App\Filament\Helpdesk\Resources\HelpdeskRequests\HelpdeskRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHelpdeskRequest extends EditRecord
{
    protected static string $resource = HelpdeskRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
