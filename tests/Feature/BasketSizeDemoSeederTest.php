<?php

use App\Models\BasketSizeEmployeeRecord;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\User;
use Database\Seeders\BasketSizeDemoSeeder;

it('seeds repeatable basket size demo data', function () {
    $this->seed(BasketSizeDemoSeeder::class);
    $this->seed(BasketSizeDemoSeeder::class);

    $branch = Branch::query()->where('name', 'Bloomery Demo Basket Size')->firstOrFail();

    expect($branch->salesShifts()->count())->toBe(2)
        ->and($branch->employees()->count())->toBe(5)
        ->and(SalesReport::query()->whereBelongsTo($branch)->count())->toBe(14)
        ->and(BasketSizeRecord::query()->whereBelongsTo($branch)->count())->toBe(28)
        ->and(BasketSizeEmployeeRecord::query()->count())->toBeGreaterThan(28)
        ->and(User::query()->where('username', 'DemoBasketFinance')->firstOrFail()->hasRole('FINANCE_STAFF'))->toBeTrue();

    $record = BasketSizeRecord::query()->whereBelongsTo($branch)->firstOrFail();

    expect((float) $record->basket_size)
        ->toEqualWithDelta((float) $record->revenue / $record->total_pax, 0.01)
        ->and($record->employeeRecords)->toHaveCount($record->staff_count);
});
