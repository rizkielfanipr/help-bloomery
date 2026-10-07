<?php

use App\Actions\Rnd\ShelfLife\UpdateWipShelfLifeAction;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\RndProductEsbShelfLife;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\User;
use App\Services\Rnd\Bom\ProjectWipRecipeDiscovery;
use App\Services\Rnd\ShelfLife\ProjectProductShelfLifeReadiness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    config(['cache.default' => 'array']);
    Cache::flush();

    $this->row = fn (int $productDetailId, string $code, string $name, string $category = 'Barang WIP'): array => [
        'ID' => $productDetailId, 'productID' => $productDetailId, 'productDetailID' => $productDetailId, 'productCode' => $code,
        'productName' => $name, 'categoryName' => $category, 'uomName' => 'GRAM', 'qty' => 10, 'lastHPP' => 1,
    ];

    $this->project = RndProject::query()->create(['name' => 'Seasonal Menu', 'start_date' => '2026-09-01', 'end_date' => '2026-12-31']);

    /**
     * A Product whose Main BOM uses Saus Keju (WIP with a found recipe that itself uses Adonan Dasar
     * and Saus Keju again), Topping (WIP whose recipe is not found), a WIP without Product Detail ID,
     * and a raw material.
     */
    $this->productWith = function (array $mainComponents, array $recipes = []): RndProjectProduct {
        $product = $this->project->products()->create(['name' => 'Pizza Keju '.uniqid(), 'status' => 'development']);
        $bom = $this->project->boms()->create([
            'esb_bom_id' => random_int(1000, 999999), 'bom_code' => 'BOM-PZ', 'bom_name' => 'Pizza Main',
            'detail_snapshot' => ['bomID' => 1, 'bomName' => 'Pizza Main', 'bomTypeID' => 1, 'bomDetails' => $mainComponents],
        ]);
        $product->boms()->attach($bom->id, ['usage_type' => 'main']);
        Cache::put(ProjectWipRecipeDiscovery::CACHE_PREFIX.$bom->esb_bom_id, $recipes, 600);

        return $product;
    };

    $this->standardProduct = fn (): RndProjectProduct => ($this->productWith)([
        ($this->row)(501, 'BW-SAUS', 'Saus Keju'),
        ($this->row)(502, 'BW-TOPPING', 'Topping'),
        ($this->row)(0, 'BW-MISTERI', 'WIP Misteri'),
        ($this->row)(900, 'KEJU', 'Keju Mozarella', 'Bahan Baku'),
    ], [[
        'bomID' => 77, 'bomCode' => 'BOM-SAUS', 'bomName' => 'Saus Keju Recipe', 'productDetailID' => 501,
        'productCode' => 'BW-SAUS', 'productName' => 'Saus Keju', 'uomName' => 'GRAM', 'sourceQty' => 10, 'sourceUnit' => 'GRAM',
        'bomDetails' => [($this->row)(503, 'BW-ADONAN', 'Adonan Dasar'), ($this->row)(501, 'BW-SAUS', 'Saus Keju'), ($this->row)(901, 'SUSU', 'Susu', 'Bahan Baku')],
    ]]);

    $this->userWith = function (array $permissions): User {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo(['access backoffice', ...$permissions]);

        return $user;
    };
    $this->filler = ($this->userWith)(['view rnd projects', 'edit rnd projects', 'view wip shelf life', 'manage wip shelf life']);

    $this->page = fn (RndProjectProduct $product) => Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $product->id])
        ->call('loadAllBomComponents');
});

it('reads masters for direct and nested WIP from the existing mapping, deduplicated, without ESB requests', function () {
    Http::fake();
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 501, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day', 'storage_condition' => 'chiller', 'notes' => 'Simpan 2–5°C']);
    RndProductEsbShelfLife::factory()->inactive()->create(['esb_product_detail_id' => 503]);
    $this->actingAs($this->filler);

    $component = ($this->page)(($this->standardProduct)())
        ->assertSee('Shelf Life WIP')
        ->assertSee('3 Hari')
        ->assertSee('Chiller')
        ->assertSee('Simpan 2–5°C')
        ->assertSee('Adonan Dasar')
        ->assertSee('Tidak Aktif')
        ->assertSee('Belum Diisi')
        ->assertSee('Identitas Tidak Lengkap')
        ->assertSee('BOM turunan WIP ini tidak ditemukan')
        ->assertDontSee('Keju Mozarella · Product Detail');

    $rows = collect($component->instance()->wipShelfLifeRows());

    expect($rows->pluck('product_detail_id')->all())->toBe([501, 502, null, 503])
        ->and($rows->firstWhere('product_detail_id', 501)['paths'])->toBe(['Pizza Main', 'Pizza Main → Saus Keju Recipe'])
        ->and($rows->firstWhere('product_detail_id', 503)['paths'])->toBe(['Pizza Main → Saus Keju Recipe']);

    Http::assertNothingSent();
});

