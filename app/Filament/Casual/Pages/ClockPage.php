<?php

namespace App\Filament\Casual\Pages;

use App\Models\Branch;
use App\Models\CasualClockRecord;
use App\Models\CasualOvertimeRequest;
use App\Models\CasualPositionRegistration;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
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
        return 'Presensi';
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

    public function mount(): void {}

    #[Computed]
    public function activeRegistration(): ?CasualPositionRegistration
    {
        return CasualPositionRegistration::where('user_id', auth()->id())
            ->join('casual_position_openings', 'casual_position_openings.id', '=', 'casual_position_registrations.casual_position_opening_id')
            ->where('casual_position_openings.work_date', '>=', today())
            ->orderBy('casual_position_openings.work_date')
            ->select('casual_position_registrations.*')
            ->with(['opening.casualPosition', 'opening.branch', 'opening.postedBy'])
            ->first();
    }

    #[Computed]
    public function effectiveBranch(): ?Branch
    {
        return $this->activeRegistration?->opening?->branch;
    }

    #[Computed]
    public function canCancelRegistration(): bool
    {
        $reg = $this->activeRegistration;
        if (! $reg) {
            return false;
        }

        return now()->diffInHours($reg->opening->work_date->startOfDay(), false) >= 24;
    }

    public function cancelRegistration(): void
    {
        $registration = CasualPositionRegistration::where('user_id', auth()->id())
            ->join('casual_position_openings', 'casual_position_openings.id', '=', 'casual_position_registrations.casual_position_opening_id')
            ->where('casual_position_openings.work_date', '>=', today())
            ->orderBy('casual_position_openings.work_date')
            ->select('casual_position_registrations.*')
            ->first();

        if (! $registration) {
            unset($this->activeRegistration, $this->effectiveBranch, $this->canCancelRegistration);

            return;
        }

        if (now()->diffInHours($registration->opening->work_date->startOfDay(), false) < 24) {
            Notification::make()
                ->title('Pembatalan Tidak Dapat Diproses')
                ->body('Pembatalan pendaftaran hanya dapat dilakukan lebih dari 24 jam sebelum tanggal kerja.')
                ->danger()
                ->send();

            return;
        }

        $registration->delete();

        unset($this->activeRegistration, $this->effectiveBranch, $this->canCancelRegistration);

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

    public function submitOvertimeRequest(string $date, string $startTime, string $endTime, string $reason): void
    {
        $record = CasualClockRecord::where('user_id', auth()->id())
            ->where('date', $date)
            ->with('overtimeRequest')
            ->first();

        if (! $record) {
            Notification::make()
                ->title('Data absensi tidak ditemukan')
                ->body('Tidak ada catatan absensi pada tanggal tersebut.')
                ->danger()
                ->send();

            return;
        }

        if ($record->overtimeRequest) {
            Notification::make()
                ->title('Request lembur sudah ada')
                ->body('Sudah ada request lembur untuk absensi tanggal ini.')
                ->warning()
                ->send();

            return;
        }

        [$sh, $sm] = array_map('intval', explode(':', $startTime));
        [$eh, $em] = array_map('intval', explode(':', $endTime));
        $totalMins = ($eh * 60 + $em) - ($sh * 60 + $sm);

        if ($totalMins <= 0) {
            Notification::make()
                ->title('Waktu tidak valid')
                ->body('Jam selesai harus setelah jam mulai lembur.')
                ->danger()
                ->send();

            return;
        }

        $approvedHours = round($totalMins / 60, 2);
        $position = $record->effectivePosition();
        $overtimeFee = $approvedHours * (float) ($position?->overtime_rate_per_hour ?? 0);

        CasualOvertimeRequest::create([
            'casual_clock_record_id' => $record->id,
            'user_id' => auth()->id(),
            'requested_hours' => $approvedHours,
            'reason' => $reason,
            'status' => 'approved',
            'approved_hours' => $approvedHours,
            'overtime_fee' => $overtimeFee > 0 ? $overtimeFee : null,
            'reviewed_at' => now(),
        ]);

        Notification::make()
            ->title('Lembur berhasil dicatat')
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

    public function clockIn(): void
    {
        $user = auth()->user();
        $branch = $this->effectiveBranch;

        if (! $this->clockInPhoto) {
            Notification::make()
                ->title('Foto Selfie Diperlukan')
                ->body('Harap ambil foto selfie sebelum melakukan absensi masuk.')
                ->danger()
                ->send();

            return;
        }

        $existing = CasualClockRecord::where('user_id', $user->id)
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

        if ($branch?->hasLocation() && $this->latitude && $this->longitude) {
            $distance = $this->calculateDistance(
                $this->latitude, $this->longitude,
                $branch->lat, $branch->lng,
            );

            if ($distance > $branch->radius_meters) {
                Notification::make()
                    ->title('Lokasi Di Luar Area Kerja')
                    ->body('Anda berada sejauh '.round($distance).' m dari lokasi kerja. Radius yang diizinkan: '.$branch->radius_meters.' m.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $photoPath = $this->clockInPhoto->store('casual-clocks', 'public');
        $now = now();

        CasualClockRecord::create([
            'user_id' => $user->id,
            'branch_id' => $branch?->id,
            'date' => today(),
            'clock_in_at' => $now,
            'clock_in_photo' => $photoPath,
            'clock_in_lat' => $this->latitude,
            'clock_in_lng' => $this->longitude,
        ]);

        $this->clockInPhoto = null;
        $this->latitude = null;
        $this->longitude = null;

        unset($this->todayRecord, $this->unreadCount);

        $notif = Notification::make()
            ->title('Absensi Masuk Berhasil — Pukul '.$now->format('H:i'))
            ->body('Absensi masuk Anda telah berhasil dicatat.')
            ->success();

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

        $branch = $record->branch;
        $now = now();

        if ($branch?->hasLocation() && $this->latitude && $this->longitude) {
            $distance = $this->calculateDistance(
                $this->latitude, $this->longitude,
                $branch->lat, $branch->lng,
            );

            if ($distance > $branch->radius_meters) {
                Notification::make()
                    ->title('Lokasi Di Luar Area Kerja')
                    ->body('Anda berada sejauh '.round($distance).' m dari lokasi kerja.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $photoPath = $this->clockOutPhoto->store('casual-clocks', 'public');

        $record->update([
            'clock_out_at' => $now,
            'clock_out_photo' => $photoPath,
            'clock_out_lat' => $this->latitude,
            'clock_out_lng' => $this->longitude,
        ]);

        $this->clockOutPhoto = null;
        $this->latitude = null;
        $this->longitude = null;

        unset($this->todayRecord, $this->unreadCount);

        $notif = Notification::make()
            ->title('Absensi Keluar Berhasil — Pukul '.$now->format('H:i'))
            ->body('Absensi keluar Anda telah berhasil dicatat.')
            ->success();

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
