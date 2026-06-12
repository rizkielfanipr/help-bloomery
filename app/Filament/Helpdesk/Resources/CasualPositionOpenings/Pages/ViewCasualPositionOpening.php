<?php

namespace App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages;

use App\Filament\Helpdesk\Resources\CasualPositionOpenings\CasualPositionOpeningResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCasualPositionOpening extends ViewRecord
{
    protected static string $resource = CasualPositionOpeningResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Lowongan';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
