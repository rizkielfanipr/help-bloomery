<?php

namespace App\Filament\Helpdesk\Resources\Employees\Pages;

use App\Filament\Helpdesk\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
}
