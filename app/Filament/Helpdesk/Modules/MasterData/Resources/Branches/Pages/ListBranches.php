<?php

namespace App\Filament\Helpdesk\Modules\MasterData\Resources\Branches\Pages;

use App\Filament\Helpdesk\Modules\MasterData\Resources\Branches\BranchResource;
use Filament\Resources\Pages\ListRecords;

class ListBranches extends ListRecords
{
    protected static string $resource = BranchResource::class;
}
