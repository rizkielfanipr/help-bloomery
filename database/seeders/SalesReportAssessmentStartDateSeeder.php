<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class SalesReportAssessmentStartDateSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->update(['sales_assessment_started_at' => '2026-09-01']);
    }
}
