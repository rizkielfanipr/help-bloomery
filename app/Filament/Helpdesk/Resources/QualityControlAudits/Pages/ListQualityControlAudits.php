<?php

namespace App\Filament\Helpdesk\Resources\QualityControlAudits\Pages;

use App\Filament\Helpdesk\Resources\QualityControlAudits\QualityControlAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListQualityControlAudits extends ListRecords
{
    protected static string $resource = QualityControlAuditResource::class;
}
