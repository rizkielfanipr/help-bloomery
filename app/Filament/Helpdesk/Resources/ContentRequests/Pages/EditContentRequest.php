<?php

namespace App\Filament\Helpdesk\Resources\ContentRequests\Pages;

use App\Filament\Helpdesk\Resources\ContentRequests\ContentRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentRequest extends EditRecord
{
    protected static string $resource = ContentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
