<?php

namespace App\Filament\Helpdesk\Resources\StoreSalesOrders\Pages;

use App\Filament\Helpdesk\Resources\StoreSalesOrders\StoreSalesOrderResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListStoreSalesOrders extends ListRecords
{
    protected static string $resource = StoreSalesOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Store Sales Orders';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau Store Sales Order sesuai cakupan branch Anda.';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
