<?php

namespace App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlChecklistItems\Pages;

use App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlChecklistItems\QualityControlChecklistItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQualityControlChecklistItem extends CreateRecord
{
    protected static string $resource = QualityControlChecklistItemResource::class;
}
