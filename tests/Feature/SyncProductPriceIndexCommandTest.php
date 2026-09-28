<?php

use App\Models\ProductPriceSyncRun;
use App\Services\ProductPriceSnapshotService;
use App\Services\PurchaseOrderPriceSyncService;
use Illuminate\Support\Carbon;

it('records a successful automatic product price sync run', function () {
    Carbon::setTestNow('2026-09-21 01:00:00');
    $sync = Mockery::mock(PurchaseOrderPriceSyncService::class);
    $sync->shouldReceive('sync')
        ->once()
        ->with('2026-06-24', '2026-09-21', 100, 1000)
        ->andReturn(['orders' => 4, 'items' => 12, 'failed' => 0, 'errors' => []]);
    $snapshots = Mockery::mock(ProductPriceSnapshotService::class);
    $snapshots->shouldReceive('create')->once()->andReturn(8);
    app()->instance(PurchaseOrderPriceSyncService::class, $sync);
    app()->instance(ProductPriceSnapshotService::class, $snapshots);

    $this->artisan('product-price-index:sync')->assertSuccessful();

    $run = ProductPriceSyncRun::query()->firstOrFail();
    expect($run->status)->toBe('completed')
        ->and($run->orders_synced)->toBe(4)
        ->and($run->products_snapshotted)->toBe(8);
});