it('shows a loading state before the mapping runs and a neutral empty state for a Product without WIP', function () {
    $this->actingAs($this->filler);
    $product = ($this->productWith)([($this->row)(900, 'KEJU', 'Keju Mozarella', 'Bahan Baku')]);

    Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $product->id])
        ->assertSee('Memetakan WIP...')
        ->call('loadAllBomComponents')
        ->assertSee('Produk ini belum memakai WIP');
});

it('lets an authorized user fill a missing master that the Shelf Life menu then shows, without a Project copy', function () {
    $this->actingAs($this->filler);
    $product = ($this->standardProduct)();

    ($this->page)($product)
        ->call('openWipShelfLifeModal', 502)
        ->assertSet('shelfLifeModalOpen', true)
        ->assertSee('Product Detail #502')
        ->set('shelfLifeValue', '5')
        ->set('shelfLifeUnit', 'day')
        ->set('shelfLifeStorageCondition', 'frozen')
        ->call('saveWipShelfLife')
        ->assertHasNoErrors()
        ->assertSet('shelfLifeModalOpen', false)
        ->assertSee('5 Hari');

    $master = RndProductEsbShelfLife::query()->sole();
    expect($master->esb_product_detail_id)->toBe(502)
        ->and($master->product_name)->toBe('Topping')
        ->and($master->created_by)->toBe($this->filler->id)
        ->and($product->fresh()->only(['shelf_life_value', 'shelf_life_unit', 'storage_condition', 'storage_notes']))
        ->toBe(['shelf_life_value' => null, 'shelf_life_unit' => null, 'storage_condition' => null, 'storage_notes' => null]);
});

it('requires both edit rnd projects and manage wip shelf life to fill a master', function (array $permissions) {
    $this->actingAs(($this->userWith)($permissions));

    ($this->page)(($this->standardProduct)())
        ->assertSee('Belum Diisi')
        ->assertDontSee('Isi Shelf Life')
        ->call('openWipShelfLifeModal', 502)
        ->assertForbidden();

    expect(RndProductEsbShelfLife::query()->count())->toBe(0);
})->with([
    'project editor only' => [['view rnd projects', 'edit rnd projects']],
    'Shelf Life manager only' => [['view rnd projects', 'view wip shelf life', 'manage wip shelf life']],
    'BOM editor only' => [['view rnd projects', 'view bill of materials', 'edit bill of materials']],
]);

it('shows Design users the values read-only without granting them BOM or Shelf Life edit permissions', function () {
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 501, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day']);
    $designer = User::factory()->create(['is_active' => true]);
    $designer->assignRole('DESIGN_STAFF');
    $this->actingAs($designer);

    ($this->page)(($this->standardProduct)())
        ->assertSee('3 Hari')
        ->assertDontSee('Isi Shelf Life')
        ->call('saveWipShelfLife')
        ->assertForbidden();

    expect($designer->can('view bill of materials'))->toBeFalse()
        ->and($designer->can('edit bill of materials'))->toBeFalse()
        ->and($designer->can('manage wip shelf life'))->toBeFalse();
});

it('never edits an existing master from the Project', function () {
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 501, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day']);
    RndProductEsbShelfLife::factory()->inactive()->create(['esb_product_detail_id' => 503]);
    $this->actingAs($this->filler);

    ($this->page)(($this->standardProduct)())
        ->call('openWipShelfLifeModal', 501)
        ->assertSet('shelfLifeModalOpen', false)
        ->call('openWipShelfLifeModal', 503)
        ->assertSet('shelfLifeModalOpen', false);

    expect($master->fresh()->shelf_life_value)->toBe('3.00')
        ->and(method_exists(ViewProjectProductPage::class, 'toggleShelfLifeActive'))->toBeFalse();
});

it('rejects a Product Detail ID that is not a WIP of this Product', function () {
    $this->actingAs($this->filler);

    ($this->page)(($this->standardProduct)())
        ->call('openWipShelfLifeModal', 900)
        ->assertStatus(422);

    ($this->page)(($this->standardProduct)())
        ->call('openWipShelfLifeModal', 424242)
        ->assertStatus(422);

    expect(fn () => ($this->page)(($this->standardProduct)())->set('shelfLifeTarget', ['product_detail_id' => 424242]))->toThrow(Exception::class);
    expect(RndProductEsbShelfLife::query()->count())->toBe(0);
});

