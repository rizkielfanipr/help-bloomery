<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesReport extends EditRecord
{
    protected static string $resource = SalesReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
