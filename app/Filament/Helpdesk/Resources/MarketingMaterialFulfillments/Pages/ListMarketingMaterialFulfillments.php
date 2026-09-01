<?php

namespace App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\Pages;

use App\Enums\MarketingMaterialFulfillmentStatus;
use App\Filament\Helpdesk\Resources\MarketingMaterialFulfillments\MarketingMaterialFulfillmentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListMarketingMaterialFulfillments extends ListRecords
{
    protected static string $resource = MarketingMaterialFulfillmentResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Perlu Dipesan (Purchasing)';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        // Purchasing's queue: materials that haven't been marked ordered yet —
        // either no fulfillment row exists at all, or one exists but is still
        // sitting at NotStarted (defensive; the mark_ordered action always
        // moves it to Ordered in the same update, so this case is rare).
        return parent::getTableQuery()->where(function (Builder $query): void {
            $query->whereDoesntHave('fulfillment')
                ->orWhereHas('fulfillment', fn (Builder $q) => $q->where('status', MarketingMaterialFulfillmentStatus::NotStarted));
        });
    }
}
