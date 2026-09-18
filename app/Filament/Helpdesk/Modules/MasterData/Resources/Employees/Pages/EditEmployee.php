<?php

namespace App\Filament\Helpdesk\Modules\MasterData\Resources\Employees\Pages;

use App\Filament\Helpdesk\Modules\MasterData\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;
}
