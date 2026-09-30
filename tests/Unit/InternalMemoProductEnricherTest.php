<?php

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoProductEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set([
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);
    $memo = RndInternalMemo::factory()->create();
    $this->menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
});

it('stores the raw Product detail snapshot but leaves Purchase UOM unset since the contract is unproven', function () {
    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'data' => [[
                    'productID' => 10, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung',
                    'productDetails' => [
                        ['productDetailID' => 101, 'unit' => 'KG', 'conversionFactor' => 1000, 'defaultUnit' => ['baseUnit' => 'No']],
                    ],
                ]],
                'next' => '',
            ],
        ]),
    ]);
    $material = $this->menu->materials()->create([
        'esb_product_detail_id' => 101, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR',
        'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
    ]);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    $material->refresh();
    expect($material->hasPurchaseUom())->toBeFalse()
        ->and($material->purchase_uom_id)->toBeNull()
        ->and($material->purchase_uom_name)->toBeNull()
        ->and($material->product_detail_snapshot)->not->toBeNull()
        ->and($material->product_detail_snapshot['unit'])->toBe('KG')
        ->and($material->product_synced_at)->not->toBeNull();
});

it('fetches Product detail once for two materials sharing the same Product Detail ID', function () {
    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => ['page' => 1, 'data' => [[
                'productID' => 10, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung',
                'productDetails' => [['productDetailID' => 101, 'unit' => 'KG', 'defaultUnit' => ['baseUnit' => 'Yes']]],
            ]], 'next' => ''],
        ]),
    ]);
    $this->menu->materials()->create(['esb_product_detail_id' => 101, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_bom_code' => 'A', 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $this->menu->materials()->create(['esb_product_detail_id' => 101, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 125, 'net_quantity' => 0, 'source_bom_id' => 2, 'source_bom_code' => 'B', 'source_path' => [], 'depth' => 1, 'is_wip' => false, 'is_packaging' => false]);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    Http::assertSentCount(1);
    expect($this->menu->materials()->whereNotNull('product_synced_at')->count())->toBe(2);
});

it('marks a material without a Product Detail ID as checked without calling ESB', function () {
    $material = $this->menu->materials()->create([
        'product_code' => null, 'product_name' => 'Tanpa Identitas', 'uom_name' => 'PCS',
        'quantity_per_menu' => 1, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
    ]);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    Http::assertNothingSent();
    expect($material->fresh()->product_synced_at)->not->toBeNull()
        ->and($material->fresh()->hasPurchaseUom())->toBeFalse();
});

it('keeps the BOM component snapshot untouched by enrichment', function () {
    Http::fake(['https://master-product.test/corev1/master/product*' => Http::response(['status' => 'ok', 'result' => ['page' => 1, 'data' => [], 'next' => '']])]);
    $material = $this->menu->materials()->create([
        'esb_product_detail_id' => 999, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR',
        'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
        'product_snapshot' => ['productCode' => 'RAW-FLOUR', 'qty' => 250],
    ]);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    $material->refresh();
    expect($material->product_snapshot)->toBe(['productCode' => 'RAW-FLOUR', 'qty' => 250])
        ->and($material->product_detail_snapshot)->toBeNull();
});
