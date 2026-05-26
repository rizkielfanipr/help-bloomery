<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->checkAndAutoComplete();
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('klaim_garansi')
                ->label('Klaim Garansi')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Klaim Garansi')
                ->modalDescription('Pekerjaan dikembalikan ke teknisi untuk perbaikan ulang.')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Warranty)
                ->action(function (ServiceRequest $record): void {
                    $record->update([
                        'status' => ServiceRequestStatus::InProgress,
                        'completed_at' => null,
                        'warranty_expires_at' => null,
                        'after_photo' => null,
                        'after_notes' => null,
                    ]);

                    Notification::make()
                        ->title('Garansi diklaim — pekerjaan dikembalikan ke teknisi')
                        ->warning()
                        ->send();
                }),
        ];
    }
}
