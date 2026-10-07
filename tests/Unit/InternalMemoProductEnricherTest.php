<?php

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoProductEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set([
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.companies.BLSS' => ['username' => 'memo-user', 'password' => 'memo-secret'],
    ]);
    Cache::put('esb_core.access_token.BLSS', 'core-token');
    $memo = RndInternalMemo::factory()->create();
    $this->menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $this->material = fn (array $attributes = []) => $this->menu->materials()->create([
        'esb_product_id' => 10, 'esb_product_detail_id' => 101, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR',
        'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
        ...$attributes,
    ]);
});

/** @param list<array<string, mixed>> $details */
function esbProductFixture(int $productId, array $details): array
{
    return ['status' => 'ok', 'result' => ['productCode' => 'P-'.$productId, 'productName' => 'Produk '.$productId, 'productDetails' => $details]];
}

it('takes Purchase UOM from the one active purchasing unit of the ESB Product', function () {
    Http::fake(['https://core-esb.test/product/10' => Http::response(esbProductFixture(10, [
        ['productDetailID' => 101, 'uomID' => 5, 'uomName' => 'GR', 'qty' => 1, 'isBase' => true, 'isPurchase' => false, 'flagActive' => true],
        ['productDetailID' => 102, 'uomID' => 31, 'uomName' => 'PACK@525GR', 'qty' => 525, 'isBase' => false, 'isPurchase' => true, 'flagActive' => true],
        ['productDetailID' => 103, 'uomID' => 9, 'uomName' => 'PACK', 'qty' => 456, 'isBase' => false, 'isPurchase' => true, 'flagActive' => false],
    ]))]);
    $material = ($this->material)();

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    $material->refresh();
    expect($material->only(['purchase_uom_id', 'purchase_uom_name']))->toBe(['purchase_uom_id' => 31, 'purchase_uom_name' => 'PACK@525GR'])
        ->and($material->hasPurchaseUom())->toBeTrue()
        ->and($material->product_detail_snapshot['matchedProductDetail']['uomName'])->toBe('GR')
        ->and($material->product_detail_snapshot['purchaseProductDetail']['uomName'])->toBe('PACK@525GR')
        ->and($material->product_synced_at)->not->toBeNull();
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer core-token'));
});

it('does not guess a Purchase UOM when the Product has none or more than one active purchasing unit', function () {
    Http::fake([
        'https://core-esb.test/product/10' => Http::response(esbProductFixture(10, [
            ['productDetailID' => 101, 'uomID' => 5, 'uomName' => 'GR', 'isPurchase' => false, 'flagActive' => true],
        ])),
        'https://core-esb.test/product/20' => Http::response(esbProductFixture(20, [
            ['productDetailID' => 201, 'uomID' => 1, 'uomName' => 'PCS', 'isPurchase' => true, 'flagActive' => true],
            ['productDetailID' => 202, 'uomID' => 2, 'uomName' => 'BOX', 'isPurchase' => true, 'flagActive' => true],
        ])),
    ]);
    $none = ($this->material)();
    $ambiguous = ($this->material)(['esb_product_id' => 20, 'esb_product_detail_id' => 201, 'product_code' => 'RAW-BOX']);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    expect($none->fresh()->hasPurchaseUom())->toBeFalse()
        ->and($ambiguous->fresh()->hasPurchaseUom())->toBeFalse()
        ->and($ambiguous->fresh()->product_synced_at)->not->toBeNull();
});

it('reads each ESB Product once even when several materials share it', function () {
    Http::fake(['https://core-esb.test/product/10' => Http::response(esbProductFixture(10, [
        ['productDetailID' => 101, 'uomID' => 5, 'uomName' => 'KG', 'isPurchase' => true, 'flagActive' => true],
    ]))]);
    ($this->material)();
    ($this->material)(['source_bom_id' => 2, 'depth' => 1, 'quantity_per_menu' => 125]);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    Http::assertSentCount(1);
    expect($this->menu->materials()->where('purchase_uom_name', 'KG')->count())->toBe(2);
});

it('uses the Menu Company Code when reading Products', function () {
    config()->set('esb.core.companies.BLO6', ['username' => 'blo6-user', 'password' => 'blo6-secret']);
    Cache::put('esb_core.access_token.BLO6', 'blo6-token');
    $this->menu->update(['company_code' => 'BLO6']);
    ($this->material)(['esb_product_id' => 20, 'esb_product_detail_id' => 202]);
    Http::fake(['https://core-esb.test/product/20' => Http::response(esbProductFixture(20, [
        ['productDetailID' => 202, 'uomID' => 7, 'uomName' => 'ML', 'isPurchase' => true, 'flagActive' => true],
    ]))]);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer blo6-token'));
});

it('marks a material without a Product ID as checked without calling ESB', function () {
    Http::fake();
    $material = ($this->material)(['esb_product_id' => null, 'esb_product_detail_id' => null, 'product_code' => null, 'product_name' => 'Tanpa Identitas']);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    Http::assertNothingSent();
    expect($material->fresh()->product_synced_at)->not->toBeNull()
        ->and($material->fresh()->hasPurchaseUom())->toBeFalse();
});

it('keeps the BOM component snapshot untouched and survives a failed Product read', function () {
    Http::fake(['https://core-esb.test/product/10' => Http::response(['status' => 'error'], 500)]);
    $material = ($this->material)(['product_snapshot' => ['productCode' => 'RAW-FLOUR', 'qty' => 250]]);

    app(InternalMemoProductEnricher::class)->enrichMenu($this->menu);

    $material->refresh();
    expect($material->product_snapshot)->toBe(['productCode' => 'RAW-FLOUR', 'qty' => 250])
        ->and($material->product_detail_snapshot)->toBeNull()
        ->and($material->hasPurchaseUom())->toBeFalse()
        ->and($material->product_synced_at)->not->toBeNull();
});
