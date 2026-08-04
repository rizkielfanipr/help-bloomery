<?php

namespace App\Filament\Concerns;

use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

trait HasTripHistoryLedger
{
    /**
     * Return every date in the selected cutoff period, including dates without a trip.
     *
     * @return array<int, array{date: CarbonInterface, trips: Collection}>
     */
    #[Computed]
    public function tripDays(): array
    {
        $tripsByDate = $this->trips->groupBy(
            fn ($trip): string => $trip->trip_date->format('Y-m-d'),
        );

        $days = [];
        foreach (CarbonPeriod::create($this->periodStart, $this->periodEnd) as $date) {
            $days[] = [
                'date' => $date->copy(),
                'trips' => $tripsByDate->get($date->format('Y-m-d'), collect()),
            ];
        }

        return $days;
    }
}
