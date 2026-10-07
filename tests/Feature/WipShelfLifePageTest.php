<?php

use App\Actions\Rnd\Bom\SyncBomCatalogAction;
use App\Actions\Rnd\ShelfLife\CreateWipShelfLifeAction;
use App\Actions\Rnd\ShelfLife\SyncWipProductCatalogAction;
use App\Enums\RndWipShelfLifeSource;
use App\Exceptions\Rnd\WipShelfLifeAlreadyExistsException;
use App\Filament\Helpdesk\Pages\BomAdjustmentPage;
use App\Filament\Helpdesk\Pages\EditBomAdjustmentPage;
use App\Filament\Helpdesk\Pages\WipShelfLifePage;
use App\Jobs\Rnd\SyncWipProductCatalogJob;
use App\Models\RndBomCatalog;
use App\Models\RndProductEsbShelfLife;
use App\Models\RndWipProduct;
use App\Models\User;
use App\Services\Rnd\ShelfLife\WipShelfLifeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Cache::flush();
    config([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'global-user',
        'esb.core.password' => 'global-password',
        'esb.core.companies.BLSS' => ['username' => 'blss-user', 'password' => 'blss-secret'],
    ]);

    $this->viewer = User::factory()->create(['is_active' => true]);
    $this->viewer->givePermissionTo(['access backoffice', 'view wip shelf life']);
    $this->editor = User::factory()->create(['is_active' => true]);
    $this->editor->givePermissionTo(['access backoffice', 'view wip shelf life', 'manage wip shelf life']);

    $this->product = RndWipProduct::factory()->create([
        'product_detail_id' => 100, 'product_code' => 'BW00100', 'product_name' => 'Saus Keju', 'uom_name' => 'GRAM',
    ]);
});

it('lists only active Barang WIP products with Shelf Life value, storage, and status for viewers without mutation buttons', function () {
    RndProductEsbShelfLife::factory()->create([
        'esb_product_detail_id' => 100, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day',
        'storage_condition' => 'chiller', 'notes' => 'Simpan 2–5°C',
    ]);
    RndWipProduct::factory()->create(['product_name' => 'Adonan Dasar', 'product_detail_id' => 200]);
    RndWipProduct::factory()->inactive()->create(['product_name' => 'WIP Lama Nonaktif']);
    $this->actingAs($this->viewer);

    Livewire::test(WipShelfLifePage::class)
        ->assertSee('Saus Keju')
        ->assertSee('Product Detail #100')
        ->assertSee('3 Hari')
        ->assertSee('Chiller')
        ->assertSee('Simpan 2–5°C')
        ->assertSee('Lengkap')
        ->assertSee('Adonan Dasar')
        ->assertSee('Belum Diisi')
        ->assertDontSee('WIP Lama Nonaktif')
        ->assertDontSee('Isi Shelf Life')
        ->assertDontSee('Edit Shelf Life');
});

it('renders the page for permitted users and refuses users without Shelf Life access', function () {
    $this->actingAs($this->viewer)->get(WipShelfLifePage::getUrl(panel: 'helpdesk'))->assertSuccessful()->assertSee('Saus Keju');

    $bomOnly = User::factory()->create(['is_active' => true]);
    $bomOnly->givePermissionTo(['access backoffice', 'view bill of materials', 'edit bill of materials']);
    $this->actingAs($bomOnly)->get(WipShelfLifePage::getUrl(panel: 'helpdesk'))->assertForbidden();
});

