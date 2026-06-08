<?php

namespace App\Filament\Helpdesk\Resources\CasualRegistrationTokens\Pages;

use App\Filament\Helpdesk\Resources\CasualRegistrationTokens\CasualRegistrationTokenResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCasualRegistrationTokens extends ListRecords
{
    protected static string $resource = CasualRegistrationTokenResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Token Registrasi';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola token registrasi untuk staff casual baru';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
