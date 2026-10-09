<?php

use App\Actions\Rnd\InternalMemo\AddExtraProductToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\RefreshInternalMemoMenuAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoExtraProduct;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use App\Services\EsbMasterProductService;
use App\Services\EsbService;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Products added by hand to a Memo's Product Active summary (WIP/RAW × Store/Kitchen).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'blss-user', 'password' => 'blss-secret']);
    Cache::put('esb_core.access_token.BLSS', 'core-token');

    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'update rnd internal memo', 'manage wip shelf life']);
    $this->actingAs($this->operator);
    $this->memo = RndInternalMemo::factory()->create();

    Http::fake(fn (Request $request) => match (true) {
        str_ends_with($request->url(), '/product/936') => Http::response(['status' => 'ok', 'result' => [
            'productCode' => 'BW212', 'productName' => 'PRX | CRP02', 'categoryName' => 'Barang WIP', 'flagActive' => true,
            'productDetails' => [
                ['productDetailID' => 937, 'uomID' => 5, 'uomName' => 'GR', 'isBase' => true, 'isPurchase' => false, 'flagActive' => true],
                ['productDetailID' => 7103, 'uomID' => 40, 'uomName' => 'PACK@3000GR', 'isBase' => false, 'isPurchase' => true, 'flagActive' => true],
                ['productDetailID' => 3172, 'uomID' => 9, 'uomName' => 'PACK', 'isBase' => false, 'isPurchase' => false, 'flagActive' => false],
            ],
        ]]),
        str_ends_with($request->url(), '/product/365') => Http::response(['status' => 'ok', 'result' => [
            'productCode' => 'BBMK062', 'productName' => 'Minyak', 'categoryName' => 'Bahan Baku Makanan', 'flagActive' => true,
            'productDetails' => [['productDetailID' => 365, 'uomID' => 3, 'uomName' => 'ML', 'isBase' => true, 'isPurchase' => true, 'flagActive' => true]],
        ]]),
        default => Http::response(['status' => 'error'], 404),
    });

    $this->add = fn (string $scope, string $kind, int $productId, int $productDetailId, ?User $actor = null) => app(AddExtraProductToInternalMemoAction::class)
        ->execute($this->memo, $scope, $kind, $productId, $productDetailId, $actor ?? $this->operator);
});

it('adds WIP and RAW products to Store and Kitchen with the unit and Purchase UOM read from ESB BLSS', function () {
    $wip = ($this->add)('store', 'wip', 936, 937);
    $raw = ($this->add)('kitchen', 'raw', 365, 365);

    expect($wip->only(['scope', 'kind', 'product_code', 'product_name', 'uom_name', 'purchase_uom_name', 'category_name', 'created_by']))
        ->toBe(['scope' => 'store', 'kind' => 'wip', 'product_code' => 'BW212', 'product_name' => 'PRX | CRP02', 'uom_name' => 'GR', 'purchase_uom_name' => 'PACK@3000GR', 'category_name' => 'Barang WIP', 'created_by' => $this->operator->id])
        ->and($raw->only(['scope', 'kind', 'uom_name', 'purchase_uom_name']))->toBe(['scope' => 'kitchen', 'kind' => 'raw', 'uom_name' => 'ML', 'purchase_uom_name' => 'ML']);

    $summary = app(InternalMemoConsolidationService::class)->consolidateForSummary($this->memo);
    expect($summary['store']['wip'][0])->toMatchArray(['source' => 'manual', 'extra_product_id' => $wip->id, 'product_name' => 'PRX | CRP02', 'is_wip' => true])
        ->and($summary['kitchen']['bahan'][0])->toMatchArray(['source' => 'manual', 'product_name' => 'Minyak', 'is_wip' => false])
        ->and($summary['store']['bahan'])->toBe([])
        ->and($summary['kitchen']['wip'])->toBe([]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/product/936') && $request->hasHeader('Authorization', 'Bearer core-token'));
});

it('refuses wrong categories, inactive units, duplicates, and items the BOM already places in the section', function () {
    expect(fn () => ($this->add)('store', 'raw', 936, 937))->toThrow(ValidationException::class, 'Barang WIP tidak dapat ditambahkan ke RAW')
        ->and(fn () => ($this->add)('store', 'wip', 365, 365))->toThrow(ValidationException::class, 'Hanya product kategori Barang WIP')
        ->and(fn () => ($this->add)('store', 'wip', 936, 3172))->toThrow(ValidationException::class, 'tidak aktif')
        ->and(fn () => ($this->add)('store', 'wip', 936, 999999))->toThrow(ValidationException::class, 'tidak aktif')
        ->and(fn () => ($this->add)('elsewhere', 'wip', 936, 937))->toThrow(ValidationException::class);

    ($this->add)('store', 'wip', 936, 937);
    expect(fn () => ($this->add)('store', 'wip', 936, 937))->toThrow(ValidationException::class, 'sudah ditambahkan');
    expect(($this->add)('kitchen', 'wip', 936, 937)->scope)->toBe('kitchen');

    $menu = $this->memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Menu', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => []]);
    $menu->materials()->create(['esb_product_id' => 365, 'esb_product_detail_id' => 365, 'product_code' => 'BBMK062', 'product_name' => 'Minyak', 'uom_name' => 'ML', 'quantity_per_menu' => 1, 'net_quantity' => 0, 'source_bom_id' => 42, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    expect(fn () => ($this->add)('store', 'raw', 365, 365))->toThrow(ValidationException::class, 'sudah ada dari BOM Menu');
    expect(($this->add)('kitchen', 'raw', 365, 365)->scope)->toBe('kitchen');
});

it('requires Memo update permission', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);

    expect(fn () => ($this->add)('store', 'raw', 365, 365, $viewer))->toThrow(AuthorizationException::class);

    $this->actingAs($viewer);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertDontSee('Tambah WIP Store')
        ->call('openExtraProductPicker', 'store', 'wip')->assertForbidden();
    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])->call('removeExtraProduct', 1)->assertForbidden();
});

