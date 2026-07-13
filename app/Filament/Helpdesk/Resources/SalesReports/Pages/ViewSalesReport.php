<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Models\SalesReport;
use Filament\Resources\Pages\Page;

class ViewSalesReport extends Page
{
    protected static string $resource = SalesReportResource::class;

    protected string $view = 'filament.helpdesk.sales-reports.view';

    public SalesReport $record;

    public function mount(SalesReport $record): void
    {
        $this->record = $record->load(['branch', 'submittedBy', 'entries']);
    }

    public function getTitle(): string
    {
        return 'Sales Report — '.$this->record->branch->name.' '.$this->record->report_date->isoFormat('D MMMM Y');
    }
}
