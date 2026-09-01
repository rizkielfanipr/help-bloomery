<?php

namespace App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\Pages;

use App\Enums\MarketingMaterialFulfillmentStatus;
use App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\MarketingMaterialFulfillmentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListMarketingMaterialFulfillmentsToReceive extends ListRecords
{
    protected static string $resource = MarketingMaterialFulfillmentResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Perlu Diterima (Inventory)';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        // Inventory's queue: materials Purchasing has already ordered but
        // that haven't been marked received/stocked yet.
        return parent::getTableQuery()
            ->whereHas('fulfillment', fn (Builder $query) => $query->where('status', MarketingMaterialFulfillmentStatus::Ordered));
    }
}
