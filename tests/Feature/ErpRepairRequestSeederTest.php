<?php

use App\Enums\ItRequestStatus;
use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use App\Models\User;
use Database\Seeders\ErpRepairRequestSeeder;

it('seeds coherent ERP request and Warranty demo data', function () {
    User::factory()->create();
    Branch::factory()->create();
    ErpModule::firstOrCreate(['name' => 'Sales'], ['sort_order' => 1, 'is_active' => true]);
    ItRequestType::firstOrCreate(['name' => 'Ticketing'], ['priority' => 'medium', 'sort_order' => 1, 'is_active' => true]);

    $this->seed(ErpRepairRequestSeeder::class);

    expect(ErpRepairRequest::count())->toBe(25)
        ->and(ErpRepairRequest::where('keterangan', '[DEMO] Tiket dalam masa Warranty aktif.')
            ->where('status', ItRequestStatus::Warranty)
            ->whereNotNull('warranty_started_at')
            ->where('warranty_expires_at', '>', now())
            ->exists())->toBeTrue()
        ->and(ErpRepairRequest::where('keterangan', '[DEMO] Warranty kedaluwarsa untuk pengujian auto-complete.')
            ->where('status', ItRequestStatus::Warranty)
            ->where('warranty_expires_at', '<=', now())
            ->exists())->toBeTrue()
        ->and(ErpRepairRequest::where('status', ItRequestStatus::Warranty)
            ->where(fn ($query) => $query->whereNull('warranty_started_at')->orWhereNull('warranty_expires_at'))
            ->exists())->toBeFalse();
});
