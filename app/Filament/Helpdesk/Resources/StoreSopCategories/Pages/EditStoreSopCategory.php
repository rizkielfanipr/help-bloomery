<?php

namespace App\Filament\Helpdesk\Resources\StoreSopCategories\Pages;

use App\Filament\Helpdesk\Resources\StoreSopCategories\StoreSopCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreSopCategory extends EditRecord
{
    protected static string $resource = StoreSopCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
