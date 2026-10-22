<?php

namespace App\Filament\Helpdesk\Resources\SalesReportVoids\Pages;

use App\Filament\Helpdesk\Resources\SalesReports\Concerns\HasSalesReportSubNavigation;
use App\Filament\Helpdesk\Resources\SalesReportVoids\SalesReportVoidResource;
use Filament\Resources\Pages\ListRecords;

class ListSalesReportVoids extends ListRecords
{
    use HasSalesReportSubNavigation;

    protected static string $resource = SalesReportVoidResource::class;

    protected ?string $subheading = 'Satu baris per Void untuk dibandingkan dengan laporan ESB.';
}
