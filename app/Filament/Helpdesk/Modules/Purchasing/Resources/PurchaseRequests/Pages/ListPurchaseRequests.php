<?php

namespace App\Filament\Helpdesk\Modules\Purchasing\Resources\PurchaseRequests\Pages;

use App\Filament\Helpdesk\Modules\Purchasing\Resources\PurchaseRequests\PurchaseRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;
}
