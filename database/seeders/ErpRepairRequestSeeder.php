<?php

namespace Database\Seeders;

use App\Enums\ItRequestStatus;
use App\Models\ErpRepairRequest;
use Illuminate\Database\Seeder;

class ErpRepairRequestSeeder extends Seeder
{
    public function run(): void
    {
        ErpRepairRequest::factory(22)->create();

        ErpRepairRequest::factory()->create([
            'keterangan' => '[DEMO] Tiket dalam masa Warranty aktif.',
            'status' => ItRequestStatus::Warranty,
            'submitted_at' => now()->subWeekdays(2),
            'resolved_at' => now(),
            'warranty_started_at' => now(),
            'warranty_expires_at' => now()->addWeekdays(3),
        ]);

        ErpRepairRequest::factory()->create([
            'keterangan' => '[DEMO] Warranty kedaluwarsa untuk pengujian auto-complete.',
            'status' => ItRequestStatus::Warranty,
            'submitted_at' => now()->subWeekdays(6),
            'resolved_at' => now()->subWeekdays(4),
            'warranty_started_at' => now()->subWeekdays(4),
            'warranty_expires_at' => now()->subMinute(),
        ]);

        ErpRepairRequest::factory()->create([
            'keterangan' => '[DEMO] Tiket sudah Completed setelah masa Warranty.',
            'status' => ItRequestStatus::Completed,
            'submitted_at' => now()->subWeekdays(8),
            'resolved_at' => now()->subWeekdays(6),
            'warranty_started_at' => now()->subWeekdays(6),
            'warranty_expires_at' => now()->subWeekdays(3),
        ]);
    }
}
