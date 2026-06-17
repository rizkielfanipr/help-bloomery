<?php

namespace App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages;

use App\Filament\Helpdesk\Resources\CasualPositionOpenings\CasualPositionOpeningResource;
use App\Models\CasualPositionOpening;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class CreateCasualPositionOpening extends CreateRecord
{
    protected static string $resource = CasualPositionOpeningResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Buat Lowongan Baru';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['posted_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $schedules = $data['schedules'] ?? [];
        unset($data['schedules']);

        $first = null;
        foreach ($schedules as $schedule) {
            $record = CasualPositionOpening::create(array_merge($data, [
                'work_date' => $schedule['work_date'],
                'casual_shift_id' => $schedule['casual_shift_id'],
                'total_slots' => (int) $schedule['total_slots'],
            ]));
            $first ??= $record;
        }

        return $first ?? CasualPositionOpening::create($data);
    }

    protected function getRedirectUrl(): string
    {
        return CasualPositionOpeningResource::getUrl('index');
    }
}
