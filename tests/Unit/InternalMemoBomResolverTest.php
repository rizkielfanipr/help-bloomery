<?php

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoBomResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    Http::fake(['https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']])]);
    $memo = RndInternalMemo::factory()->create();
    $this->menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'esb_bom_id' => 501]);
});

it('resolves a single-level BOM with one raw material and no Assembly', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomCode' => 'BOM-000501',
            'bomName' => 'Croissant Butter - Menu',
            'bomDetails' => [
                ['productDetailID' => 15002, 'productID' => 9002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung Terigu', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR', 'tolerancePercent' => 5.0],
            ],
        ])]),
    ]);

    $result = app(InternalMemoBomResolver::class)->resolve($this->menu);

    expect($result['blockers'])->toBe([])
        ->and($result['warnings'])->toBe([]);

    $material = $this->menu->materials()->sole();
    expect($material->product_code)->toBe('RAW-FLOUR')
        ->and((float) $material->quantity_per_menu)->toBe(250.0)
        ->and((float) $material->net_quantity)->toBe(0.0)
        ->and($material->is_wip)->toBeFalse()
        ->and($material->is_packaging)->toBeFalse()
        ->and($material->depth)->toBe(0)
        ->and($material->parent_material_id)->toBeNull()
        ->and($material->source_bom_id)->toBe(501);
});

it('ignores tolerancePercent and never applies waste or an output-yield division', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomDetails' => [
                ['productDetailID' => 15002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 100.0, 'uomName' => 'GR', 'tolerancePercent' => 50.0],
            ],
        ])]),
    ]);

    app(InternalMemoBomResolver::class)->resolve($this->menu);

    // Exactly the raw BOM qty; a tolerance/waste/yield calculation would have produced a different number.
    expect((float) $this->menu->materials()->sole()->quantity_per_menu)->toBe(100.0);
});

it('recurses into a one-level WIP/Assembly and multiplies quantities through it', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomName' => 'Croissant Butter - Menu',
            'bomDetails' => [
                ['productDetailID' => 15003, 'productID' => 9003, 'productCode' => 'BW1356', 'productName' => 'Croissant Dough WIP', 'categoryName' => 'Barang WIP', 'qty' => 2.0, 'uomName' => 'PCS'],
            ],
        ])]),
        'https://esb.test/core/product/bom?*' => Http::response(['status' => 'ok', 'result' => ['data' => [['bomID' => 7301, 'bomCode' => 'BOM-007301']]]]),
        'https://esb.test/core/product/bom/7301' => Http::response(['status' => 'ok', 'result' => internalMemoAssemblyBomDetailFixture(['bomDetails' => [
            ['productDetailID' => 15002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR'],
        ]])]),
    ]);

    $result = app(InternalMemoBomResolver::class)->resolve($this->menu);

    expect($result['blockers'])->toBe([]);

    $materials = $this->menu->materials()->orderBy('depth')->get();
    expect($materials)->toHaveCount(2);

    $wip = $materials->firstWhere('is_wip', true);
    $raw = $materials->firstWhere('is_wip', false);

    expect($wip->product_code)->toBe('BW1356')
        ->and((float) $wip->quantity_per_menu)->toBe(2.0)
        ->and($wip->depth)->toBe(0)
        ->and($raw->depth)->toBe(1)
        ->and($raw->parent_material_id)->toBe($wip->id)
        ->and($raw->source_bom_id)->toBe(7301)
        ->and((float) $raw->quantity_per_menu)->toBe(500.0) // 250 (per Assembly) x 2 (Assembly needed per Menu)
        ->and($raw->source_path)->toBe(['Croissant Butter - Menu', 'Croissant Dough WIP']);
});

it('recurses through two Assembly levels and multiplies the full chain', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomDetails' => [
                ['productDetailID' => 1, 'productCode' => 'BW-A', 'productName' => 'Assembly A', 'categoryName' => 'Barang WIP', 'qty' => 3.0, 'uomName' => 'PCS'],
            ],
        ])]),
        'https://esb.test/core/product/bom?*' => Http::sequence()
            ->push(['status' => 'ok', 'result' => ['data' => [['bomID' => 101]]]])
            ->push(['status' => 'ok', 'result' => ['data' => [['bomID' => 102]]]]),
        'https://esb.test/core/product/bom/101' => Http::response(['status' => 'ok', 'result' => internalMemoAssemblyBomDetailFixture(['bomID' => 101, 'bomName' => 'Assembly A BOM', 'productCode' => 'BW-A', 'bomDetails' => [
            ['productDetailID' => 2, 'productCode' => 'BW-B', 'productName' => 'Assembly B', 'categoryName' => 'Barang WIP', 'qty' => 4.0, 'uomName' => 'PCS'],
        ]])]),
        'https://esb.test/core/product/bom/102' => Http::response(['status' => 'ok', 'result' => internalMemoAssemblyBomDetailFixture(['bomID' => 102, 'bomName' => 'Assembly B BOM', 'productCode' => 'BW-B', 'bomDetails' => [
            ['productDetailID' => 3, 'productCode' => 'RAW-SUGAR', 'productName' => 'Gula', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 10.0, 'uomName' => 'GR'],
        ]])]),
    ]);

    app(InternalMemoBomResolver::class)->resolve($this->menu);

    $raw = $this->menu->materials()->where('product_code', 'RAW-SUGAR')->sole();
    expect($raw->depth)->toBe(2)
        ->and((float) $raw->quantity_per_menu)->toBe(120.0); // 10 x 4 x 3
});

