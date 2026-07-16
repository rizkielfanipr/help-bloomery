<?php

namespace App\Filament\Helpdesk\Resources\StockCards\Pages;

use App\Filament\Helpdesk\Resources\StockCards\StockCardResource;
use App\Models\StockCard;
use Filament\Resources\Pages\Page;

class ViewStockCard extends Page
{
    protected static string $resource = StockCardResource::class;

    protected string $view = 'filament.helpdesk.stock-cards.view';

    public StockCard $record;

    public function mount(StockCard $record): void
    {
        $this->record = $record->load(['branch', 'submittedBy', 'entries']);
    }

    public function getTitle(): string
    {
        return 'Stock Card — '.$this->record->branch->name.' '.$this->record->report_date->isoFormat('D MMMM Y');
    }
}