it('lets a manager create, edit, deactivate, and reactivate a master locally without ESB requests', function () {
    Http::fake();
    $this->actingAs($this->editor);

    $component = Livewire::test(WipShelfLifePage::class)
        ->assertSee('Isi Shelf Life')
        ->call('openShelfLifeModal', $this->product->id)
        ->assertSet('shelfLifeModalOpen', true)
        ->assertSee('Product Detail #100')
        ->set('shelfLifeValue', '3')
        ->set('shelfLifeUnit', 'day')
        ->set('shelfLifeStorageCondition', 'chiller')
        ->set('shelfLifeNotes', 'Simpan 2–5°C')
        ->call('saveShelfLife')
        ->assertHasNoErrors()
        ->assertSet('shelfLifeModalOpen', false)
        ->assertSee('3 Hari')
        ->assertSee('Edit Shelf Life');

    $master = RndProductEsbShelfLife::query()->sole();
    expect($master->esb_product_detail_id)->toBe(100)
        ->and($master->product_name)->toBe('Saus Keju')
        ->and($master->product_code)->toBe('BW00100')
        ->and($master->created_by)->toBe($this->editor->id);

    $component
        ->call('openShelfLifeModal', $this->product->id)
        ->assertSet('shelfLifeValue', '3')
        ->set('shelfLifeValue', '2')
        ->set('shelfLifeUnit', 'week')
        ->call('saveShelfLife')
        ->assertHasNoErrors()
        ->assertSee('2 Minggu')
        ->call('toggleShelfLifeActive', $this->product->id)
        ->assertSee('Tidak Aktif');

    expect($master->fresh()->is_active)->toBeFalse()
        ->and($master->fresh()->shelf_life_unit)->toBe('week')
        ->and(RndProductEsbShelfLife::query()->count())->toBe(1);

    $component->call('toggleShelfLifeActive', $this->product->id);
    expect($master->fresh()->is_active)->toBeTrue();

    Http::assertNothingSent();
});

it('keeps the input and the modal open when validation fails', function () {
    $this->actingAs($this->editor);

    Livewire::test(WipShelfLifePage::class)
        ->call('openShelfLifeModal', $this->product->id)
        ->set('shelfLifeValue', '0')
        ->set('shelfLifeUnit', 'hari')
        ->set('shelfLifeNotes', 'catatan tetap ada')
        ->call('saveShelfLife')
        ->assertHasErrors(['shelfLifeValue', 'shelfLifeUnit'])
        ->assertSet('shelfLifeModalOpen', true)
        ->assertSet('shelfLifeNotes', 'catatan tetap ada')
        ->assertSee('Masa simpan harus lebih besar dari 0.');

    expect(RndProductEsbShelfLife::query()->count())->toBe(0);
});

it('rejects direct Livewire mutations from viewers and from BOM editors', function () {
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 100]);
    $this->actingAs($this->viewer);

    Livewire::test(WipShelfLifePage::class)->call('openShelfLifeModal', $this->product->id)->assertForbidden();
    Livewire::test(WipShelfLifePage::class)->call('saveShelfLife')->assertForbidden();
    Livewire::test(WipShelfLifePage::class)->call('toggleShelfLifeActive', $this->product->id)->assertForbidden();
    Livewire::test(WipShelfLifePage::class)->call('refreshCatalog')->assertForbidden();

    expect($master->fresh()->is_active)->toBeTrue();
});

it('refuses to open a modal for an inactive or unknown WIP product and keeps the target locked', function () {
    $inactive = RndWipProduct::factory()->inactive()->create();
    $this->actingAs($this->editor);

    expect(fn () => Livewire::test(WipShelfLifePage::class)->call('openShelfLifeModal', $inactive->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => Livewire::test(WipShelfLifePage::class)->call('openShelfLifeModal', 999999))->toThrow(ModelNotFoundException::class);

    expect(fn () => Livewire::test(WipShelfLifePage::class)->set('wipProductId', $this->product->id))->toThrow(Exception::class);
});

it('shows the existing value instead of overwriting when another user created the master meanwhile', function () {
    $this->actingAs($this->editor);

    $component = Livewire::test(WipShelfLifePage::class)
        ->call('openShelfLifeModal', $this->product->id)
        ->set('shelfLifeValue', '9');

    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 100, 'shelf_life_value' => 4, 'shelf_life_unit' => 'month']);

    $component->call('saveShelfLife')->assertSet('shelfLifeValue', '4');

    expect(RndProductEsbShelfLife::query()->sole()->shelf_life_value)->toBe('4.00');
});

