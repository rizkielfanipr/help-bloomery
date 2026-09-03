<?php

namespace App\Filament\Helpdesk\Resources\ComplimentTypes\Pages;

use App\Filament\Helpdesk\Resources\ComplimentTypes\ComplimentTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComplimentType extends EditRecord
{
    protected static string $resource = ComplimentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
