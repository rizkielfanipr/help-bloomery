<?php

namespace App\Filament\Helpdesk\Resources\SalesReportCompliments\Pages;

use App\Filament\Helpdesk\Resources\SalesReportCompliments\SalesReportComplimentResource;
use App\Filament\Helpdesk\Resources\SalesReports\Concerns\HasSalesReportSubNavigation;
use Filament\Resources\Pages\ListRecords;

class ListSalesReportCompliments extends ListRecords
{
    use HasSalesReportSubNavigation;

    protected static string $resource = SalesReportComplimentResource::class;

    protected ?string $subheading = 'Satu baris per Compliment untuk dibandingkan dengan laporan ESB.';
}
