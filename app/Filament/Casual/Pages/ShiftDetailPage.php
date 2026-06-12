<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualPositionRegistration;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;

class ShiftDetailPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.casual.pages.shift-detail-page';

    protected static string $layout = 'filament.casual.layouts.bare';

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function mount(): void
    {
        $user = auth()->user();

        $registration = $user->casualPositionRegistration()
            ->with(['opening.casualShift'])
            ->first();

        if (! $registration) {
            $this->redirect(route('filament.casual.pages.select-position'));

            return;
        }

        $shiftStart = Carbon::parse(
            $registration->opening->work_date->toDateString()
            .' '
            .$registration->opening->casualShift->start_time
        );

        if (now()->gte($shiftStart)) {
            $this->redirect(route('filament.casual.pages.clock-page'));
        }
    }

    #[Computed]
    public function registration(): CasualPositionRegistration
    {
        return auth()->user()->casualPositionRegistration()
            ->with(['opening.casualPosition', 'opening.casualShift', 'opening.postedBy'])
            ->firstOrFail();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function cancelSlot(): void
    {
        $registration = auth()->user()->casualPositionRegistration()
            ->with(['opening.casualShift'])
            ->first();

        if (! $registration) {
            $this->redirect(route('filament.casual.pages.select-position'));

            return;
        }

        $shiftStart = Carbon::parse(
            $registration->opening->work_date->toDateString()
            .' '
            .$registration->opening->casualShift->start_time
        );

        if (now()->diffInHours($shiftStart, false) < 24) {
            Notification::make()
                ->title('Tidak dapat membatalkan')
                ->body('Pembatalan hanya bisa dilakukan lebih dari 24 jam sebelum shift dimulai.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($registration): void {
            $registration->delete();
            auth()->user()->update([
                'casual_position_id' => null,
                'casual_shift_id' => null,
            ]);
        });

        Notification::make()
            ->title('Slot dibatalkan')
            ->body('Pendaftaran Anda telah berhasil dibatalkan.')
            ->success()
            ->send();

        $this->redirect(route('filament.casual.pages.select-position'));
    }

    public function getWhatsappUrl(): string
    {
        $registration = $this->registration;
        $leader = $registration->opening->postedBy;
        $phone = $this->normalizePhone($leader?->phone ?? '');
        $user = auth()->user();
        $opening = $registration->opening;

        $message = urlencode(
            'Halo, saya '.$user->name
            .'. Saya ingin menghubungi mengenai slot posisi '.$opening->casualPosition->name
            .' pada '.$opening->work_date->translatedFormat('d M Y').'.'
        );

        return 'https://wa.me/'.$phone.'?text='.$message;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
