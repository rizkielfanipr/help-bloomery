<?php

namespace App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages;

use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\RndProductEsbShelfLifeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRndProductEsbShelfLives extends ListRecords
{
    protected static string $resource = RndProductEsbShelfLifeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
