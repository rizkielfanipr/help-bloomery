<?php

namespace App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages;

use App\Filament\Helpdesk\Resources\CasualPositionOpenings\CasualPositionOpeningResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditCasualPositionOpening extends EditRecord
{
    protected static string $resource = CasualPositionOpeningResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Edit Lowongan';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return CasualPositionOpeningResource::getUrl('index');
    }
}
