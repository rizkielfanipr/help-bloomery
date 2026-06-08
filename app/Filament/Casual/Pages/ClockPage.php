<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualClockRecord;
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
        if (auth()->user()->casual_position_id === null) {
            $this->redirect(SelectPosition::getUrl());
        }
    }

    #[Computed]
    public function todayRecord(): ?CasualClockRecord
    {
        return CasualClockRecord::where('user_id', auth()->id())
            ->where('date', today())
            ->latest()
            ->first();
    }

    public function clockIn(): void
    {
        $user = auth()->user();
        $shift = $user->casualShift;

        if (! $shift) {
            Notification::make()
                ->title('Shift belum diatur')
                ->body('Hubungi HR Admin untuk mengatur shift Anda.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->clockInPhoto) {
            Notification::make()
                ->title('Foto selfie diperlukan')
                ->body('Ambil foto selfie sebelum clock in.')
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
                ->title('Sudah clock in hari ini')
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
                    ->title('Di luar area')
                    ->body('Anda berada '.round($distance).'m dari lokasi shift. Radius: '.$shift->location_radius_meters.'m.')
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

        unset($this->todayRecord);

        if ($isLate) {
            Notification::make()
                ->title('Clock In — Terlambat '.$lateMinutes.' menit')
                ->body('Jadwal masuk: '.$shift->start_time.'. Anda masuk: '.$now->format('H:i').'.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Clock In berhasil!')
                ->body('Selamat bekerja, shift '.$shift->name.'.')
                ->success()
                ->send();
        }
    }

    public function clockOut(): void
    {
        if (! $this->clockOutPhoto) {
            Notification::make()
                ->title('Foto selfie diperlukan')
                ->body('Ambil foto selfie sebelum clock out.')
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
                    ->title('Di luar area')
                    ->body('Anda berada '.round($distance).'m dari lokasi shift.')
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

        unset($this->todayRecord);

        if ($isEarlyOut) {
            Notification::make()
                ->title('Clock Out — Pulang lebih awal '.$earlyOutMinutes.' menit')
                ->body('Jadwal keluar: '.$shift->end_time.'. Anda keluar: '.$now->format('H:i').'.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Clock Out berhasil!')
                ->body('Terima kasih, sampai jumpa!')
                ->success()
                ->send();
        }
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
