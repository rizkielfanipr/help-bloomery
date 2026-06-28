<?php

namespace App\Filament\Helpdesk\Resources\DesignCategories\Pages;

use App\Filament\Helpdesk\Resources\DesignCategories\DesignCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDesignCategory extends EditRecord
{
    protected static string $resource = DesignCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
