<?php

namespace App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages;

use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\RndProductEsbShelfLifeResource;
use App\Models\RndInternalMemo;
use Filament\Resources\Pages\CreateRecord;

class CreateRndProductEsbShelfLife extends CreateRecord
{
    protected static string $resource = RndProductEsbShelfLifeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_code'] ??= RndInternalMemo::COMPANY_CODE;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
