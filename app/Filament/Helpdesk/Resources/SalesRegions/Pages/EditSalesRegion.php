<?php

namespace App\Filament\Helpdesk\Resources\SalesRegions\Pages;

use App\Filament\Helpdesk\Resources\SalesRegions\SalesRegionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesRegion extends EditRecord
{
    protected static string $resource = SalesRegionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
