<?php

namespace Database\Factories;

use App\Models\SalesReportSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesReportSettings> */
class SalesReportSettingsFactory extends Factory
{
    public function definition(): array
    {
        return ['auto_reject_enabled' => false, 'auto_reject_after_days' => 3,
            'auto_reject_reason' => 'Tidak ada approval Supervisor dalam :days hari.', 'effective_from' => '2026-09-01'];
    }
}
