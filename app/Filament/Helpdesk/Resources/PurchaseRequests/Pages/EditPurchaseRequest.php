<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests\Pages;

use App\Filament\Helpdesk\Resources\PurchaseRequests\PurchaseRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseRequest extends EditRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['processed_by'] = auth()->id();

        return $data;
    }
}
