<?php

namespace App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Pages;

use App\Filament\Helpdesk\Resources\QualityControlChecklistItems\QualityControlChecklistItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQualityControlChecklistItem extends CreateRecord
{
    protected static string $resource = QualityControlChecklistItemResource::class;
}