it('filters by Shelf Life status and search in SQL with pagination', function () {
    $this->actingAs($this->viewer);
    RndWipProduct::query()->delete();
    $products = RndWipProduct::factory()->count(25)->sequence(fn ($sequence) => [
        'product_name' => sprintf('WIP %02d', $sequence->index), 'product_detail_id' => 5000 + $sequence->index,
    ])->create();
    $products->take(12)->each(fn (RndWipProduct $product) => RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => $product->product_detail_id]));
    $products->slice(12, 3)->each(fn (RndWipProduct $product) => RndProductEsbShelfLife::factory()->inactive()->create(['esb_product_detail_id' => $product->product_detail_id]));

    $page = Livewire::test(WipShelfLifePage::class)->set('perPage', 10);

    expect($page->set('shelfLifeFilter', 'complete')->instance()->productRows()->total())->toBe(12)
        ->and($page->instance()->productRows()->count())->toBe(10)
        ->and($page->set('shelfLifeFilter', 'inactive')->instance()->productRows()->total())->toBe(3)
        ->and($page->set('shelfLifeFilter', 'missing')->instance()->productRows()->total())->toBe(10)
        ->and($page->set('shelfLifeFilter', '')->instance()->productRows()->total())->toBe(25)
        ->and($page->set('search', 'WIP 07')->instance()->productRows()->total())->toBe(1);

    $page->call('resetFilters')->set('shelfLifeFilter', 'complete')->call('nextPage')->assertSet('page', 2);
    expect($page->instance()->productRows()->count())->toBe(2);
});

it('renders the list without a query per row', function () {
    $this->actingAs($this->viewer);
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(WipShelfLifePage::class);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 100]);
    $countQueries();
    $fewRows = $countQueries();

    RndWipProduct::factory()->count(15)->sequence(fn ($sequence) => ['product_detail_id' => 7000 + $sequence->index])->create()
        ->take(8)->each(fn (RndWipProduct $product) => RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => $product->product_detail_id]));

    expect($countQueries())->toBe($fewRows);
});

it('queues the WIP product refresh for managers', function () {
    Queue::fake();
    $this->actingAs($this->editor);

    Livewire::test(WipShelfLifePage::class)->call('refreshCatalog');

    Queue::assertPushed(SyncWipProductCatalogJob::class, fn (SyncWipProductCatalogJob $job): bool => $job->triggeredBy === $this->editor->id);
});

it('syncs Barang WIP products with their units from the product detail endpoint and lists one row per product', function () {
    Cache::put('esb_core.access_token.BLSS', 'core-token');
    $stale = RndWipProduct::factory()->create(['product_detail_id' => 999, 'product_name' => 'WIP Dihapus', 'last_synced_at' => now()->subDay()]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 999, 'shelf_life_value' => 6]);
    Http::fake(function (Request $request) {
        expect($request->method())->toBe('GET');

        if (preg_match('#/product/(\d+)$#', parse_url($request->url(), PHP_URL_PATH), $matches) === 1) {
            return Http::response(['status' => 'ok', 'result' => match ((int) $matches[1]) {
                4198 => ['productName' => 'ATL | Acar', 'productDetails' => [
                    ['productDetailID' => 4837, 'uomName' => 'GR', 'isBase' => true, 'flagActive' => true],
                    ['productDetailID' => 4865, 'uomName' => 'Porsi', 'isBase' => false, 'flagActive' => true],
                    ['productDetailID' => 4866, 'uomName' => 'Pack', 'isBase' => false, 'flagActive' => false],
                ]],
                4200 => ['productName' => 'Adonan Dasar', 'productDetails' => [
                    ['productDetailID' => 200, 'uomName' => 'GRAM', 'isBase' => true, 'flagActive' => true],
                ]],
            }]);
        }

        expect((int) $request['flagActive'])->toBe(1);

        return match ((int) $request['page']) {
            1 => Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => 150, 'next' => 'yes', 'data' => [
                ['productID' => 4198, 'productCode' => 'BW930', 'productName' => 'ATL | Acar', 'categoryID' => 46, 'categoryName' => 'Barang WIP', 'flagActive' => 1],
                ['productID' => 2, 'productCode' => 'RM001', 'productName' => 'Keju Mozarella', 'categoryName' => 'Bahan Baku', 'flagActive' => 1],
            ]]]),
            default => Http::response(['status' => 'ok', 'result' => ['page' => 2, 'limit' => 100, 'count' => 150, 'next' => '', 'data' => [
                ['productID' => 4200, 'productCode' => 'BW200', 'productName' => 'Adonan Dasar', 'categoryName' => 'barang wip', 'flagActive' => 1],
            ]]]),
        };
    });

    $result = app(SyncWipProductCatalogAction::class)->execute();

    expect($result)->toMatchArray(['status' => 'completed', 'scanned' => 2, 'products' => 2, 'synced' => 4, 'failed' => 0, 'deactivated' => 1])
        ->and(RndWipProduct::query()->active()->orderBy('product_detail_id')->pluck('product_detail_id')->all())->toBe([100, 200, 4837, 4865, 4866])
        ->and(RndWipProduct::query()->where('product_detail_id', 4837)->sole()->only(['product_code', 'product_name', 'uom_name', 'is_base', 'esb_product_id']))
        ->toBe(['product_code' => 'BW930', 'product_name' => 'ATL | Acar', 'uom_name' => 'GR', 'is_base' => true, 'esb_product_id' => 4198])
        ->and(RndWipProduct::query()->where('product_detail_id', 4865)->sole()->is_base)->toBeFalse()
        ->and(RndWipProduct::query()->where('product_detail_id', 4866)->sole()->is_base)->toBeFalse()
        ->and($stale->fresh()->is_active)->toBeFalse()
        ->and(RndProductEsbShelfLife::query()->where('esb_product_detail_id', 999)->sole()->shelf_life_value)->toBe('6.00')
        ->and(SyncWipProductCatalogAction::progress()['status'])->toBe('completed');

    // The page lists one row per Product: its base unit.
    $this->actingAs($this->viewer);
    Livewire::test(WipShelfLifePage::class)
        ->assertSee('ATL | Acar')
        ->assertSee('Product Detail #4837')
        ->assertDontSee('Product Detail #4865');
});

