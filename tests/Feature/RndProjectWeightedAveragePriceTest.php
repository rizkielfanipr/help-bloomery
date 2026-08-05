<?php

use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Models\EsbPurchaseOrder;
use App\Models\EsbPurchaseOrderItem;
use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    $this->project = RndProject::query()->create([
        'name' => 'Test R&D Project',
        'start_date' => '2026-07-01',
        'end_date' => '2026-08-31',
        'created_by' => $admin->id,
    ]);
    $this->product = RndProjectProduct::query()->create([
        'rnd_project_id' => $this->project->id,
        'name' => 'Test Product Release',
        'product_code' => 'PRD-TEST',
        'status' => 'development',
        'created_by' => $admin->id,
    ]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/42' => Http::response(['status' => 'ok', 'result' => [
            'bomID' => 42, 'bomTypeName' => 'Assembly', 'bomName' => 'Croissant Assembly', 'bomCode' => 'BOM-CRS',
            'productDetailID' => 100, 'productName' => 'Croissant', 'uomName' => 'PCS',
            'bomDetails' => [
                ['ID' => 1, 'productDetailID' => 501, 'productCode' => 'BBMK001', 'productName' => 'Tepung Premium', 'uomName' => 'GR', 'qty' => 100, 'lastHPP' => 5000, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => ''],
                ['ID' => 2, 'productDetailID' => 999, 'productCode' => 'BBMK099', 'productName' => 'Barang Belum Pernah Dibeli', 'uomName' => 'GR', 'qty' => 10, 'lastHPP' => 2000, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => ''],
            ],
        ]]),
    ]);

    $this->projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'created_by' => $admin->id,
    ]);
    $this->product->boms()->attach($this->projectBom->id, ['usage_type' => 'main']);
});

function seedWaPurchase(string $date, int $productDetailId, float $qty, float $total): void
{
    $order = EsbPurchaseOrder::create(['purchase_num' => 'PO-'.uniqid(), 'purchase_date' => $date, 'rate' => 1, 'status_name' => 'Finished']);
    EsbPurchaseOrderItem::create([
        'esb_purchase_order_id' => $order->id,
        'esb_detail_id' => random_int(1, 999999),
        'product_detail_id' => $productDetailId,
        'product_code' => 'BBMK001',
        'product_name' => 'Tepung Premium',
        'uom_name' => 'GR',
        'qty' => $qty,
        'conversion_qty' => 1,
        'stock_qty' => $qty,
        'total' => $total,
        'vat' => 0,
    ]);
}

it('shows the weighted average price and computes total HPP for a BOM', function () {
    // 100 GR bought for 500,000 => WA price 5,000/GR. Component qty is 100 GR => line cost 500,000.
    seedWaPurchase('2026-07-15', 501, 100, 500_000);
    // productDetailID 999 has no purchase history at all -> should fall back to lastHPP (2000) * qty (10) = 20,000.

    $page = Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents');

    $page->assertSee('Harga WA')
        ->assertSee('pakai ESB');

    $total = $page->instance()->bomWeightedAverageTotal($this->projectBom->id);
    expect($total['total'])->toBe(520000.0)
        ->and($total['hasFallback'])->toBeTrue();
});

it('recomputes the weighted average total when the date filter changes', function () {
    seedWaPurchase('2026-05-01', 501, 100, 300_000); // outside the filter window
    seedWaPurchase('2026-07-15', 501, 100, 500_000); // inside the filter window

    $page = Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents');

    // No filter: WA = (300,000 + 500,000) / (100 + 100) = 4,000/GR -> 100 GR * 4,000 = 400,000 (+ fallback 20,000)
    expect($page->instance()->bomWeightedAverageTotal($this->projectBom->id)['total'])->toBe(420000.0);

    $page->set('waDateFrom', '2026-07-01')
        ->set('waDateTo', '2026-07-31')
        ->call('applyWaDateFilter');

    // Filtered to July only: WA = 500,000 / 100 = 5,000/GR -> 500,000 (+ fallback 20,000)
    expect($page->instance()->bomWeightedAverageTotal($this->projectBom->id)['total'])->toBe(520000.0);
});

it('shows an estimated HPP badge on the collapsed auto-detected WIP recipe card', function () {
    // A separate project/product/BOM (distinct esb_bom_id) is used here rather than the
    // shared beforeEach fixtures, because Http::fake() stubs registered in beforeEach()
    // are checked first and would otherwise shadow this test's own /product/bom/42 stub.
    $project = RndProject::query()->create([
        'name' => 'WIP Badge Project', 'start_date' => '2026-07-01', 'end_date' => '2026-08-31', 'created_by' => auth()->id(),
    ]);
    $product = RndProjectProduct::query()->create([
        'rnd_project_id' => $project->id, 'name' => 'WIP Badge Product', 'product_code' => 'PRD-WIP', 'status' => 'development', 'created_by' => auth()->id(),
    ]);
    $mainBom = RndProjectBom::query()->create([
        'rnd_project_id' => $project->id, 'esb_bom_id' => 77, 'bom_code' => 'BOM-CRS', 'bom_name' => 'Croissant Assembly', 'created_by' => auth()->id(),
    ]);
    $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);

    $mainDetail = [
        'bomID' => 77, 'bomName' => 'Croissant Assembly', 'bomCode' => 'BOM-CRS',
        'productDetailID' => 100, 'productName' => 'Croissant', 'uomName' => 'PCS',
        'bomDetails' => [[
            'ID' => 1, 'productDetailID' => 200, 'productCode' => 'BW0200', 'productName' => 'Adonan Bitterballen',
            'uomName' => 'Resep', 'qty' => 2, 'lastHPP' => 0, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => '',
        ]],
    ];
    $wipDetail = [
        'bomID' => 99, 'bomCode' => 'BOM-ATL', 'bomName' => 'Resep Adonan Bitterballen',
        'productID' => 999, 'productDetailID' => 201, 'productCode' => 'BW0200', 'productName' => 'Adonan Bitterballen', 'uomName' => 'Resep',
        'bomDetails' => [[
            'ID' => 12, 'productDetailID' => 300, 'productCode' => 'BBM0300', 'productName' => 'Tepung Premium',
            'uomName' => 'GR', 'qty' => 300, 'lastHPP' => 10, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => '',
        ]],
    ];

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'access-token']]),
        'https://core-esb.test/product/bom/77' => Http::response(['status' => 'ok', 'result' => $mainDetail]),
        'https://core-esb.test/product/bom/99' => Http::response(['status' => 'ok', 'result' => $wipDetail]),
        'https://core-esb.test/product/bom*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 100, 'count' => 1,
            'data' => [['bomID' => 99, 'bomCode' => 'BOM-ATL', 'bomName' => 'Resep Adonan Bitterballen', 'productName' => 'Adonan Bitterballen', 'uomName' => 'Resep']],
            'prev' => '', 'next' => '',
        ]]),
    ]);

    // 300 GR bought for Rp 6,000 => WA price Rp 20/GR (deliberately different from lastHPP of 10, to prove
    // the badge is driven by the Weighted Average query, not just falling back to ESB's lastHPP).
    seedWaPurchase('2026-07-15', 300, 300, 6_000);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])->call('loadAllBomComponents')
        ->call('refreshWipComponentRecipes')
        ->assertSee('AUTO · BARANG WIP')
        ->assertSee('Est. HPP: Rp 6.000');
});
