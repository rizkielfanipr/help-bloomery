<?php

namespace App\Filament\Helpdesk\Modules\Briefing\Resources\BriefingScores\Pages;

use App\Filament\Helpdesk\Modules\Briefing\Resources\BriefingScores\BriefingScoreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBriefingScore extends EditRecord
{
    protected static string $resource = BriefingScoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
