<?php

namespace App\Filament\Helpdesk\Resources\Branches\Pages;

use App\Filament\Helpdesk\Resources\Branches\BranchResource;
use App\Services\EsbBranchSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBranches extends ListRecords
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncEsbBranches')
                ->label('Sync Branch ESB')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sinkronkan Branch ESB?')
                ->modalDescription('Sistem akan mengambil daftar branch dari setiap Company Code aktif dan mengisi ESB Branch ID berdasarkan Branch Code. Mapping ambigu tidak akan diubah.')
                ->visible(fn (): bool => auth()->user()?->can('edit branches') ?? false)
                ->action(function (): void {
                    $result = app(EsbBranchSyncService::class)->syncAll();
                    $issues = $result['missing'] + $result['ambiguous'] + $result['failed'];
                    $summary = "{$result['synced']} diperbarui, {$result['unchanged']} tetap, {$result['missing']} tidak ditemukan, {$result['ambiguous']} ambigu, {$result['failed']} gagal.";

                    $notification = Notification::make()
                        ->title($issues > 0 ? 'Sync Branch ESB selesai dengan catatan' : 'Sync Branch ESB berhasil')
                        ->body($summary.($result['details'] === [] ? '' : ' '.implode(' ', array_slice($result['details'], 0, 3))));

                    ($issues > 0 ? $notification->warning() : $notification->success())->send();
                }),
        ];
    }
}
