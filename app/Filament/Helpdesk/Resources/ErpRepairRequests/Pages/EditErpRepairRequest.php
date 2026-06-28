<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditErpRepairRequest extends EditRecord
{
    protected static string $resource = ErpRepairRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
