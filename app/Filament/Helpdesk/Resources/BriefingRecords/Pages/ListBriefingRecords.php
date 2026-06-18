<?php

namespace App\Filament\Helpdesk\Resources\BriefingRecords\Pages;

use App\Filament\Helpdesk\Resources\BriefingRecords\BriefingRecordResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListBriefingRecords extends ListRecords
{
    protected static string $resource = BriefingRecordResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Daily Briefing';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau dan kelola checklist briefing staff';
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
