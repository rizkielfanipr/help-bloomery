<?php

namespace App\Filament\Helpdesk\Modules\Inventory\Resources\GoodsReceipts\Pages;

use App\Filament\Helpdesk\Modules\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;
}
