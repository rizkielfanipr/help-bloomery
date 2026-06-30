<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualClockRecord;
use App\Models\CasualOvertimeRequest;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class AttendancePage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.attendance-page';

    public function getTitle(): string|Htmlable
    {
        return 'Absensi';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    #[Computed]
    public function records(): Collection
    {
        return CasualClockRecord::where('user_id', auth()->id())
            ->with('overtimeRequest')
            ->orderBy('date', 'desc')
            ->get();
    }

    public function cancelOvertimeRequest(int $overtimeRequestId): void
    {
        $overtimeRequest = CasualOvertimeRequest::where('id', $overtimeRequestId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $overtimeRequest) {
            Notification::make()
                ->title('Data tidak ditemukan')
                ->danger()
                ->send();

            return;
        }

        $overtimeRequest->delete();
        unset($this->records);

        Notification::make()
            ->title('Lembur berhasil dibatalkan')
            ->success()
            ->send();
    }

    public function comingSoon(): void
    {
        Notification::make()
            ->title('Fitur Dalam Pengembangan')
            ->body('Fitur ini belum tersedia saat ini.')
            ->info()
            ->send();
    }
}
