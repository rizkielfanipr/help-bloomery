<?php

namespace App\Filament\Helpdesk\Modules\Design\Resources\DesignCategories\Pages;

use App\Filament\Helpdesk\Modules\Design\Resources\DesignCategories\DesignCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDesignCategory extends CreateRecord
{
    protected static string $resource = DesignCategoryResource::class;
}
