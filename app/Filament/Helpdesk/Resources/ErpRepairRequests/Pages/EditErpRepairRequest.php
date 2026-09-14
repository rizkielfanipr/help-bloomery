<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Actions\UpdateErpRequestStatusAction;
use App\Enums\ItRequestStatus;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditErpRepairRequest extends EditRecord
{
    protected static string $resource = ErpRepairRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param array{status: string, it_notes?: ?string} $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(UpdateErpRequestStatusAction::class)->execute(
            $record,
            ItRequestStatus::from($data['status']),
            $data['it_notes'] ?? null,
            auth()->user(),
            'data.status',
            'data.it_notes',
        );

        return $record->refresh();
    }
}
