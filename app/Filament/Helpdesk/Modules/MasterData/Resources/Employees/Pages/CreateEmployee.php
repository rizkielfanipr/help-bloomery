<?php

namespace App\Filament\Helpdesk\Modules\MasterData\Resources\Employees\Pages;

use App\Filament\Helpdesk\Modules\MasterData\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
