<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Concerns;

use App\Filament\Helpdesk\Resources\SalesReportCompliments\Pages\ListSalesReportCompliments;
use App\Filament\Helpdesk\Resources\SalesReportCompliments\SalesReportComplimentResource;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Filament\Helpdesk\Resources\SalesReportVoids\Pages\ListSalesReportVoids;
use App\Filament\Helpdesk\Resources\SalesReportVoids\SalesReportVoidResource;
use Filament\Navigation\NavigationItem;

/**
 * One Sales Report area with three tabs — Laporan Harian, Compliment, Void — instead of three
 * unrelated sidebar menus (docs/products/finance/sales-report-compliment-void-remediation/prd.md §15.1).
 */
trait HasSalesReportSubNavigation
{
    /** @return array<NavigationItem> */
    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make('Laporan Harian')
                ->icon('heroicon-o-document-chart-bar')
                ->url(SalesReportResource::getUrl('index'))
                ->isActiveWhen(fn (): bool => $this instanceof ListSalesReports),
            NavigationItem::make('Compliment')
                ->icon('heroicon-o-gift')
                ->url(SalesReportComplimentResource::getUrl('index'))
                ->visible(SalesReportComplimentResource::canViewAny())
                ->isActiveWhen(fn (): bool => $this instanceof ListSalesReportCompliments),
            NavigationItem::make('Void')
                ->icon('heroicon-o-receipt-refund')
                ->url(SalesReportVoidResource::getUrl('index'))
                ->visible(SalesReportVoidResource::canViewAny())
                ->isActiveWhen(fn (): bool => $this instanceof ListSalesReportVoids),
        ];
    }
}
