<?php

namespace App\Filament\Helpdesk\Resources\CasualStaff\Pages;

use App\Filament\Helpdesk\Resources\CasualStaff\CasualStaffResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCasualStaff extends EditRecord
{
    protected static string $resource = CasualStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
