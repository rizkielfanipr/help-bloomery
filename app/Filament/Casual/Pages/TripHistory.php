<?php

namespace App\Filament\Casual\Pages;

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

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return 'Riwayat Perjalanan';
    }

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.trip-history';

    #[Url]
    public int $reportMonth;

    #[Url]
    public int $reportYear;

    public function mount(): void
    {
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;

        $today = now();

        if ($today->day >= $cutoff) {
            $this->reportMonth = $today->month;
            $this->reportYear = $today->year;
        } else {
            $prev = $today->copy()->subMonth();
            $this->reportMonth = $prev->month;
            $this->reportYear = $prev->year;
        }
    }

    #[Computed]
    public function periodStart(): Carbon
    {
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;

        return Carbon::createFromDate($this->reportYear, $this->reportMonth, $cutoff)->subMonth();
    }

    #[Computed]
    public function periodEnd(): Carbon
    {
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;

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
