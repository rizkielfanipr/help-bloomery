<?php

namespace App\Filament\Helpdesk\Resources\PurchaseRequests\Pages;

use App\Filament\Helpdesk\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Models\AppSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        $isOpen = AppSetting::get('purchase_request_open', 'true') === 'true';

        return [
            Action::make('toggle_form')
                ->label($isOpen ? 'Tutup Form' : 'Buka Form')
                ->icon($isOpen ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                ->color($isOpen ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalFooterActionsAlignment(Alignment::End)
                ->modalHeading($isOpen ? 'Tutup Form Pengajuan?' : 'Buka Form Pengajuan?')
                ->modalDescription($isOpen
                    ? 'Pengguna tidak akan bisa mengajukan pembelian baru. Pastikan alasan penutupan sudah diisi.'
                    : 'Pengguna kembali bisa mengajukan pembelian baru.')
                ->form($isOpen ? [
                    Textarea::make('close_reason')
                        ->label('Alasan Penutupan')
                        ->placeholder('Contoh: Anggaran bulan ini sudah penuh')
                        ->required()
                        ->rows(3),
                ] : [])
                ->action(function (array $data) use ($isOpen): void {
                    if ($isOpen) {
                        AppSetting::set('purchase_request_open', 'false');
                        AppSetting::set('purchase_request_close_reason', $data['close_reason']);
                    } else {
                        AppSetting::set('purchase_request_open', 'true');
                        AppSetting::set('purchase_request_close_reason', null);
                    }

                    Notification::make()
                        ->title($isOpen ? 'Form pengajuan ditutup' : 'Form pengajuan dibuka')
                        ->success()
                        ->send();
                }),
        ];
    }
}
