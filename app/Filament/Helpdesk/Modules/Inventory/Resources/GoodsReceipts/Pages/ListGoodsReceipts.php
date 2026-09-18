<?php

namespace App\Filament\Helpdesk\Modules\Inventory\Resources\GoodsReceipts\Pages;

use App\Filament\Helpdesk\Modules\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGoodsReceipts extends ListRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
