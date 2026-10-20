<?php

namespace App\Filament\Helpdesk\Resources\MonitoringDeployments\Pages;

use App\Filament\Helpdesk\Resources\MonitoringDeployments\MonitoringDeploymentResource;
use Filament\Resources\Pages\ListRecords;

class ListMonitoringDeployments extends ListRecords
{
    protected static string $resource = MonitoringDeploymentResource::class;

    protected ?string $subheading = 'Read-only. Deploy dan rollback tidak dilakukan dari System Monitoring.';
}
