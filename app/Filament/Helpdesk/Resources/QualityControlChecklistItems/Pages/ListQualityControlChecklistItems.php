<?php

namespace App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Pages;

use App\Filament\Helpdesk\Resources\QualityControlChecklistItems\QualityControlChecklistItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQualityControlChecklistItems extends ListRecords
{
    protected static string $resource = QualityControlChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