it('opens the shared picker for the active section and adds the picked row through ESB verification', function () {
    $this->mock(EsbMasterProductService::class, function ($mock): void {
        $mock->shouldReceive('getProductTaxonomy')->andReturn(['categories' => [46 => 'Barang WIP', 3 => 'Bahan Baku Makanan'], 'subCategories' => []]);
        $mock->shouldReceive('filterActiveProductDetails')->andReturnUsing(fn (array $details) => $details);
    });
    $this->mock(EsbService::class, function ($mock): void {
        $mock->shouldReceive('getAllActiveProductUnits')->andReturn(['GR', 'PACK@3000GR']);
        $mock->shouldReceive('getActiveProductDetailsPage')->andReturn(['data' => [
            937 => ['productDetailID' => 937, 'productID' => 936, 'productCode' => 'BW212', 'productName' => 'PRX | CRP02', 'categoryName' => 'Barang WIP', 'subCategoryName' => 'Premix', 'unit' => 'GR', 'baseUnit' => 'GR', 'conversionFactor' => 1, 'sku' => '', 'basePrice' => 0, 'receiptTolerance' => 0],
        ], 'page' => 1, 'total' => 1, 'perPage' => 10, 'hasNext' => false]);
    });

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSeeInOrder(['Product Active Store', 'Export .xlsx', 'Tambah WIP Store'])
        ->call('openExtraProductPicker', 'store', 'wip')
        ->assertSet('inlineProductModalOpen', true)
        ->assertSet('extraProductTarget', ['scope' => 'store', 'kind' => 'wip'])
        ->assertSet('inlineProductCategoryId', '46')
        ->assertSee('Tambah Product · WIP Store')
        ->set('inlineProductCategoryId', '')
        ->call('loadInlineProducts')
        ->assertSee('PRX | CRP02')
        ->assertSee('Conversion Factor')
        ->call('selectExtraProduct', 937)
        ->assertSet('inlineProductModalOpen', false)
        ->assertNotified('Product ditambahkan')
        ->assertSee('Manual')
        ->assertSeeHtml('aria-label="Hapus PRX | CRP02"');

    expect($this->memo->extraProducts()->sole()->only(['scope', 'kind', 'esb_product_detail_id']))->toBe(['scope' => 'store', 'kind' => 'wip', 'esb_product_detail_id' => 937]);
});

it('keeps Minimum Order, Shelf Life, removal, and Menu refresh working for hand-added products', function () {
    $wip = ($this->add)('store', 'wip', 936, 937);
    ($this->add)('store', 'raw', 365, 365);

    $page = Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->call('editMinimumOrder', 'store|extra:'.$wip->id)
        ->assertSet('minimumOrderTarget.product_name', 'PRX | CRP02')
        ->assertSet('minimumOrderTarget.purchase_uom_name', 'PACK@3000GR')
        ->set('minimumOrderValue', '12')
        ->call('saveMinimumOrder')
        ->assertHasNoErrors()
        ->call('openMemoShelfLifeModal', 937)
        ->assertSet('shelfLifeModalOpen', true)
        ->set('shelfLifeValue', '6')
        ->set('shelfLifeUnit', 'month')
        ->call('saveMemoShelfLife')
        ->assertHasNoErrors()
        ->assertSee('6 Bulan');

    expect((float) $wip->fresh()->minimum_order)->toBe(12.0)
        ->and(RndProductEsbShelfLife::query()->sole()->esb_product_detail_id)->toBe(937);

    $menu = $this->memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Menu', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => []]);
    try {
        app(RefreshInternalMemoMenuAction::class)->execute($menu);
    } catch (RuntimeException) {
        // The BOM fetch is not faked here; only the extra products' survival matters.
    }
    expect($this->memo->extraProducts()->count())->toBe(2);

    $page->call('removeExtraProduct', $wip->id)->assertNotified('Product dihapus dari Memo');
    expect($this->memo->extraProducts()->pluck('kind')->all())->toBe(['raw']);
});

it('exports hand-added products with their source', function () {
    ($this->add)('kitchen', 'raw', 365, 365);

    $response = $this->get(route('helpdesk.rnd-internal-memos.product-active-export', ['memo' => $this->memo->id, 'scope' => 'kitchen']))->assertOk();
    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();

    expect($rows['RAW Kitchen'][7])->toBe([1, 'BBMK062', 'Minyak', 'ML', 'ML', '', '', 'Manual']);
});

it('cascades hand-added products away with their Memo and offers the add buttons even before any Menu exists', function () {
    RndInternalMemoExtraProduct::factory()->wip()->create(['rnd_internal_memo_id' => $this->memo->id]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $this->memo->id])
        ->assertSee('Product Active Store')
        ->assertSee('Product Active Kitchen')
        ->assertSee('Tambah WIP Kitchen');

    $this->memo->forceDelete();
    expect(RndInternalMemoExtraProduct::query()->count())->toBe(0);
});
