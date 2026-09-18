<?php

namespace App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlAudits\Pages;

use App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlAudits\QualityControlAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListQualityControlAudits extends ListRecords
{
    protected static string $resource = QualityControlAuditResource::class;
}
