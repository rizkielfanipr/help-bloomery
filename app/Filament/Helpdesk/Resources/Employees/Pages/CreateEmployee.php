<?php

namespace App\Filament\Helpdesk\Resources\Employees\Pages;

use App\Filament\Helpdesk\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
