<?php

namespace App\Filament\Helpdesk\Modules\MasterData\Resources\Brands\Pages;

use App\Filament\Helpdesk\Modules\MasterData\Resources\Brands\BrandResource;
use Filament\Resources\Pages\ListRecords;

class ListBrands extends ListRecords
{
    protected static string $resource = BrandResource::class;
}
