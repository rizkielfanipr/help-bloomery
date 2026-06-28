<?php

namespace App\Filament\Helpdesk\Resources\DesignRequests\Pages;

use App\Filament\Helpdesk\Resources\DesignRequests\DesignRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDesignRequest extends ViewRecord
{
    protected static string $resource = DesignRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
