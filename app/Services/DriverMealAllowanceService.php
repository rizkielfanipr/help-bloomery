<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\DriverMealAllowancePeriod;
use App\Models\DriverMealAllowanceSummary;
use App\Models\DriverMealAllowanceTripItem;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverMealAllowanceService
{
    public function createPeriod(int $year, int $month, int $userId): DriverMealAllowancePeriod
    {
        [$start, $end] = $this->dateRange($year, $month);

        return DB::transaction(function () use ($year, $month, $start, $end, $userId): DriverMealAllowancePeriod {
            $period = DriverMealAllowancePeriod::firstOrCreate(
                ['report_year' => $year, 'report_month' => $month],
                ['start_date' => $start, 'end_date' => $end, 'status' => 'open', 'created_by' => $userId],
            );

            if ($period->isOpen()) {
                $period->update(['start_date' => $start, 'end_date' => $end]);
            }

            $this->sync($period);

            return $period->fresh();
        });
    }

    /**
     * Recalculate all open periods after the global cutoff setting changes.
     * Finalized periods intentionally remain immutable payroll snapshots.
     */
    public function refreshOpenPeriods(): int
    {
        $updated = 0;

        DriverMealAllowancePeriod::query()
            ->where('status', 'open')
            ->orderBy('start_date')
            ->each(function (DriverMealAllowancePeriod $period) use (&$updated): void {
                [$start, $end] = $this->dateRange($period->report_year, $period->report_month);
                $period->update(['start_date' => $start, 'end_date' => $end]);
                $this->sync($period);
                $updated++;
            });

        return $updated;
    }

    public function sync(DriverMealAllowancePeriod $period): void
    {
        $this->assertOpen($period);

        DB::transaction(function () use ($period): void {
            $tripQuery = Trip::query()
                ->with(['driver:id,name,username', 'tripRoute:id,name,meal_allowance_amount'])
                ->where('status', TripStatus::Completed)
                ->whereBetween('trip_date', [$period->start_date, $period->end_date])
                ->whereNotNull('driver_id')
                ->orderBy('trip_date');

            if ($period->is_demo) {
                $tripQuery->where('notes', 'like', '[DEMO UANG MAKAN DRIVER]%');
            }

            $trips = $tripQuery->get();

            $period->items()->whereNotIn('trip_id', $trips->pluck('id'))->delete();

            foreach ($trips as $trip) {
                $summary = DriverMealAllowanceSummary::firstOrCreate([
                    'period_id' => $period->id,
                    'driver_id' => $trip->driver_id,
                ]);

                $tripAmount = $trip->getRawOriginal('meal_allowance_amount');
                $routeAmount = $trip->tripRoute?->getRawOriginal('meal_allowance_amount');
                $source = $tripAmount !== null ? 'trip_snapshot' : ($routeAmount !== null ? 'route_fallback' : 'missing');

                DriverMealAllowanceTripItem::firstOrCreate(
                    ['trip_id' => $trip->id],
                    [
                        'period_id' => $period->id,
                        'summary_id' => $summary->id,
                        'trip_date' => $trip->trip_date,
                        'trip_code' => $trip->code ?: 'TRIP-'.$trip->id,
                        'route_name' => $trip->tripRoute?->name,
                        'allowance_amount' => $tripAmount ?? $routeAmount ?? 0,
                        'amount_source' => $source,
                    ],
                );
            }

            $this->recalculate($period);
        });
    }

    public function updateAdjustment(DriverMealAllowanceSummary $summary, float $amount, ?string $reason, int $userId): void
    {
        $this->assertOpen($summary->period);

        if ($amount !== 0.0 && blank($reason)) {
            throw ValidationException::withMessages(['adjustmentReason' => 'Alasan wajib diisi saat ada penyesuaian.']);
        }

        $summary->update([
            'adjustment_amount' => $amount,
            'adjustment_reason' => filled($reason) ? trim($reason) : null,
            'adjusted_by' => $userId,
            'adjusted_at' => now(),
        ]);
        $this->recalculate($summary->period);
    }

    public function updateItem(DriverMealAllowanceTripItem $item, float $amount, bool $included, ?string $reason): void
    {
        $this->assertOpen($item->summary->period);

        if (! $included && blank($reason)) {
            throw ValidationException::withMessages(['itemReason' => 'Alasan wajib diisi untuk perjalanan yang dikecualikan.']);
        }

        $item->update([
            'allowance_amount' => max(0, $amount),
            'amount_source' => 'hr_adjustment',
            'is_included' => $included,
            'exclusion_reason' => $included ? null : trim((string) $reason),
        ]);
        $this->recalculate($item->summary->period);
    }

    public function finalize(DriverMealAllowancePeriod $period, int $userId): void
    {
        $this->assertOpen($period);
        $this->sync($period);

        if ($period->items()->where('amount_source', 'missing')->exists()) {
            throw ValidationException::withMessages(['period' => 'Masih ada perjalanan tanpa nominal uang makan. Periksa detail driver terlebih dahulu.']);
        }

        $period->update(['status' => 'finalized', 'finalized_by' => $userId, 'finalized_at' => now()]);
    }

    public function reopen(DriverMealAllowancePeriod $period, string $reason, int $userId): void
    {
        if ($period->status !== 'finalized') {
            throw ValidationException::withMessages(['period' => 'Hanya periode finalized yang dapat dibuka kembali.']);
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['reopenReason' => 'Alasan membuka kembali wajib diisi.']);
        }

        $period->update([
            'status' => 'open', 'reopened_by' => $userId, 'reopened_at' => now(),
            'reopen_reason' => trim($reason), 'finalized_by' => null, 'finalized_at' => null,
        ]);
        $this->sync($period);
    }

    public function lateTripCount(DriverMealAllowancePeriod $period): int
    {
        $query = Trip::query()
            ->where('status', TripStatus::Completed)
            ->whereBetween('trip_date', [$period->start_date, $period->end_date])
            ->whereNotIn('id', $period->items()->select('trip_id'));

        if ($period->is_demo) {
            $query->where('notes', 'like', '[DEMO UANG MAKAN DRIVER]%');
        }

        return $query->count();
    }

    private function recalculate(DriverMealAllowancePeriod $period): void
    {
        $summaries = $period->summaries()->with('items')->get();

        foreach ($summaries as $summary) {
            $included = $summary->items->where('is_included', true);
            $base = $included->sum(fn ($item): float => (float) $item->allowance_amount);
            $summary->update([
                'trip_count' => $included->count(),
                'base_amount' => $base,
                'final_amount' => $base + (float) $summary->adjustment_amount,
            ]);
        }

        $period->summaries()->whereDoesntHave('items')->delete();
        $period->update([
            'driver_count' => $period->summaries()->count(),
            'trip_count' => $period->summaries()->sum('trip_count'),
            'total_amount' => $period->summaries()->sum('final_amount'),
        ]);
    }

    private function assertOpen(DriverMealAllowancePeriod $period): void
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['period' => 'Periode sudah finalized dan tidak dapat diubah.']);
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dateRange(int $year, int $month): array
    {
        $cutoff = DriverTripSettings::instance()->report_cutoff_day;
        $end = Carbon::create($year, $month, $cutoff)->subDay()->startOfDay();
        $start = $end->copy()->subMonth()->addDay();

        return [$start, $end];
    }
}
