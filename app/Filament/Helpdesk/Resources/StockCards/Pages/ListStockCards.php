<?php

namespace App\Filament\Helpdesk\Resources\StockCards\Pages;

use App\Filament\Helpdesk\Resources\StockCards\StockCardResource;
use Filament\Resources\Pages\ListRecords;

class ListStockCards extends ListRecords
{
    protected static string $resource = StockCardResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
