<?php

namespace App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages;

use App\Filament\Helpdesk\Resources\HelpdeskRequests\HelpdeskRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHelpdeskRequests extends ListRecords
{
    protected static string $resource = HelpdeskRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Permintaan'),
        ];
    }
}
