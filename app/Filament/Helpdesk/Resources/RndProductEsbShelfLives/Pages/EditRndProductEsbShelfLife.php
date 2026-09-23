<?php

namespace App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages;

use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\RndProductEsbShelfLifeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRndProductEsbShelfLife extends EditRecord
{
    protected static string $resource = RndProductEsbShelfLifeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
