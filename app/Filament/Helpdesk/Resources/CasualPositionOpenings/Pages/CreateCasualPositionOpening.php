<?php

namespace App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages;

use App\Filament\Helpdesk\Resources\CasualPositionOpenings\CasualPositionOpeningResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateCasualPositionOpening extends CreateRecord
{
    protected static string $resource = CasualPositionOpeningResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Buat Lowongan Baru';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['posted_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return CasualPositionOpeningResource::getUrl('index');
    }
}
