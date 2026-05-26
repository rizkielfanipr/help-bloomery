<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualClockRecord;
use App\Models\CasualShift;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class ClockPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationLabel = 'Absensi';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.casual.pages.clock-page';

    /** Form state for clock in */
    public ?array $clockInData = [];

    /** Form state for clock out */
    public ?array $clockOutData = [];

    /** Captured GPS coordinates */
    public ?float $latitude = null;

    public ?float $longitude = null;

    /** Key to force FileUpload re-mount between clock-in and clock-out */
    public int $formKey = 0;

    public function mount(): void
    {
        if (auth()->user()->casual_position_id === null) {
            $this->redirect(SelectPosition::getUrl());
        }
    }

    #[Computed]
    public function activeShifts(): Collection
    {
        return CasualShift::where('is_active', true)->orderBy('start_time')->get();
    }

    #[Computed]
    public function todayRecord(): ?CasualClockRecord
    {
        $shiftId = $this->clockInData['shift_id'] ?? null;

        if (! $shiftId) {
            // Return any record for today (for post-clock-in state)
            return CasualClockRecord::where('user_id', auth()->id())
                ->where('date', today())
                ->latest()
                ->first();
        }

        return CasualClockRecord::where('user_id', auth()->id())
            ->where('shift_id', $shiftId)
            ->where('date', today())
            ->first();
    }

    #[Computed]
    public function anyTodayRecord(): ?CasualClockRecord
    {
        return CasualClockRecord::where('user_id', auth()->id())
            ->where('date', today())
            ->latest()
            ->first();
    }

    public function clockInForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('shift_id')
                    ->label('Pilih Shift')
                    ->options(
                        CasualShift::where('is_active', true)
                            ->orderBy('start_time')
                            ->get()
                            ->mapWithKeys(fn ($s) => [
                                $s->id => $s->name.' ('.$s->start_time.' - '.$s->end_time.')',
                            ])
                    )
                    ->required()
                    ->searchable(),

                FileUpload::make('photo')
                    ->label('Foto Selfie')
                    ->image()
                    ->disk('public')
                    ->directory('casual-clocks')
                    ->imageEditor()
                    ->required(),
            ])
            ->statePath('clockInData');
    }

    public function clockOutForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo')
                    ->label('Foto Selfie')
                    ->image()
                    ->disk('public')
                    ->directory('casual-clocks')
                    ->imageEditor()
                    ->required(),
            ])
            ->statePath('clockOutData');
    }

    public function setLocation(float $lat, float $lng): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
    }

    public function clockIn(): void
    {
        $data = $this->clockInForm->getState();
        $shift = CasualShift::findOrFail($data['shift_id']);

        // Check if already clocked in for this shift today
        $existing = CasualClockRecord::where('user_id', auth()->id())
            ->where('shift_id', $shift->id)
            ->where('date', today())
            ->first();

        if ($existing) {
            Notification::make()
                ->title('Sudah clock in untuk shift ini hari ini')
                ->warning()
                ->send();

            return;
        }

        // Location validation
        if ($shift->location_required && $this->latitude && $this->longitude) {
            $distance = $this->calculateDistance(
                $this->latitude, $this->longitude,
                $shift->location_lat, $shift->location_lng
            );

            if ($distance > $shift->location_radius_meters) {
                Notification::make()
                    ->title('Di luar area')
                    ->body('Anda berada '.round($distance).'m dari lokasi shift. Radius yang diperbolehkan: '.$shift->location_radius_meters.'m.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $now = now();
        $lateMinutes = $shift->calculateLateMinutes($now);
        $isLate = $lateMinutes > 0;

        $record = CasualClockRecord::create([
            'user_id' => auth()->id(),
            'shift_id' => $shift->id,
            'date' => today(),
            'clock_in_at' => $now,
            'clock_in_photo' => $data['photo'],
            'clock_in_lat' => $this->latitude,
            'clock_in_lng' => $this->longitude,
            'is_late' => $isLate,
            'late_minutes' => $isLate ? $lateMinutes : null,
        ]);

        $this->clockInData = [];
        $this->latitude = null;
        $this->longitude = null;
        $this->formKey++;

        unset($this->todayRecord, $this->anyTodayRecord);

        if ($isLate) {
            Notification::make()
                ->title('Clock In berhasil — Terlambat '.$lateMinutes.' menit')
                ->body('Jadwal masuk: '.$shift->start_time.'. Anda masuk: '.$now->format('H:i').'.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Clock In berhasil!')
                ->body('Selamat bekerja di shift '.$shift->name.'.')
                ->success()
                ->send();
        }
    }

    public function clockOut(): void
    {
        $data = $this->clockOutForm->getState();

        $record = CasualClockRecord::where('user_id', auth()->id())
            ->where('date', today())
            ->whereNull('clock_out_at')
            ->latest()
            ->firstOrFail();

        $shift = $record->shift;
        $now = now();

        // Location validation
        if ($shift->location_required && $this->latitude && $this->longitude) {
            $distance = $this->calculateDistance(
                $this->latitude, $this->longitude,
                $shift->location_lat, $shift->location_lng
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

        $earlyOutMinutes = $shift->calculateEarlyOutMinutes($now);
        $isEarlyOut = $earlyOutMinutes > 0;

        $record->update([
            'clock_out_at' => $now,
            'clock_out_photo' => $data['photo'],
            'clock_out_lat' => $this->latitude,
            'clock_out_lng' => $this->longitude,
            'is_early_out' => $isEarlyOut,
            'early_out_minutes' => $isEarlyOut ? $earlyOutMinutes : null,
        ]);

        $this->clockOutData = [];
        $this->latitude = null;
        $this->longitude = null;
        $this->formKey++;

        unset($this->todayRecord, $this->anyTodayRecord);

        if ($isEarlyOut) {
            Notification::make()
                ->title('Clock Out berhasil — Pulang lebih awal '.$earlyOutMinutes.' menit')
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

    /**
     * Calculate distance between two coordinates in meters (Haversine formula).
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
