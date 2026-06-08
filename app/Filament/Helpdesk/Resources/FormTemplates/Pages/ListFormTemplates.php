<?php

namespace App\Filament\Helpdesk\Resources\FormTemplates\Pages;

use App\Filament\Helpdesk\Resources\FormTemplates\FormTemplateResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListFormTemplates extends ListRecords
{
    protected static string $resource = FormTemplateResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Template Form';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola template formulir untuk helpdesk';
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
