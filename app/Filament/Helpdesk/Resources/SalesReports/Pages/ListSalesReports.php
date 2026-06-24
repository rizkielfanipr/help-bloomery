<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use Filament\Resources\Pages\ListRecords;

class ListSalesReports extends ListRecords
{
    protected static string $resource = SalesReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
