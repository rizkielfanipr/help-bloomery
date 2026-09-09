<?php

namespace App\Filament\Helpdesk\Resources\StoreSops\Pages;

use App\Filament\Helpdesk\Resources\StoreSops\StoreSopResource;
use App\Services\StoreSopPublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewStoreSop extends ViewRecord
{
    protected static string $resource = StoreSopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => $this->record->status === 'draft'),
            Action::make('publish')
                ->label('Publish & Bagikan')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('SOP akan tersedia untuk semua pengguna yang memiliki akses SOP Store pada branch terpilih.')
                ->visible(fn (): bool => $this->record->status === 'draft' && auth()->user()?->can('publish store sops'))
                ->action(function (): void {
                    $count = app(StoreSopPublisher::class)->publish($this->record, auth()->user());
                    $this->refreshFormData(['status', 'published_at', 'published_by']);
                    Notification::make()->title('SOP berhasil dipublikasikan')->body($count.' branch menerima SOP.')->success()->send();
                }),
            Action::make('recipients')
                ->label('Rekap Penerima')
                ->icon('heroicon-o-users')
                ->color('info')
                ->visible(fn (): bool => auth()->user()?->can('view store sop reports') ?? false)
                ->modalHeading(fn (): string => 'Rekap Penerima — '.$this->record->title)
                ->modalWidth(Width::FourExtraLarge)
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('filament.helpdesk.store-sops.recipients', ['record' => $this->record->load('branches', 'assignments.user', 'assignments.branch')])),
            DeleteAction::make()
                ->label('Hapus SOP')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hapus SOP')
                ->modalDescription('SOP beserta seluruh riwayat penerimaannya akan dihapus permanen.'),
        ];
    }
}
