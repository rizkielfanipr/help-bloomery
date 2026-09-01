<?php

namespace App\Filament\Helpdesk\Resources\BulkProductSubmissions\Pages;

use App\Actions\SubmitBulkProductAction;
use App\Filament\Helpdesk\Resources\BulkProductSubmissions\BulkProductSubmissionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBulkProductSubmission extends ViewRecord
{
    protected static string $resource = BulkProductSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_failed')
                ->label('Coba Ulang yang Gagal')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => auth()->user()?->can('edit bulk product submissions') && $this->record->items()->where('status', 'failed')->exists())
                ->action(function (SubmitBulkProductAction $submit): void {
                    foreach ($this->record->items()->where('status', 'failed')->get() as $item) {
                        $submit->retry($item);
                    }

                    $this->record->refresh();
                    Notification::make()->title('Percobaan ulang selesai')->success()->send();
                }),
        ];
    }
}
