<?php

namespace App\Filament\Helpdesk\Modules\MasterData\Resources\Employees\Pages;

use App\Filament\Helpdesk\Modules\MasterData\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
}