it('deactivates nothing when a product detail cannot be read', function () {
    Cache::put('esb_core.access_token.BLSS', 'core-token');
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/product/list')) {
            return Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => 1, 'next' => '', 'data' => [
                ['productID' => 4198, 'productCode' => 'BW930', 'productName' => 'ATL | Acar', 'categoryName' => 'Barang WIP'],
            ]]]);
        }

        return Http::response(['status' => 'error', 'message' => 'down'], 500);
    });

    expect(app(SyncWipProductCatalogAction::class)->execute())->toMatchArray(['status' => 'completed', 'failed' => 1, 'deactivated' => 0])
        ->and($this->product->fresh()->is_active)->toBeTrue();
});

it('deactivates nothing when the ESB product list fails', function () {
    Cache::put('esb_core.access_token.BLSS', 'core-token');
    Http::fake(['https://core-esb.test/product/list*' => Http::response(['status' => 'error', 'message' => 'down'], 500)]);

    expect(fn () => app(SyncWipProductCatalogAction::class)->execute())->toThrow(RuntimeException::class)
        ->and($this->product->fresh()->is_active)->toBeTrue()
        ->and(SyncWipProductCatalogAction::progress()['status'])->toBe('failed');
});

it('shares one master between all units of a WIP product', function () {
    $porsi = RndWipProduct::factory()->unitOf($this->product, 'PORSI')->create(['product_detail_id' => 101]);
    $resolver = app(WipShelfLifeResolver::class);

    expect($resolver->masters([101]))->toBeEmpty();

    $master = app(CreateWipShelfLifeAction::class)->execute(RndWipShelfLifeSource::ShelfLifeMenu, [
        'esb_product_detail_id' => $porsi->product_detail_id, 'product_code' => 'BW00100', 'product_name' => 'Saus Keju',
        'shelf_life_value' => 3, 'shelf_life_unit' => 'day', 'storage_condition' => 'chiller', 'notes' => null,
    ], $this->editor);

    expect($master->esb_product_detail_id)->toBe(100)
        ->and($resolver->masters([100, 101])->map->id->all())->toBe([100 => $master->id, 101 => $master->id])
        ->and($resolver->activeMasters([101])->first()?->id)->toBe($master->id)
        ->and(fn () => app(CreateWipShelfLifeAction::class)->execute(RndWipShelfLifeSource::ShelfLifeMenu, [
            'esb_product_detail_id' => 101, 'product_code' => 'BW00100', 'product_name' => 'Saus Keju',
            'shelf_life_value' => 5, 'shelf_life_unit' => 'day', 'storage_condition' => 'dry', 'notes' => null,
        ], $this->editor))->toThrow(WipShelfLifeAlreadyExistsException::class);
});

