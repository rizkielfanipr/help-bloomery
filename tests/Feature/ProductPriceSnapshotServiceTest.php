<?php

use App\Models\EsbPurchaseOrder;
use App\Models\EsbPurchaseOrderItem;
use App\Models\ProductPriceSnapshot;
use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\User;
use App\Services\ProductPriceSnapshotService;
use App\Services\WipPriceIndexService;
use Illuminate\Support\Carbon;

it('stores a weekly weighted average snapshot from local purchase orders', function () {
    $order = EsbPurchaseOrder::query()->create([
        'purchase_num' => 'PO-SNAPSHOT-1',
        'purchase_date' => '2026-09-20',
        'rate' => 1,
    ]);
    EsbPurchaseOrderItem::query()->create([
        'esb_purchase_order_id' => $order->id,
        'esb_detail_id' => 1,
        'product_detail_id' => 501,
        'product_code' => 'BBMK001',
        'product_name' => 'Tepung',
        'uom_name' => 'GR',
        'qty' => 10,
        'stock_qty' => 10,
        'total' => 1000,
        'vat' => 0,
    ]);

    $count = app(ProductPriceSnapshotService::class)->create(
        Carbon::parse('2026-09-21'),
        Carbon::parse('2026-06-24'),
        Carbon::parse('2026-09-21'),
    );

    expect($count)->toBe(1);
    $snapshot = ProductPriceSnapshot::query()->firstOrFail();
    expect((float) $snapshot->weighted_average_price)->toBe(100.0)
        ->and($snapshot->purchase_count)->toBe(1);
});

it('calculates WIP price from the latest material price snapshot', function () {
    ProductPriceSnapshot::factory()->create([
        'snapshot_date' => '2026-09-21',
        'product_detail_id' => 501,
        'weighted_average_price' => 20,
    ]);
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'WIP Price',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'created_by' => $user->id,
    ]);
    RndProjectBom::query()->create([
        'rnd_project_id' => $project->id,
        'esb_bom_id' => 100,
        'bom_name' => 'Adonan',
        'product_name' => 'WIP Adonan',
        'is_active' => true,
        'created_by' => $user->id,
        'detail_snapshot' => [
            'productDetailID' => 900,
            'productCode' => 'BW900',
            'productName' => 'WIP Adonan',
            'uomName' => 'Resep',
            'bomDetails' => [[
                'productDetailID' => 501,
                'productCode' => 'BBMK001',
                'productName' => 'Tepung',
                'qty' => 100,
            ]],
        ],
    ]);

    $row = app(WipPriceIndexService::class)->prices()->first();

    expect($row['price'])->toBe(2000.0)
        ->and($row['complete'])->toBeTrue()
        ->and($row['material_count'])->toBe(1);
});

it('calculates a parent WIP price proportionally from the child recipe output', function () {
    ProductPriceSnapshot::factory()->create([
        'snapshot_date' => '2026-09-21',
        'product_detail_id' => 501,
        'weighted_average_price' => 20,
    ]);
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Nested WIP Price',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'created_by' => $user->id,
    ]);
    RndProjectBom::query()->create([
        'rnd_project_id' => $project->id,
        'esb_bom_id' => 101,
        'bom_name' => 'Froyo Mix',
        'product_name' => 'WIP Froyo Mix',
        'is_active' => true,
        'created_by' => $user->id,
        'detail_snapshot' => [
            'productDetailID' => 901,
            'productCode' => 'BW901',
            'productName' => 'WIP Froyo Mix',
            'categoryName' => 'Barang WIP',
            'conversionFactor' => 6000,
            'bomDetails' => [[
                'productDetailID' => 501,
                'productCode' => 'BBMK001',
                'productName' => 'Susu',
                'qty' => 3000,
            ]],
        ],
    ]);
    RndProjectBom::query()->create([
        'rnd_project_id' => $project->id,
        'esb_bom_id' => 102,
        'bom_name' => 'Parent',
        'product_name' => 'WIP Parent',
        'is_active' => true,
        'created_by' => $user->id,
        'detail_snapshot' => [
            'productDetailID' => 902,
            'productCode' => 'BW902',
            'productName' => 'WIP Parent',
            'categoryName' => 'Barang WIP',
            'bomDetails' => [[
                'productDetailID' => 901,
                'productCode' => 'BW901',
                'productName' => 'WIP Froyo Mix',
                'categoryName' => 'Barang WIP',
                'qty' => 130,
            ]],
        ],
    ]);

    $parent = app(WipPriceIndexService::class)->prices()->firstWhere('product_code', 'BW902');

    expect($parent['price'])->toBe(1300.0);
});
