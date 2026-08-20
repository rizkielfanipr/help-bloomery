<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AllUsersSeeder::class,
            CasualTestDataSeeder::class,
            FuelTypeSeeder::class,
            PurchaseRequestSeeder::class,
            VehicleSeeder::class,
            DesignRequestSeeder::class,
            ErpRepairRequestSeeder::class,
            PurchasingRequestSeeder::class,
            EmployeeSeeder::class,
            SalesReportSeeder::class,
            ServiceRequestSeeder::class,
            TripSeeder::class,
            QualityControlChecklistSeeder::class,
            QualityControlAuditSeeder::class,
        ]);
    }
}
