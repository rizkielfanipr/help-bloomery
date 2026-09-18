<?php

namespace App\Filament\Helpdesk\Modules\Briefing\Resources\BriefingScores\Pages;

use App\Filament\Helpdesk\Modules\Briefing\Resources\BriefingScores\BriefingScoreResource;
use Filament\Resources\Pages\ListRecords;

class ListBriefingScores extends ListRecords
{
    protected static string $resource = BriefingScoreResource::class;
}