it('no longer shows Shelf Life on Recipe Adjustment', function () {
    $bomEditor = User::factory()->create(['is_active' => true]);
    $bomEditor->givePermissionTo(['access backoffice', 'view bill of materials', 'edit bill of materials']);
    RndBomCatalog::factory()->create(['bom_name' => 'Saus Keju Assembly', 'product_detail_id' => 100]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 100, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day']);
    $this->actingAs($bomEditor);

    Livewire::test(BomAdjustmentPage::class)
        ->assertSee('Saus Keju Assembly')
        ->assertDontSee('Isi Shelf Life')
        ->assertDontSee('Edit Shelf Life')
        ->assertDontSee('Semua Shelf Life')
        ->assertDontSee('3 Hari');
});

it('keeps Shelf Life out of the ESB BOM update payload and survives the catalog re-sync after it', function () {
    $bomEditor = User::factory()->create(['is_active' => true]);
    $bomEditor->givePermissionTo(['access backoffice', 'view bill of materials', 'edit bill of materials']);
    RndBomCatalog::factory()->create(['esb_bom_id' => 55, 'bom_name' => 'Saus Keju Assembly', 'product_detail_id' => 100]);
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 100, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day']);
    $detail = fn (int $qty, string $edited) => [
        'bomID' => 55, 'bomTypeID' => 1, 'bomTypeName' => 'Assembly', 'bomName' => 'Saus Keju Assembly', 'bomCode' => 'BOM-SK',
        'productDetailID' => 100, 'productName' => 'Saus Keju', 'productCode' => 'BW00100', 'uomName' => 'GRAM', 'editedDate' => $edited,
        'bomDetails' => [['ID' => 7, 'productID' => 2, 'productDetailID' => 200, 'productName' => 'Keju', 'productCode' => 'KJ', 'uomName' => 'GRAM', 'qty' => $qty, 'lastHpp' => 10, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => '']],
    ];
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::sequence()
            ->push(['status' => 'ok', 'result' => $detail(100, '2026-07-30T10:00:00+07:00')])
            ->push(['status' => 'ok', 'result' => $detail(100, '2026-07-30T10:00:00+07:00')])
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => $detail(150, '2026-07-30T11:00:00+07:00')]),
    ]);
    $this->actingAs($bomEditor);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->set('reason', 'Tambah keju')
        ->set('draft.bomDetails.0.qty', 150)
        ->call('openPreview')
        ->assertDontSee('Shelf Life')
        ->call('submit')
        ->assertRedirect(BomAdjustmentPage::getUrl());

    Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)
        && preg_match('/shelf|storage/i', $request->body()) === 1);

    expect($master->fresh()->only(['shelf_life_value', 'shelf_life_unit', 'is_active']))
        ->toBe(['shelf_life_value' => '3.00', 'shelf_life_unit' => 'day', 'is_active' => true]);
});

it('does not touch Shelf Life masters when the whole BOM catalog is re-synced', function () {
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 100, 'shelf_life_value' => 6, 'shelf_life_unit' => 'month']);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }
        if (str_contains($request->url(), '/product/bom/55')) {
            return Http::response(['status' => 'ok', 'result' => [
                'bomID' => 55, 'bomTypeID' => 1, 'bomTypeName' => 'Assembly', 'bomCode' => 'BOM-SK', 'bomName' => 'Saus Keju Assembly v2',
                'productDetailID' => 100, 'productCode' => 'BW00100', 'productName' => 'Saus Keju', 'uomName' => 'GRAM',
                'editedDate' => '2026-08-01T10:00:00+07:00', 'bomDetails' => [],
            ]]);
        }
        $rows = ((int) ($request['flagActive'] ?? 1)) === 1 ? [['bomID' => 55, 'bomTypeID' => 1, 'bomTypeName' => 'Assembly', 'bomCode' => 'BOM-SK', 'bomName' => 'Saus Keju Assembly v2']] : [];

        return Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => count($rows), 'data' => $rows, 'prev' => '', 'next' => '']]);
    });

    app(SyncBomCatalogAction::class)->execute();

    expect(RndBomCatalog::query()->where('esb_bom_id', 55)->sole()->bom_name)->toBe('Saus Keju Assembly v2')
        ->and($master->fresh()->shelf_life_value)->toBe('6.00')
        ->and($master->fresh()->shelf_life_unit)->toBe('month');
});
