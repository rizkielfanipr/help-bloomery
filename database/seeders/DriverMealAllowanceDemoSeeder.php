<?php

namespace Database\Seeders;

use App\Enums\TripStatus;
use App\Models\DriverMealAllowancePeriod;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverMealAllowanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverMealAllowanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        DriverTripSettings::instance()->update(['report_cutoff_day' => 20]);

        $drivers = collect([
            ['username' => 'BLDemoDriver01', 'name' => 'Demo Driver Andi'],
            ['username' => 'BLDemoDriver02', 'name' => 'Demo Driver Budi'],
            ['username' => 'BLDemoDriver03', 'name' => 'Demo Driver Candra'],
        ])->map(function (array $data): User {
            $driver = User::firstOrCreate(
                ['username' => $data['username']],
                [
                    'name' => $data['name'],
                    'email' => null,
                    'password' => Hash::make('DemoDriver123!'),
                    'is_active' => true,
                ],
            );
            $driver->assignRole('DRIVER');

            return $driver;
        });

        $vehicle = Vehicle::firstOrCreate(
            ['license_plate' => 'H 1707 DEMO'],
            ['brand' => 'Toyota', 'model' => 'Hiace Demo', 'year' => 2024, 'is_active' => true],
        );

        $routes = collect([
            ['name' => 'Demo Gudang – Toko Utama', 'meal_allowance_amount' => 25000],
            ['name' => 'Demo Gudang – Cabang Selatan', 'meal_allowance_amount' => 35000],
            ['name' => 'Demo Antar Kota', 'meal_allowance_amount' => 50000],
        ])->map(fn (array $data): TripRoute => TripRoute::firstOrCreate(
            ['name' => $data['name']],
            $data + ['description' => 'Rute khusus demonstrasi uang makan driver', 'is_active' => true],
        ));

        $tripRows = [
            ['DEMO-JUL-001', 0, 0, '2026-06-20'],
            ['DEMO-JUL-002', 0, 1, '2026-06-27'],
            ['DEMO-JUL-003', 0, 2, '2026-07-05'],
            ['DEMO-JUL-004', 1, 0, '2026-06-22'],
            ['DEMO-JUL-005', 1, 1, '2026-07-01'],
            ['DEMO-JUL-006', 1, 1, '2026-07-19'],
            ['DEMO-JUL-007', 2, 2, '2026-06-25'],
            ['DEMO-JUL-008', 2, 0, '2026-07-08'],
            ['DEMO-JUL-009', 2, 1, '2026-07-15'],
        ];

        foreach ($tripRows as [$code, $driverIndex, $routeIndex, $date]) {
            $route = $routes[$routeIndex];
            Trip::updateOrCreate(
                ['code' => $code],
                [
                    'driver_id' => $drivers[$driverIndex]->id,
                    'vehicle_id' => $vehicle->id,
                    'trip_route_id' => $route->id,
                    'trip_date' => $date,
                    'status' => TripStatus::Completed,
                    'started_at' => $date.' 08:00:00',
                    'completed_at' => $date.' 16:00:00',
                    'meal_allowance_amount' => $route->meal_allowance_amount,
                    'notes' => '[DEMO UANG MAKAN DRIVER] Cutoff Juli 2026',
                ],
            );
        }

        $creator = User::role('SUPERADMIN')->firstOrFail();
        $service = app(DriverMealAllowanceService::class);
        $period = DriverMealAllowancePeriod::where('report_year', 2026)
            ->where('report_month', 7)
            ->first();

        if (! $period) {
            $period = DriverMealAllowancePeriod::create([
                'report_year' => 2026,
                'report_month' => 7,
                'start_date' => '2026-06-20',
                'end_date' => '2026-07-19',
                'status' => 'open',
                'is_demo' => true,
                'created_by' => $creator->id,
            ]);
            $service->sync($period);
        } elseif (! $period->is_demo) {
            $this->command?->warn('Periode payroll Juli 2026 sudah ada. Seeder demo dibatalkan agar data operasional tidak tercampur.');

            return;
        } elseif ($period->isOpen()) {
            $service->sync($period);
        }

        if (! $period->isOpen()) {
            $this->command?->warn('Periode Juli 2026 sudah finalized. Trip demo dibuat, tetapi rekap tidak diubah.');

            return;
        }

        $period->load('summaries.driver', 'summaries.items');
        $andi = $period->summaries->first(fn ($summary): bool => $summary->driver->username === 'BLDemoDriver01');
        if ($andi) {
            $service->updateAdjustment($andi, -5000, 'Demo koreksi uang makan perjalanan parsial', $creator->id);
        }

        $budi = $period->summaries->first(fn ($summary): bool => $summary->driver->username === 'BLDemoDriver02');
        $excludedTrip = $budi?->items->firstWhere('trip_code', 'DEMO-JUL-005');
        if ($excludedTrip) {
            $service->updateItem($excludedTrip, (float) $excludedTrip->allowance_amount, false, 'Demo trip dibatalkan dari perhitungan payroll');
        }

        $this->command?->info('Demo terisolasi uang makan driver Juli 2026 berhasil dibuat (20 Jun–19 Jul 2026).');
    }
}
