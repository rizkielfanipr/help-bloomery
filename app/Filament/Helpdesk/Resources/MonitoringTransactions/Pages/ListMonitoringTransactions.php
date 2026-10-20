<?php

namespace App\Filament\Helpdesk\Resources\MonitoringTransactions\Pages;

use App\Filament\Helpdesk\Resources\MonitoringTransactions\MonitoringTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListMonitoringTransactions extends ListRecords
{
    protected static string $resource = MonitoringTransactionResource::class;

    protected ?string $subheading = 'Jejak seluruh mutation ESB dari UI. Status unknown wajib direkonsiliasi, tidak pernah dicoba ulang otomatis.';
}
