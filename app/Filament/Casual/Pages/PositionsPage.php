<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualPositionOpening;
use App\Models\CasualPositionRegistration;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class PositionsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.casual.pages.positions-page';

    protected static string $layout = 'filament.casual.layouts.bare';

    public function getTitle(): string|Htmlable
    {
        return 'Posisi Kerja';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    #[Computed]
    public function availableOpenings(): Collection
    {
        return CasualPositionOpening::available()
            ->with(['casualPosition', 'branch'])
            ->withCount('registrations')
            ->get();
    }

    /** IDs of openings the user is already registered for (upcoming). [opening_id => registration_id] */
    #[Computed]
    public function myRegistrationMap(): array
    {
        return CasualPositionRegistration::where('user_id', auth()->id())
            ->whereHas('opening', fn ($q) => $q->where('work_date', '>=', today()))
            ->pluck('id', 'casual_position_opening_id')
            ->toArray();
    }

    /** Dates (Y-m-d) the user already has a registration for. */
    #[Computed]
    public function myRegisteredDates(): array
    {
        return CasualPositionOpening::whereIn('id', array_keys($this->myRegistrationMap))
            ->pluck('work_date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();
    }

    /** All upcoming registrations for the "Jadwal Saya" section. */
    #[Computed]
    public function myRegistrations(): Collection
    {
        return CasualPositionRegistration::where('user_id', auth()->id())
            ->whereHas('opening', fn ($q) => $q->where('work_date', '>=', today()))
            ->with(['opening.casualPosition', 'opening.branch', 'opening.postedBy'])
            ->get()
            ->sortBy(fn ($r) => $r->opening->work_date->timestamp)
            ->values();
    }

    public function canCancelRegistration(CasualPositionRegistration $registration): bool
    {
        return now()->diffInHours($registration->opening->work_date->startOfDay(), false) >= 24;
    }

    public function cancelRegistration(int $registrationId): void
    {
        $registration = CasualPositionRegistration::where('id', $registrationId)
            ->where('user_id', auth()->id())
            ->with(['opening'])
            ->first();

        if (! $registration) {
            unset($this->myRegistrations, $this->myRegistrationMap);

            return;
        }

        if (! $this->canCancelRegistration($registration)) {
            Notification::make()
                ->title('Pembatalan Tidak Dapat Diproses')
                ->body('Pembatalan pendaftaran hanya dapat dilakukan lebih dari 24 jam sebelum tanggal kerja.')
                ->danger()
                ->send();

            return;
        }

        $registration->delete();

        unset($this->myRegistrations, $this->myRegistrationMap, $this->availableOpenings);

        Notification::make()
            ->title('Pendaftaran Berhasil Dibatalkan')
            ->body('Silakan pilih lowongan lain untuk mendaftar kembali.')
            ->success()
            ->send();
    }

    public function registerOpening(int $openingId): void
    {
        $user = auth()->user();

        if (isset($this->myRegistrationMap[$openingId])) {
            Notification::make()
                ->title('Pendaftaran Sudah Ada')
                ->body('Anda telah terdaftar pada lowongan ini sebelumnya.')
                ->warning()
                ->send();

            return;
        }

        $opening = CasualPositionOpening::with(['casualPosition'])
            ->where('id', $openingId)
            ->where('is_active', true)
            ->where('work_date', '>=', today())
            ->firstOrFail();

        $alreadyOnSameDate = CasualPositionRegistration::where('user_id', auth()->id())
            ->whereHas('opening', fn ($q) => $q->whereDate('work_date', $opening->work_date))
            ->exists();

        if ($alreadyOnSameDate) {
            Notification::make()
                ->title('Tanggal Sudah Terisi')
                ->body('Anda sudah memiliki shift pada '.($opening->work_date->translatedFormat('d M Y')).'. Pilih lowongan di tanggal berbeda.')
                ->icon('heroicon-o-calendar-days')
                ->warning()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($user, $opening): void {
                $count = CasualPositionRegistration::where('casual_position_opening_id', $opening->id)
                    ->lockForUpdate()
                    ->count();

                if ($count >= $opening->total_slots) {
                    Notification::make()
                        ->title('Slot Penuh')
                        ->body('Semua slot untuk lowongan ini sudah terisi. Silakan pilih lowongan lain.')
                        ->icon('heroicon-o-users')
                        ->danger()
                        ->send();

                    return;
                }

                CasualPositionRegistration::create([
                    'casual_position_opening_id' => $opening->id,
                    'user_id' => $user->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title('Pendaftaran Sudah Ada')
                ->body('Anda telah terdaftar pada lowongan ini sebelumnya.')
                ->warning()
                ->send();

            return;
        }

        unset($this->myRegistrations, $this->myRegistrationMap, $this->availableOpenings);

        Notification::make()
            ->title('Pendaftaran Berhasil')
            ->body('Pendaftaran Anda untuk posisi '.$opening->casualPosition->name.' pada '.$opening->work_date->translatedFormat('d M Y').' telah berhasil diproses.')
            ->success()
            ->send();
    }

    public function getWhatsappUrl(int $registrationId): string
    {
        $registration = CasualPositionRegistration::where('id', $registrationId)
            ->where('user_id', auth()->id())
            ->with(['opening.casualPosition', 'opening.postedBy'])
            ->firstOrFail();

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
