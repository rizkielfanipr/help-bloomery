<?php

namespace App\Filament\Helpdesk\Modules\StoreSop\Resources\StoreSopCategories\Pages;

use App\Filament\Helpdesk\Modules\StoreSop\Resources\StoreSopCategories\StoreSopCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreSopCategories extends ListRecords
{
    protected static string $resource = StoreSopCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
