<?php

namespace App\Filament\Helpdesk\Resources\BriefingTasks\Pages;

use App\Filament\Helpdesk\Resources\BriefingTasks\BriefingTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBriefingTask extends EditRecord
{
    protected static string $resource = BriefingTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
