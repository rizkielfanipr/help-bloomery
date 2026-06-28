<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewErpRepairRequest extends ViewRecord
{
    protected static string $resource = ErpRepairRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
