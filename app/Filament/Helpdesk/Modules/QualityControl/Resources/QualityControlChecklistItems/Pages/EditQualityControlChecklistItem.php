<?php

namespace App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlChecklistItems\Pages;

use App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlChecklistItems\QualityControlChecklistItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQualityControlChecklistItem extends EditRecord
{
    protected static string $resource = QualityControlChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
