<?php

namespace App\Filament\Helpdesk\Modules\Rnd\Resources\PrefixCategories\Pages;

use App\Filament\Helpdesk\Modules\Rnd\Resources\PrefixCategories\PrefixCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrefixCategory extends CreateRecord
{
    protected static string $resource = PrefixCategoryResource::class;
}