it('keeps the master another user created meanwhile instead of overwriting it', function () {
    $this->actingAs($this->filler);
    $component = ($this->page)(($this->standardProduct)())
        ->call('openWipShelfLifeModal', 502)
        ->set('shelfLifeValue', '9');

    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 502, 'shelf_life_value' => 2, 'shelf_life_unit' => 'week']);

    $component->call('saveWipShelfLife')
        ->assertSet('shelfLifeModalOpen', false)
        ->assertSee('2 Minggu');

    expect(RndProductEsbShelfLife::query()->sole()->shelf_life_value)->toBe('2.00');
});

it('shows a Shelf Life menu change on the Project immediately', function () {
    $master = RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 501, 'shelf_life_value' => 3, 'shelf_life_unit' => 'day']);
    $this->actingAs($this->filler);
    $component = ($this->page)(($this->standardProduct)())->assertSee('3 Hari');

    app(UpdateWipShelfLifeAction::class)->execute($master, ['shelf_life_value' => 1, 'shelf_life_unit' => 'week', 'storage_condition' => 'dry', 'notes' => null], $this->filler);

    $component->call('$refresh')->assertSee('1 Minggu');
});

it('lists every WIP that blocks Ready/Released and clears once all are complete', function () {
    $readiness = app(ProjectProductShelfLifeReadiness::class);
    $product = ($this->standardProduct)();

    $blockers = $readiness->blockers($product);

    expect($blockers)->toContain('Shelf Life WIP BW-SAUS Saus Keju belum diisi.')
        ->toContain('Shelf Life WIP BW-ADONAN Adonan Dasar belum diisi.')
        ->toContain('WIP BW-MISTERI WIP Misteri belum mempunyai Product Detail ID.')
        ->toContain('BOM turunan WIP BW-TOPPING Topping tidak ditemukan, sehingga WIP di dalamnya belum dapat dipastikan.');

    $complete = ($this->productWith)([($this->row)(501, 'BW-SAUS', 'Saus Keju')], [[
        'bomID' => 77, 'bomCode' => 'BOM-SAUS', 'bomName' => 'Saus Keju Recipe', 'productDetailID' => 501, 'productCode' => 'BW-SAUS',
        'productName' => 'Saus Keju', 'uomName' => 'GRAM', 'sourceQty' => 10, 'sourceUnit' => 'GRAM', 'bomDetails' => [($this->row)(503, 'BW-ADONAN', 'Adonan Dasar')],
    ]]);
    RndProductEsbShelfLife::factory()->create(['esb_product_detail_id' => 501]);
    $inactive = RndProductEsbShelfLife::factory()->inactive()->create(['esb_product_detail_id' => 503]);

    expect($readiness->blockers($complete))->toBe(['Shelf Life WIP BW-ADONAN Adonan Dasar tidak aktif.']);

    $inactive->update(['is_active' => true]);
    expect($readiness->blockers($complete))->toBe([])
        ->and($readiness->blockers(($this->productWith)([($this->row)(900, 'KEJU', 'Keju', 'Bahan Baku')])))->toBe([])
        ->and($readiness->blockers($this->project->products()->create(['name' => 'Tanpa BOM', 'status' => 'draft'])))->toBe([]);
});

it('reports a mapping that cannot be loaded instead of treating the Product as complete', function () {
    $product = ($this->productWith)([($this->row)(501, 'BW-SAUS', 'Saus Keju')]);
    Cache::flush();
    Http::fake(['*' => Http::response(['message' => 'down'], 500)]);
    config(['esb.core.base_url' => 'https://core-esb.test', 'esb.core.username' => 'u', 'esb.core.password' => 'p']);

    expect(app(ProjectProductShelfLifeReadiness::class)->blockers($product)[0])->toContain('belum dapat diperiksa');
});

it('blocks only Ready/Released on the backend, never Draft, Development, or Trial', function (string $status, bool $blocked) {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
    $product = ($this->standardProduct)();

    $component = Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->call('editProduct', $product->id)
        ->set('releaseDate', '2026-12-01')
        ->set('productStatus', $status)
        ->call('saveProduct');

    if ($blocked) {
        $component->assertHasErrors(['wipShelfLife'])->assertSee('Shelf Life WIP BW-SAUS Saus Keju belum diisi.');
        expect($product->fresh()->status)->toBe('development');
    } else {
        $component->assertHasNoErrors(['wipShelfLife']);
        expect($product->fresh()->status)->toBe($status);
    }
})->with([
    'draft' => ['draft', false],
    'development' => ['development', false],
    'trial' => ['trial', false],
    'ready' => ['ready', true],
    'released' => ['released', true],
]);
