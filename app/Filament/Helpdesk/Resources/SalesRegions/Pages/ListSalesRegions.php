<?php

namespace App\Filament\Helpdesk\Resources\SalesRegions\Pages;

use App\Filament\Helpdesk\Resources\SalesRegions\SalesRegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesRegions extends ListRecords
{
    protected static string $resource = SalesRegionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Region')];
    }
}
