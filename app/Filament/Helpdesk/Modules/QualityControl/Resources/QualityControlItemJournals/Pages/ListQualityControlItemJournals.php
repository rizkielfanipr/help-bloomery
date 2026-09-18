<?php

namespace App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlItemJournals\Pages;

use App\Filament\Helpdesk\Modules\QualityControl\Resources\QualityControlItemJournals\QualityControlItemJournalResource;
use Filament\Resources\Pages\ListRecords;

class ListQualityControlItemJournals extends ListRecords
{
    protected static string $resource = QualityControlItemJournalResource::class;
}
