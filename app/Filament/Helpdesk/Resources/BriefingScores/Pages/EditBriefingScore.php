<?php

namespace App\Filament\Helpdesk\Resources\BriefingScores\Pages;

use App\Filament\Helpdesk\Resources\BriefingScores\BriefingScoreResource;
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
