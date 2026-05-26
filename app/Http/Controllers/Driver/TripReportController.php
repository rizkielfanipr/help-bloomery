<?php

namespace App\Http\Controllers\Driver;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TripReportController extends Controller
{
    public function pdf(Request $request): Response
    {
        $user = $request->user();

        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $cutoff = DriverTripSettings::instance()->report_cutoff_day;

        $periodStart = Carbon::createFromDate($year, $month, $cutoff)->subMonth();
        $periodEnd = Carbon::createFromDate($year, $month, $cutoff)->subDay();

        $trips = Trip::where('driver_id', $user->id)
            ->where('status', TripStatus::Completed)
            ->whereBetween('trip_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->with(['tripRoute', 'vehicle', 'fuelFillup', 'waypointCheckins.waypoint'])
            ->orderBy('trip_date')
            ->get();

        $totalMealAllowance = $trips->sum('meal_allowance_amount');
        $totalFuelCost = $trips->sum(fn ($t) => $t->fuelFillup?->total_price ?? 0);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $pdf = Pdf::loadView('pdf.driver-trip-report', [
            'driver' => $user,
            'trips' => $trips,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthNames[$month],
            'totalMealAllowance' => $totalMealAllowance,
            'totalFuelCost' => $totalFuelCost,
        ])->setPaper('a4', 'portrait');

        $filename = 'laporan-perjalanan-'.$user->name.'-'.$monthNames[$month].'-'.$year.'.pdf';

        return $pdf->download($filename);
    }
}
