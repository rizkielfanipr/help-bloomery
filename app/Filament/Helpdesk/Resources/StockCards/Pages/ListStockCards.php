<?php

namespace App\Filament\Helpdesk\Resources\StockCards\Pages;

use App\Enums\StockCardStatus;
use App\Filament\Helpdesk\Resources\StockCards\StockCardResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockCards extends ListRecords
{
    protected static string $resource = StockCardResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending_supervisor' => Tab::make('Supervisor Review')
                ->badge(fn (): int => $this->statusCount(StockCardStatus::PendingSupervisor))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StockCardStatus::PendingSupervisor->value)),
            'pending_finance' => Tab::make('Finance Review')
                ->badge(fn (): int => $this->statusCount(StockCardStatus::PendingFinance))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StockCardStatus::PendingFinance->value)),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StockCardStatus::Completed->value)),
        ];
    }

    private function statusCount(StockCardStatus $status): int
    {
        return StockCardResource::getEloquentQuery()->where('status', $status->value)->count();
    }
}
