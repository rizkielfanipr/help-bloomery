<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions\Pages;

use App\Filament\Helpdesk\Resources\CasualPositions\CasualPositionResource;
use App\Models\CasualPosition;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListCasualPositions extends ListRecords
{
    protected static string $resource = CasualPositionResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Posisi Casual';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $total = CasualPosition::count();
        $active = CasualPosition::where('is_active', true)->count();

        return new HtmlString(
            "<span class=\"text-sm text-slate-500 dark:text-slate-400\">Menampilkan <strong>{$total}</strong> posisi &mdash; <strong>{$active}</strong> aktif</span>"
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('penerimaan_status')
                ->label('Layanan Penerimaan Aktif')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->disabled()
                ->extraAttributes(['class' => 'fi-badge-penerimaan']),

            CreateAction::make()
                ->label('Tambah Posisi')
                ->icon('heroicon-o-plus'),

            Action::make('import_excel')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action(function (): void {
                    Notification::make()
                        ->title('Segera hadir')
                        ->body('Fitur Import Excel belum tersedia.')
                        ->warning()
                        ->send();
                }),

            Action::make('export_excel')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): void {
                    Notification::make()
                        ->title('Segera hadir')
                        ->body('Fitur Export Excel belum tersedia.')
                        ->warning()
                        ->send();
                }),

            Action::make('toggle_penerimaan')
                ->label('Tutup Penerimaan')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Tutup Penerimaan')
                ->modalDescription('Apakah Anda yakin ingin menutup layanan penerimaan? Staff baru tidak dapat mendaftar.')
                ->action(function (): void {
                    Notification::make()
                        ->title('Segera hadir')
                        ->body('Fitur Buka/Tutup Penerimaan belum tersedia.')
                        ->warning()
                        ->send();
                }),

            Action::make('refresh')
                ->label('')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Refresh')
                ->action(fn () => null),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
