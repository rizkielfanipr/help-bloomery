<?php

namespace App\Filament\Helpdesk\Resources\StoreSops\Pages;

use App\Filament\Helpdesk\Resources\StoreSops\StoreSopResource;
use App\Services\StoreSopPublisher;
use Filament\Actions\Action;
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
                ->modalDescription('SOP akan langsung diberikan kepada Supervisor Store yang menangani branch terpilih.')
                ->visible(fn (): bool => $this->record->status === 'draft' && auth()->user()?->can('publish store sops'))
                ->action(function (): void {
                    $count = app(StoreSopPublisher::class)->publish($this->record, auth()->user());
                    $this->refreshFormData(['status', 'published_at', 'published_by']);
                    Notification::make()->title('SOP berhasil dipublikasikan')->body($count.' assignment Supervisor Store dibuat.')->success()->send();
                }),
            Action::make('recipients')
                ->label('Rekap Penerima')
                ->icon('heroicon-o-users')
                ->color('info')
                ->visible(fn (): bool => auth()->user()?->can('view store sop reports') ?? false)
                ->modalHeading(fn (): string => 'Rekap Penerima — '.$this->record->title)
                ->modalWidth(Width::FourExtraLarge)
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('filament.helpdesk.store-sops.recipients', ['record' => $this->record->load('assignments.user', 'assignments.branch')])),
            Action::make('archive')
                ->label('Arsipkan')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('SOP ini akan diarsipkan dan tidak lagi muncul sebagai tugas aktif.')
                ->visible(fn (): bool => $this->record->status === 'published' && auth()->user()?->can('edit store sops'))
                ->action(function (): void {
                    $this->record->update(['status' => 'archived']);
                    $this->refreshFormData(['status']);
                    Notification::make()->title('SOP berhasil diarsipkan')->warning()->send();
                }),
        ];
    }
}
