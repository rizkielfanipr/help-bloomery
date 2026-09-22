<?php

use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ItRequestType;
use App\Services\TableFilterOptions;
use Illuminate\Support\Facades\DB;

it('returns branch filter options sorted by name and reuses them during the request', function () {
    $branchB = Branch::factory()->create(['name' => 'Zulu Branch']);
    $branchA = Branch::factory()->create(['name' => 'Alpha Branch']);
    $service = app(TableFilterOptions::class);

    DB::enableQueryLog();
    $first = $service->branches();
    $queryCount = count(DB::getQueryLog());
    $second = $service->branches();

    expect($first)->toBe([
        $branchA->id => 'Alpha Branch',
        $branchB->id => 'Zulu Branch',
    ])->and($second)->toBe($first)
        ->and(count(DB::getQueryLog()))->toBe($queryCount);
});

it('returns only active ERP modules and request types in their configured order', function () {
    ErpModule::query()->update(['is_active' => false]);
    ItRequestType::query()->update(['is_active' => false]);

    $moduleSecond = ErpModule::query()->create(['name' => 'Inventory', 'sort_order' => 20, 'is_active' => true]);
    $moduleFirst = ErpModule::query()->create(['name' => 'Sales', 'sort_order' => 10, 'is_active' => true]);
    ErpModule::query()->create(['name' => 'Inactive Module', 'sort_order' => 1, 'is_active' => false]);

    $typeSecond = ItRequestType::query()->create(['name' => 'Phase Three Project', 'sort_order' => 20, 'is_active' => true]);
    $typeFirst = ItRequestType::query()->create(['name' => 'Phase Three Ticket', 'sort_order' => 10, 'is_active' => true]);
    ItRequestType::query()->create(['name' => 'Inactive Type', 'sort_order' => 1, 'is_active' => false]);

    $service = app(TableFilterOptions::class);

    expect($service->erpModules())->toBe([
        $moduleFirst->id => 'Sales',
        $moduleSecond->id => 'Inventory',
    ])->and($service->itRequestTypes())->toBe([
        $typeFirst->id => 'Phase Three Ticket',
        $typeSecond->id => 'Phase Three Project',
    ]);
});
