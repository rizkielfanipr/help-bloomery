<?php

namespace App\Filament\Driver\Pages;

use App\Enums\TripStatus;
use App\Filament\Concerns\HasTripHistoryLedger;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class TripHistory extends Page
{
    use HasTripHistoryLedger;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Riwayat Perjalanan';

    protected static ?int $navigationSort = 2;

    protected static string $layout = 'filament.driver.layouts.bare';

    protected string $view = 'filament.driver.pages.trip-history';

    #[Url]
    public int $reportMonth;

    #[Url]
    public int $reportYear;

    public function mount(): void
    {
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;

        // Tentukan periode aktif berdasarkan cutoff
        $today = now();

        if ($today->day >= $cutoff) {
            // Sudah lewat cutoff, periode berjalan adalah bulan ini
            $this->reportMonth = $today->month;
            $this->reportYear = $today->year;
        } else {
            // Belum cutoff, periode berjalan adalah bulan lalu
            $prev = $today->copy()->subMonth();
            $this->reportMonth = $prev->month;
            $this->reportYear = $prev->year;
        }
    }

    #[Computed]
    public function periodStart(): Carbon
    {
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;

        // Periode dimulai dari tanggal cutoff bulan sebelumnya
        $prev = Carbon::createFromDate($this->reportYear, $this->reportMonth, $cutoff)->subMonth();

        return $prev;
    }

    #[Computed]
    public function periodEnd(): Carbon
    {
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;

        // Periode berakhir satu hari sebelum cutoff bulan ini
        return Carbon::createFromDate($this->reportYear, $this->reportMonth, $cutoff)->subDay();
    }

    #[Computed]
    public function trips(): Collection
    {
        return Trip::where('driver_id', auth()->id())
            ->where('status', TripStatus::Completed)
            ->whereBetween('trip_date', [$this->periodStart->toDateString(), $this->periodEnd->toDateString()])
            ->with(['tripRoute', 'vehicle', 'fuelFillup', 'waypointCheckins.waypoint'])
            ->orderBy('trip_date')
            ->get();
    }

    public function previousPeriod(): void
    {
        $date = Carbon::createFromDate($this->reportYear, $this->reportMonth, 1)->subMonth();
        $this->reportMonth = $date->month;
        $this->reportYear = $date->year;
        unset($this->trips, $this->tripDays, $this->periodStart, $this->periodEnd);
    }

    public function nextPeriod(): void
    {
        $date = Carbon::createFromDate($this->reportYear, $this->reportMonth, 1)->addMonth();

        // Jangan tampilkan periode masa depan
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;
        $today = now();

        if ($today->day >= $cutoff) {
            $maxMonth = $today->month;
            $maxYear = $today->year;
        } else {
            $prev = $today->copy()->subMonth();
            $maxMonth = $prev->month;
            $maxYear = $prev->year;
        }

        if ($date->year > $maxYear || ($date->year === $maxYear && $date->month > $maxMonth)) {
            return;
        }

        $this->reportMonth = $date->month;
        $this->reportYear = $date->year;
        unset($this->trips, $this->tripDays, $this->periodStart, $this->periodEnd);
    }

    public function downloadPdf(): void
    {
        $this->redirect(
            route('driver.trip-report.pdf', [
                'month' => $this->reportMonth,
                'year' => $this->reportYear,
            ])
        );
    }
}
