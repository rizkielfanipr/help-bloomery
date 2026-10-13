<?php

namespace App\Filament\Helpdesk\Resources\BriefingBundles\Pages;

use App\Filament\Helpdesk\Resources\BriefingBundles\BriefingBundleResource;
use Filament\Resources\Pages\ListRecords;

class ListBriefingBundles extends ListRecords
{
    protected static string $resource = BriefingBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
