<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use App\Filament\Helpdesk\Widgets\ErpRequestSlaStatsWidget;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListErpRepairRequests extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ErpRepairRequestResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ErpRequestSlaStatsWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