it('reports a blocker and keeps the WIP row when no matching Assembly BOM is found', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomDetails' => [
                ['productDetailID' => 15003, 'productCode' => 'BW9999', 'productName' => 'WIP Tanpa BOM', 'categoryName' => 'Barang WIP', 'qty' => 1.0, 'uomName' => 'PCS'],
            ],
        ])]),
        'https://esb.test/core/product/bom?*' => Http::response(['status' => 'ok', 'result' => ['data' => []]]),
    ]);

    $result = app(InternalMemoBomResolver::class)->resolve($this->menu);

    expect($result['blockers'])->toHaveCount(1)
        ->and($result['blockers'][0])->toContain('WIP Tanpa BOM')->toContain('belum memiliki BOM turunan');

    $wip = $this->menu->materials()->sole();
    expect($wip->is_wip)->toBeTrue()
        ->and($wip->isConsolidationCandidate())->toBeFalse();
});

it('reports a circular BOM blocker and stops recursing instead of looping forever', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'productDetailID' => 1,
            'productCode' => 'BW-LOOP',
            'bomDetails' => [
                ['productDetailID' => 1, 'productCode' => 'BW-LOOP', 'productName' => 'Assembly Loop', 'categoryName' => 'Barang WIP', 'qty' => 1.0, 'uomName' => 'PCS'],
            ],
        ])]),
        'https://esb.test/core/product/bom?*' => Http::response(['status' => 'ok', 'result' => ['data' => [['bomID' => 501]]]]),
    ]);

    $result = app(InternalMemoBomResolver::class)->resolve($this->menu);

    expect($result['blockers'])->toHaveCount(1)
        ->and($result['blockers'][0])->toContain('Circular BOM');
    expect($this->menu->materials()->count())->toBe(1);
});

it('flags a Packaging component distinctly from a raw material', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomDetails' => [
                ['productDetailID' => 1, 'productCode' => 'PKG-BOX', 'productName' => 'Kotak Kemasan', 'categoryName' => 'Packaging', 'qty' => 1.0, 'uomName' => 'PCS'],
            ],
        ])]),
    ]);

    app(InternalMemoBomResolver::class)->resolve($this->menu);

    $material = $this->menu->materials()->sole();
    expect($material->is_packaging)->toBeTrue()
        ->and($material->is_wip)->toBeFalse();
});

it('adds a warning instead of a blocker when a component has no identity beyond its name', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomDetails' => [
                ['productDetailID' => null, 'productID' => null, 'productCode' => null, 'productName' => 'Bahan Tanpa Identitas', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 1.0, 'uomName' => 'PCS'],
            ],
        ])]),
    ]);

    $result = app(InternalMemoBomResolver::class)->resolve($this->menu);

    expect($result['blockers'])->toBe([])
        ->and($result['warnings'])->not->toBeEmpty()
        ->and(collect($result['warnings'])->implode(' '))->toContain('Bahan Tanpa Identitas');
    expect($this->menu->materials()->sole()->product_code)->toBeNull();
});

it('replaces materials on re-sync instead of duplicating them', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomDetails' => [
                ['productDetailID' => 15002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR'],
            ],
        ])]),
    ]);

    $resolver = app(InternalMemoBomResolver::class);
    $resolver->resolve($this->menu);
    $resolver->resolve($this->menu);

    expect($this->menu->materials()->count())->toBe(1);
});

it('caches the Assembly search so the same WIP appearing twice is only looked up once', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/501' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 501,
            'bomDetails' => [
                ['productDetailID' => 1, 'productID' => 9500, 'productCode' => 'BW-DUP', 'productName' => 'Assembly Dup', 'categoryName' => 'Barang WIP', 'qty' => 1.0, 'uomName' => 'PCS'],
                ['productDetailID' => 1, 'productID' => 9500, 'productCode' => 'BW-DUP', 'productName' => 'Assembly Dup', 'categoryName' => 'Barang WIP', 'qty' => 2.0, 'uomName' => 'PCS'],
            ],
        ])]),
        'https://esb.test/core/product/bom?*' => Http::response(['status' => 'ok', 'result' => ['data' => [['bomID' => 900]]]]),
        'https://esb.test/core/product/bom/900' => Http::response(['status' => 'ok', 'result' => internalMemoAssemblyBomDetailFixture(['bomID' => 900, 'productDetailID' => 1, 'bomDetails' => []])]),
    ]);

    app(InternalMemoBomResolver::class)->resolve($this->menu);

    Http::assertSentCount(1 // login
        + 1 // menu bom detail
        + 1 // product/bom search (first WIP occurrence only, second is cached by identity)
        + 1 // bom/900 detail (first only, second cached)
    );
});
