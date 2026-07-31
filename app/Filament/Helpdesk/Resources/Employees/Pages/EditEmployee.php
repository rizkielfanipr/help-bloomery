<?php

namespace App\Filament\Helpdesk\Resources\Employees\Pages;

use App\Filament\Helpdesk\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;
}
