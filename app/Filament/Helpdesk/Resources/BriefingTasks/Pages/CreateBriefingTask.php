<?php

namespace App\Filament\Helpdesk\Resources\BriefingTasks\Pages;

use App\Filament\Helpdesk\Resources\BriefingTasks\BriefingTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBriefingTask extends CreateRecord
{
    protected static string $resource = BriefingTaskResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
