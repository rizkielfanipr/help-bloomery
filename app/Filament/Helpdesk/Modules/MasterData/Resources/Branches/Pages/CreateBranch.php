<?php

namespace App\Filament\Helpdesk\Modules\MasterData\Resources\Branches\Pages;

use App\Filament\Helpdesk\Modules\MasterData\Resources\Branches\BranchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;
}
