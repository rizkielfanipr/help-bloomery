<?php

namespace App\Filament\Helpdesk\Resources\PrefixCategories\Pages;

use App\Filament\Helpdesk\Resources\PrefixCategories\PrefixCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrefixCategory extends EditRecord
{
    protected static string $resource = PrefixCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
