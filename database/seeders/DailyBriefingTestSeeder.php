<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DailyBriefingTestSeeder extends Seeder
{
    /**
     * Seed the minimum shared reference data required by Daily Briefing tests.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            BriefingTasksSeeder::class,
        ]);
    }
}
