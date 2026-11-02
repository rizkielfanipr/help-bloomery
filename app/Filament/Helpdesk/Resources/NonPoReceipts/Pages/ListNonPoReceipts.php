<?php

namespace App\Filament\Helpdesk\Resources\NonPoReceipts\Pages;

use App\Filament\Helpdesk\Resources\NonPoReceipts\NonPoReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListNonPoReceipts extends ListRecords
{
    protected static string $resource = NonPoReceiptResource::class;

    protected ?string $heading = 'Permintaan PO Susulan dari Receiving';

    public function getTitle(): string
    {
        return 'Permintaan PO Susulan dari Receiving';
    }
}
