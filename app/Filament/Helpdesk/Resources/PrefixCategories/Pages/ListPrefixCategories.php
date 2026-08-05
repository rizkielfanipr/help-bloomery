<?php

namespace App\Filament\Helpdesk\Resources\PrefixCategories\Pages;

use App\Filament\Helpdesk\Resources\PrefixCategories\PrefixCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrefixCategories extends ListRecords
{
    protected static string $resource = PrefixCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
