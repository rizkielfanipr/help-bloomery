<?php

namespace App\Filament\Helpdesk\Resources\DesignRequests\Pages;

use App\Filament\Helpdesk\Resources\DesignRequests\DesignRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDesignRequest extends EditRecord
{
    protected static string $resource = DesignRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
