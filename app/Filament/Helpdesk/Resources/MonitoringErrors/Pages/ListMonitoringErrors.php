<?php

namespace App\Filament\Helpdesk\Resources\MonitoringErrors\Pages;

use App\Filament\Helpdesk\Resources\MonitoringErrors\MonitoringErrorResource;
use Filament\Resources\Pages\ListRecords;

class ListMonitoringErrors extends ListRecords
{
    protected static string $resource = MonitoringErrorResource::class;

    protected ?string $subheading = 'Error identik digabung per fingerprint. Validasi dan error otorisasi normal tidak dicatat.';
}
