<?php

namespace App\Filament\Helpdesk\Resources\BriefingRecords\Pages;

use App\Filament\Helpdesk\Resources\BriefingRecords\BriefingRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBriefingRecord extends EditRecord
{
    protected static string $resource = BriefingRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
