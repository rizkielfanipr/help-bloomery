<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualClockRecord;
use App\Models\CasualPositionRegistration;
use App\Models\CasualShift;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class ClockPage extends Page
{
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationLabel = 'Absensi';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.casual.pages.clock-page';

    protected static string $layout = 'filament.casual.layouts.bare';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public $clockInPhoto = null;

    public $clockOutPhoto = null;

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
        // State is handled entirely in the blade view
    }

    #[Computed]
    public function activeRegistration(): ?CasualPositionRegistration
    {
        return CasualPositionRegistration::where('user_id', auth()->id())
            ->join('casual_position_openings', 'casual_position_openings.id', '=', 'casual_position_registrations.casual_position_opening_id')
            ->where('casual_position_openings.work_date', '>=', today())
            ->orderBy('casual_position_openings.work_date')
            ->select('casual_position_registrations.*')
            ->with(['opening.casualPosition', 'opening.casualShift', 'opening.postedBy'])
            ->first();
    }

    #[Computed]
    public function effectiveShift(): ?CasualShift
    {
        return $this->activeRegistration?->opening?->casualShift;
    }

    #[Computed]
    public function shiftStartedAt(): ?Carbon
    {
        $reg = $this->activeRegistration;
        if (! $reg) {
            return null;
        }

        return Carbon::parse(
            $reg->opening->work_date->toDateString()
            .' '
            .$reg->opening->casualShift->start_time
        );
    }

    #[Computed]
    public function isPreShift(): bool
    {
        $start = $this->shiftStartedAt;

        return $start !== null && now()->lt($start);
    }

    #[Computed]
    public function canCancelRegistration(): bool
    {
        $start = $this->shiftStartedAt;
        if (! $start) {
            return false;
        }

        return now()->diffInHours($start, false) >= 24;
    }

    public function cancelRegistration(): void
    {
        $registration = CasualPositionRegistration::where('user_id', auth()->id())
            ->join('casual_position_openings', 'casual_position_openings.id', '=', 'casual_position_registrations.casual_position_opening_id')
            ->where('casual_position_openings.work_date', '>=', today())
            ->orderBy('casual_position_openings.work_date')
            ->select('casual_position_registrations.*')
            ->with(['opening.casualShift'])
            ->first();

        if (! $registration) {
            unset($this->activeRegistration, $this->effectiveShift, $this->shiftStartedAt, $this->isPreShift, $this->canCancelRegistration);

            return;
        }

        $shiftStart = Carbon::parse(
            $registration->opening->work_date->toDateString()
            .' '
            .$registration->opening->casualShift->start_time
        );

        if (now()->diffInHours($shiftStart, false) < 24) {
            Notification::make()
                ->title('Pembatalan Tidak Dapat Diproses')
                ->body('Pembatalan pendaftaran hanya dapat dilakukan lebih dari 24 jam sebelum shift dimulai.')
                ->danger()
                ->send();

            return;
        }

        $registration->delete();

        unset($this->activeRegistration, $this->effectiveShift, $this->shiftStartedAt, $this->isPreShift, $this->canCancelRegistration);

        Notification::make()
            ->title('Pendaftaran Berhasil Dibatalkan')
            ->body('Silakan pilih lowongan lain untuk mendaftar kembali.')
            ->success()
            ->send();
    }

    #[Computed]
    public function todayRecord(): ?CasualClockRecord
    {
        return CasualClockRecord::where('user_id', auth()->id())
            ->where('date', today())
            ->latest()
            ->first();
    }

    #[Computed]
    public function monthlyStats(): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();

        $records = CasualClockRecord::where('user_id', auth()->id())
            ->whereBetween('date', [$startOfMonth->toDateString(), $now->toDateString()])
            ->get();

        $presentCount = $records->count();
        $lateCount = $records->where('is_late', true)->count();

        $workingDays = 0;
        $day = $startOfMonth->copy();
        while ($day->lte($now)) {
            if ($day->isWeekday()) {
                $workingDays++;
            }
            $day->addDay();
        }

        return [
            'present' => $presentCount,
            'absent' => max(0, $workingDays - $presentCount),
            'late' => $lateCount,
        ];
    }

    #[Computed]
    public function workingHours(): string
    {
        $record = $this->todayRecord;
        if (! $record?->clock_in_at || ! $record?->clock_out_at) {
            return '00:00:00';
        }
        $seconds = $record->clock_in_at->diffInSeconds($record->clock_out_at);

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function requestLeave(): void
    {
        Notification::make()
            ->title('Fitur Dalam Pengembangan')
            ->body('Fitur pengajuan izin/cuti belum tersedia saat ini.')
            ->info()
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

    public function clockIn(): void
    {
        $user = auth()->user();
        $shift = $this->effectiveShift;

        if (! $shift) {
            Notification::make()
                ->title('Shift Belum Dikonfigurasi')
                ->body('Silakan hubungi HR Admin untuk pengaturan shift Anda.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->clockInPhoto) {
            Notification::make()
                ->title('Foto Selfie Diperlukan')
                ->body('Harap ambil foto selfie sebelum melakukan absensi masuk.')
                ->danger()
                ->send();

            return;
        }

        $existing = CasualClockRecord::where('user_id', $user->id)
            ->where('shift_id', $shift->id)
            ->where('date', today())
            ->first();

        if ($existing) {
            Notification::make()
                ->title('Absensi Masuk Telah Tercatat')
                ->body('Absensi masuk untuk hari ini telah tercatat sebelumnya.')
                ->warning()
                ->send();

            return;
        }

        if ($shift->location_required && $this->latitude && $this->longitude) {
            $distance = $this->calculateDistance(
                $this->latitude, $this->longitude,
                $shift->location_lat, $shift->location_lng,
            );

            if ($distance > $shift->location_radius_meters) {
                Notification::make()
                    ->title('Lokasi Di Luar Area Kerja')
                    ->body('Anda berada sejauh '.round($distance).' m dari lokasi kerja. Radius yang diizinkan: '.$shift->location_radius_meters.' m.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $photoPath = $this->clockInPhoto->store('casual-clocks', 'public');
        $now = now();
        $lateMinutes = $shift->calculateLateMinutes($now);
        $isLate = $lateMinutes > 0;

        CasualClockRecord::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'date' => today(),
            'clock_in_at' => $now,
            'clock_in_photo' => $photoPath,
            'clock_in_lat' => $this->latitude,
            'clock_in_lng' => $this->longitude,
            'is_late' => $isLate,
            'late_minutes' => $isLate ? $lateMinutes : null,
        ]);

        $this->clockInPhoto = null;
        $this->latitude = null;
        $this->longitude = null;

        unset($this->todayRecord, $this->unreadCount, $this->canCancelRegistration);

        if ($isLate) {
            $notif = Notification::make()
                ->title('Absensi Masuk — Terlambat '.$lateMinutes.' Menit')
                ->body('Absensi masuk dicatat pukul '.$now->format('H:i').'. Jadwal masuk: '.Carbon::parse($shift->start_time)->format('H:i').'.')
                ->warning();
        } else {
            $notif = Notification::make()
                ->title('Absensi Masuk Berhasil — Pukul '.$now->format('H:i'))
                ->body('Absensi masuk Anda telah berhasil dicatat.')
                ->success();
        }

        $notif->send();
        $user->notifyNow($notif->toDatabase());
    }

    public function clockOut(): void
    {
        if (! $this->clockOutPhoto) {
            Notification::make()
                ->title('Foto Selfie Diperlukan')
                ->body('Harap ambil foto selfie sebelum melakukan absensi keluar.')
                ->danger()
                ->send();

            return;
        }

        $record = CasualClockRecord::where('user_id', auth()->id())
            ->where('date', today())
            ->whereNull('clock_out_at')
            ->latest()
            ->firstOrFail();

        $shift = $record->shift;
        $now = now();

        if ($shift->location_required && $this->latitude && $this->longitude) {
            $distance = $this->calculateDistance(
                $this->latitude, $this->longitude,
                $shift->location_lat, $shift->location_lng,
            );

            if ($distance > $shift->location_radius_meters) {
                Notification::make()
                    ->title('Lokasi Di Luar Area Kerja')
                    ->body('Anda berada sejauh '.round($distance).' m dari lokasi kerja.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $photoPath = $this->clockOutPhoto->store('casual-clocks', 'public');
        $earlyOutMinutes = $shift->calculateEarlyOutMinutes($now);
        $isEarlyOut = $earlyOutMinutes > 0;

        $record->update([
            'clock_out_at' => $now,
            'clock_out_photo' => $photoPath,
            'clock_out_lat' => $this->latitude,
            'clock_out_lng' => $this->longitude,
            'is_early_out' => $isEarlyOut,
            'early_out_minutes' => $isEarlyOut ? $earlyOutMinutes : null,
        ]);

        $this->clockOutPhoto = null;
        $this->latitude = null;
        $this->longitude = null;

        unset($this->todayRecord, $this->unreadCount, $this->userNotifications);

        if ($isEarlyOut) {
            $notif = Notification::make()
                ->title('Absensi Keluar — Lebih Awal '.$earlyOutMinutes.' Menit')
                ->body('Absensi keluar dicatat pukul '.$now->format('H:i').'. Jadwal keluar: '.Carbon::parse($shift->end_time)->format('H:i').'.')
                ->warning();
        } else {
            $notif = Notification::make()
                ->title('Absensi Keluar Berhasil — Pukul '.$now->format('H:i'))
                ->body('Absensi keluar Anda telah berhasil dicatat.')
                ->success();
        }

        $notif->send();
        auth()->user()->notifyNow($notif->toDatabase());
    }

    public function getWhatsappUrl(): string
    {
        $registration = $this->activeRegistration;
        $leader = $registration?->opening?->postedBy;
        $phone = $this->normalizePhone($leader?->phone ?? '');
        $user = auth()->user();
        $opening = $registration?->opening;

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

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
