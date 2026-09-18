<?php

namespace App\Filament\Helpdesk\Modules\MasterData\Resources\Brands\Pages;

use App\Filament\Helpdesk\Modules\MasterData\Resources\Brands\BrandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;
}
