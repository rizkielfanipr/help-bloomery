<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\TechnicianMonthlyMaintenance;
use Illuminate\Database\Seeder;

class TechnicianMonthlyMaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();
        $currentYear = now()->year;
        $currentMonth = now()->month;

        foreach ($branches as $branch) {
            TechnicianMonthlyMaintenance::updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'year' => $currentYear,
                    'month' => $currentMonth,
                ],
                [
                    'points' => rand(0, 100), // random points for demo
                ]
            );
        }
    }
}
