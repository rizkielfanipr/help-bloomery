<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\QualityControlAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QualityControlAudit>
 */
class QualityControlAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_number' => 'QC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'branch_id' => Branch::factory(),
            'auditor_id' => User::factory(),
            'audit_date' => today(),
            'audit_type' => 'routine',
            'store_leader_present' => false,
            'status' => 'draft',
        ];
    }
}
