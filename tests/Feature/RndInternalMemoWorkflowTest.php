<?php

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\RefreshInternalMemoMenuAction;
use App\Actions\Rnd\InternalMemo\SynchronizeInternalMemoAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMenuForecastAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMenuShelfLifeAction;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ListRndInternalMemos;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\Brand;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenuCatalog;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Puts a Menu into the global BLSS catalog snapshot (technical branch BLS, see beforeEach) and
 * returns its Menu ID — the only thing the picker sends to addMenu.
 */
function catalogMenuId(int $menuId, int $bomId, string $name = 'Croissant Butter'): int
{
    if (RndInternalMemoMenuCatalog::query()->where('company_code', 'BLSS')->where('branch_code', 'BLS')->where('menu_id', $menuId)->exists()) {
        return $menuId;
    }

    createMemoCatalogMenu([
        'menu_id' => $menuId, 'menu_code' => 'MENU-'.$menuId, 'menu_name' => $name,
        'category_detail' => 'Pastry', 'bom_id' => $bomId, 'bom_name' => $bomId > 0 ? 'BOM-'.$bomId : null,
        'flag_active' => true, 'raw_snapshot' => ['menuID' => $menuId, 'menuName' => $name],
    ]);

    return $menuId;
}

/** @param array<string, mixed> $attributes */
function createMemoCatalogMenu(array $attributes = []): RndInternalMemoMenuCatalog
{
    return RndInternalMemoMenuCatalog::factory()->create([
        'company_code' => 'BLSS',
        'branch_code' => 'BLS',
        ...$attributes,
    ]);
}

beforeEach(function () {
    Queue::fake();
    config()->set('esb.base_url', 'https://esb.test');
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    markInternalMemoCatalogSynced('BLS');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->brand = Brand::factory()->create(['name' => 'Bloomery Bakery']);
    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($this->operator);
});

it('creates a Draft memo with the chosen Brand and Company Code BLSS set server-side', function () {
    // docs/rnd-internal-memo-brand-prd.md §11.1: the user picks one Brand; Company Code is never
    // entered and is always BLSS; no Memo–Branch row is written.
    $page = Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', '001/RND/IX/2026')
        ->set('memoTitle', 'Rilis Menu September')
        ->set('periodMonth', '2026-09')
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasNoErrors();

    $memo = RndInternalMemo::sole();
    expect($memo->company_code)->toBe('BLSS')
        ->and($memo->brand_id)->toBe($this->brand->id)
        ->and($memo->brand_name_snapshot)->toBe('Bloomery Bakery')
        ->and($memo->status)->toBe(RndInternalMemoStatus::Draft)
        ->and($memo->revision)->toBe(1)
        ->and($memo->created_by)->toBe($this->operator->id)
        ->and($memo->branches()->count())->toBe(0);

    $page->assertRedirect();
});

it('requires Nomor Memo to be filled, either by Generate or manually', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set('memoTitle', 'Rilis Menu September')
        ->set('periodMonth', '2026-09')
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasErrors(['memoNumber' => 'required']);
});

it('fills Nomor Memo with the 001/RND/<roman month>/<year> convention and locks it read-only when Generate is pressed', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set('memoTitle', 'Rilis Menu September')
        ->set('periodMonth', '2026-09')
        ->call('generateMemoNumberField')
        ->assertSet('memoNumber', '001/RND/IX/2026')
        ->assertSet('memoNumberGenerated', true)
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasNoErrors();

    expect(RndInternalMemo::sole()->memo_number)->toBe('001/RND/IX/2026');
});

it('refuses to generate Nomor Memo before Bulan Memo is chosen', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set('memoTitle', 'Rilis Menu September')
        ->call('generateMemoNumberField')
        ->assertHasErrors(['periodMonth' => 'required'])
        ->assertSet('memoNumberGenerated', false);
});

it('lets the user switch back to typing Nomor Memo manually after generating it', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set('periodMonth', '2026-09')
        ->call('generateMemoNumberField')
        ->assertSet('memoNumberGenerated', true)
        ->call('useManualMemoNumber')
        ->assertSet('memoNumberGenerated', false)
        ->assertSet('memoNumber', '')
        ->set('memoNumber', 'MEMO-CUSTOM-01')
        ->set('memoTitle', 'Rilis Menu September')
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasNoErrors();

    expect(RndInternalMemo::sole()->memo_number)->toBe('MEMO-CUSTOM-01');
});

it('keeps the generated Nomor Memo sequence resetting every year', function () {
    RndInternalMemo::factory()->create(['period_month' => '2026-03-01', 'memo_number' => '001/RND/III/2026']);
    RndInternalMemo::factory()->create(['period_month' => '2025-12-01', 'memo_number' => '004/RND/XII/2025']);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoTitle', 'Rilis Menu Oktober')
        ->set('periodMonth', '2026-10')
        ->call('generateMemoNumberField')
        ->assertSet('memoNumber', '002/RND/X/2026') // only the one 2026 memo above counts; the 2025 one does not
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasNoErrors();
});

it('rejects a second memo for the same Brand and period and a duplicate memo number', function () {
    RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01', 'memo_number' => 'EXISTING-001']);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'NEW-001')
        ->set('memoTitle', 'Rilis Menu September Ganda')
        ->set('periodMonth', '2026-09')
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasErrors(['periodMonth']);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'EXISTING-001')
        ->set('memoTitle', 'Judul Lain')
        ->set('periodMonth', '2026-10')
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasErrors(['memoNumber']);
});

it('allows creating a new memo for a period whose previous memo was deleted', function () {
    // Regression: the active-period unique index applied to every row
    // at the database level, including soft-deleted ones (MySQL has no partial unique index), so
    // deleting a Memo and recreating one for the same period raised a raw
    // UniqueConstraintViolationException even though the app-level duplicate-period check
    // correctly ignores soft-deleted records.
    $old = RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01', 'memo_number' => 'OLD-001']);
    app(DeleteInternalMemoAction::class)->execute($old);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'NEW-001')
        ->set('memoTitle', 'Rilis Menu September Baru')
        ->set('periodMonth', '2026-09')
        ->set('brandId', $this->brand->id)
        ->call('createMemo')
        ->assertHasNoErrors();

    expect(RndInternalMemo::query()->where('memo_number', 'NEW-001')->exists())->toBeTrue();
});

it('adds a Menu with bomID > 0 through the picker, stores a snapshot, and resolves its BOM immediately', function () {
    // docs/rnd-internal-memo-simplification-prd.md §7.2: BOM/Assembly resolution now runs
    // synchronously right when the Menu is added, replacing the old separate Draft -> Syncing step.
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/42' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', ['bomID' => 42])]),
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('addMenu', catalogMenuId(501, 42))
        ->assertHasNoErrors();

    $menu = $memo->menus()->sole();
    expect($menu->esb_menu_id)->toBe(501)
        ->and($menu->esb_bom_id)->toBe(42)
        ->and($menu->menu_snapshot)->toBe(['menuID' => 501, 'menuName' => 'Croissant Butter'])
        ->and($menu->sync_status->value)->toBe('synced')
        ->and($menu->synced_at)->not->toBeNull()
        ->and($menu->materials()->count())->toBe(1);
});

it('keeps a Menu added even when its BOM fails to resolve, marking it Failed for retry', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    // No Http::fake() for the BOM endpoint: the stray request is blocked, simulating an ESB
    // failure at add-time (§7.2, "Jika sebagian API gagal, Menu tetap tercatat").
    Http::fake(['https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']])]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('addMenu', catalogMenuId(501, 42))
        ->assertHasNoErrors();

    $menu = $memo->menus()->sole();
    expect($menu->sync_status->value)->toBe('failed')
        ->and($menu->sync_error)->not->toBeNull();
});

it('refuses a Menu with bomID = 0 and does not persist a row', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    expect(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(502, 0)))
        ->toThrow(ValidationException::class);

    expect($memo->menus()->count())->toBe(0);
});

it('refuses adding the same Menu twice to one memo', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));

    expect(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42)))
        ->toThrow(ValidationException::class);

    expect($memo->menus()->count())->toBe(1);
});

it('allows adding a Menu regardless of the memo legacy workflow status', function () {
    // docs/rnd-internal-memo-simplification-prd.md §7.5: "Menu dapat ditambah dan dihapus kapan
    // saja" — the old Draft-only gate belonged to the finalize/lock workflow this PRD removes.
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready]);
    Http::fake(['https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']])]);

    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));

    expect($memo->menus()->count())->toBe(1)
        ->and($menu->esb_menu_id)->toBe(501);
});

it('no longer looks up the retired Menu Shelf Life master when adding a Menu', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    RndProductEsbShelfLife::factory()->legacyMenu()->create([
        'esb_menu_id' => 501,
        'shelf_life_value' => 3,
        'shelf_life_unit' => 'hari',
        'storage_condition' => 'Chiller',
        'is_active' => true,
    ]);

    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));

    expect($menu->shelf_life_value)->toBeNull()
        ->and($menu->shelf_life_unit)->toBeNull()
        ->and($menu->storage_condition)->toBeNull()
        ->and($menu->hasShelfLife())->toBeFalse();
});

it('lets a Draft memo remove an added Menu', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('removeMenu', $menu->id)
        ->assertHasNoErrors();

    expect($memo->menus()->count())->toBe(0);
});

it('searches the local Menu snapshot and shows a Menu without BOM as disabled', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    createMemoCatalogMenu([
        'menu_id' => 501, 'menu_code' => 'MENU-501', 'menu_name' => 'Croissant Butter',
        'bom_id' => 42, 'category_detail' => 'BEVERAGES - COFFEE',
    ]);
    createMemoCatalogMenu([
        'menu_id' => 502, 'menu_code' => 'MENU-502', 'menu_name' => 'Menu Belum BOM', 'bom_id' => 0,
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->call('initializeMenuPicker') // simulates the browser firing wire:init after the modal's first render
        ->assertSeeHtml('min-h-0 flex-1 overflow-auto overscroll-contain')
        ->assertSee('MENU-501')
        ->assertSee('Croissant Butter')
        ->assertSee('BEVERAGES')
        ->assertSee('COFFEE')
        ->assertSee('Menu Belum BOM')
        ->assertSee('Belum Memiliki BOM');

    Http::assertNothingSent();
});

it('splits categoryDetail into Category and Category Detail, falling back to Category only without a delimiter', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $page = Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id]);

    expect($page->instance()->splitMenuCategory('BEVERAGES - COFFEE'))->toBe(['category' => 'BEVERAGES', 'detail' => 'COFFEE'])
        ->and($page->instance()->splitMenuCategory('PASTRY'))->toBe(['category' => 'PASTRY', 'detail' => null])
        ->and($page->instance()->splitMenuCategory(null))->toBe(['category' => null, 'detail' => null]);
});

it('opens and searches the Menu picker entirely from the local snapshot', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    createMemoCatalogMenu([
        'menu_id' => 1, 'menu_name' => 'Croissant Butter', 'menu_code' => 'A1', 'bom_id' => 42,
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->call('initializeMenuPicker')
        ->set('menuSearchName', 'Croissant')
        ->assertSee('Croissant Butter');

    Http::assertNothingSent();
});

it('filters the local Menu snapshot automatically as the user types', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    createMemoCatalogMenu(['menu_id' => 1, 'menu_name' => 'Croissant Butter', 'menu_code' => 'A1', 'bom_id' => 42]);
    createMemoCatalogMenu(['menu_id' => 2, 'menu_name' => 'Matcha Cake', 'menu_code' => 'B2', 'bom_id' => 43]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->set('menuSearchName', 'Croissant')
        ->assertSet('menuPickerPage', 1)
        ->assertSee('Croissant Butter')
        ->assertDontSee('Matcha Cake');

    Http::assertNothingSent();
});

it('paginates the Menu picker with goToMenuPage/previousMenuPage/nextMenuPage', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    foreach (range(1, 25) as $index) {
        createMemoCatalogMenu([
            'menu_id' => $index,
            'menu_name' => 'Menu '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'menu_code' => 'M'.$index,
            'bom_id' => 100 + $index,
        ]);
    }

    $page = Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->call('initializeMenuPicker') // simulates the browser firing wire:init after the modal's first render
        ->assertSet('menuPickerPage', 1)
        ->call('nextMenuPage')
        ->assertSet('menuPickerPage', 2)
        ->call('goToMenuPage', 3)
        ->assertSet('menuPickerPage', 3)
        ->call('previousMenuPage')
        ->assertSet('menuPickerPage', 2);

    // 25 total / 10 per page = 3 pages; goToMenuPage clamps beyond the last page.
    $page->call('goToMenuPage', 99)->assertSet('menuPickerPage', 3);
});

it('does not let a memo be viewed or managed without permission', function () {
    $memo = RndInternalMemo::factory()->create();
    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertForbidden();
});

it('hides the Pilih Menu and Buat Memo actions from a user who can only view', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $this->actingAs($viewer);
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    Livewire::test(ListRndInternalMemos::class)->assertDontSee('Buat Memo');
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertDontSee('Tambah Menu');
});

/**
 * docs/rnd-internal-memo-simplification-prd.md Phase 4/5: the simplified workspace
 * (ViewRndInternalMemo) no longer has a Sync BOM / Forecast / Shelf Life UI — BOM resolution now
 * runs synchronously from addMenu/refreshMenu (see RefreshInternalMemoMenuActionTest.php and
 * InternalMemoBomResolverTest.php for that coverage). SynchronizeInternalMemoAction and the
 * Forecast/Shelf Life Actions still exist for the transition period, so their own business logic
 * (not the removed UI orchestration) is exercised directly here instead.
 */
it('runs SynchronizeInternalMemoAction end-to-end and leaves the memo NeedsAttention when a blocker is found', function () {
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));

    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/42' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 42,
            'bomDetails' => [
                ['productDetailID' => 15003, 'productCode' => 'BW9999', 'productName' => 'WIP Tanpa BOM', 'categoryName' => 'Barang WIP', 'qty' => 1.0, 'uomName' => 'PCS'],
            ],
        ])]),
        'https://esb.test/core/product/bom?*' => Http::response(['status' => 'ok', 'result' => ['data' => []]]),
    ]);

    app(SynchronizeInternalMemoAction::class)->execute($memo, $this->operator);

    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::NeedsAttention);
    $menu = $memo->menus()->sole();
    expect($menu->sync_status->value)->toBe('synced')
        ->and($menu->sync_error)->not->toBeNull()
        ->and($menu->materials()->count())->toBe(1);
});

it('updates Forecast Quantity and Shelf Life and recalculates net_quantity', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));
    $menu->materials()->create(['product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 42, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    app(UpdateInternalMemoMenuForecastAction::class)->execute($menu, 4.0);
    app(UpdateInternalMemoMenuShelfLifeAction::class)->execute($menu, [
        'shelf_life_value' => 3.0, 'shelf_life_unit' => 'hari', 'storage_condition' => 'Chiller', 'shelf_life_notes' => null,
    ]);

    $menu->refresh();
    expect((float) $menu->forecast_quantity)->toBe(4.0)
        ->and((float) $menu->shelf_life_value)->toBe(3.0)
        ->and($menu->shelf_life_unit)->toBe('hari')
        ->and($menu->storage_condition)->toBe('Chiller')
        ->and((float) $menu->materials()->sole()->net_quantity)->toBe(1000.0);
});

it('rejects a negative Forecast Quantity', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));

    expect(fn () => app(UpdateInternalMemoMenuForecastAction::class)->execute($menu, -5.0))
        ->toThrow(ValidationException::class);
});

it('still allows editing Forecast Quantity while the memo is NeedsAttention', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::NeedsAttention]);
    $menu = $memo->menus()->create([
        'esb_menu_id' => 501, 'menu_name' => 'Croissant Butter', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => [],
    ]);

    app(UpdateInternalMemoMenuForecastAction::class)->execute($menu, 10.0);

    expect((float) $menu->fresh()->forecast_quantity)->toBe(10.0);
});

it('runs SynchronizeInternalMemoAction end-to-end and reaches Ready once Forecast and Shelf Life are already filled', function () {
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));
    $menu->update(['forecast_quantity' => 10, 'shelf_life_value' => 3, 'shelf_life_unit' => 'hari']);

    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/42' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 42,
            'bomDetails' => [
                ['productDetailID' => 15002, 'productID' => 9002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR'],
            ],
        ])]),
    ]);

    app(SynchronizeInternalMemoAction::class)->execute($memo, $this->operator);

    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::Ready)
        ->and($memo->source_synced_at)->not->toBeNull();
    $menu->refresh();
    expect($menu->sync_status->value)->toBe('synced')
        ->and($menu->sync_error)->toBeNull()
        ->and((float) $menu->materials()->sole()->quantity_per_menu)->toBe(250.0)
        ->and((float) $menu->materials()->sole()->net_quantity)->toBe(2500.0);
});

it('leaves a freshly synced memo NeedsAttention when Forecast and Shelf Life are still missing', function () {
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    app(AddMenuToInternalMemoAction::class)->execute($memo, catalogMenuId(501, 42));

    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/42' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', [
            'bomID' => 42,
            'bomDetails' => [
                ['productDetailID' => 15002, 'productID' => 9002, 'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'categoryName' => 'Bahan Baku Makanan', 'qty' => 250.0, 'uomName' => 'GR'],
            ],
        ])]),
    ]);

    app(SynchronizeInternalMemoAction::class)->execute($memo, $this->operator);

    expect($memo->fresh()->status)->toBe(RndInternalMemoStatus::NeedsAttention);
});

it('renders the Memo Internal link in the custom helpdesk sidebar', function () {
    $this->operator->givePermissionTo('access backoffice');

    $response = $this->get(route('filament.helpdesk.resources.rnd-internal-memos.index'));

    $response->assertOk()
        ->assertSee('Research & Development')
        ->assertSee('Memo Internal')
        ->assertSee(route('filament.helpdesk.resources.rnd-internal-memos.index'), false);
});

it('no longer shows a per-Menu Struktur BOM section', function () {
    $memo = RndInternalMemo::factory()->create();
    $menu = $memo->menus()->create([
        'company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Belgian Chocolate Mille Crepe Cake 20cm',
        'esb_bom_id' => 42, 'bom_name' => 'BOM Mille Crepe', 'release_date' => now(), 'menu_snapshot' => [],
    ]);
    $menu->materials()->create([
        'product_code' => 'RM-BOX', 'product_name' => 'Box Cake', 'uom_name' => 'PCS', 'quantity_per_menu' => 1, 'net_quantity' => 0,
        'source_bom_id' => 42, 'source_path' => ['BOM Mille Crepe'], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertSee('Belgian Chocolate Mille Crepe Cake 20cm')
        ->assertSee('Box Cake')
        ->assertDontSee('Struktur BOM')
        ->assertDontSee('BOM Type Assembly');
});

it('shows the summary as products used by Store (Menu BOM only) and by Kitchen (traced WIP BOMs)', function () {
    $memo = RndInternalMemo::factory()->create();
    $menu = $memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Mille Crepe', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => []]);
    $row = fn (array $attributes) => $menu->materials()->create([
        'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 0, 'source_bom_id' => 42, 'source_path' => ['BOM Mille Crepe'],
        'depth' => 0, 'is_wip' => false, 'is_packaging' => false, ...$attributes,
    ]);
    $row(['esb_product_detail_id' => 1, 'product_code' => 'RM-BOX', 'product_name' => 'Box Cake']);
    $wip = $row(['esb_product_detail_id' => 2, 'product_code' => 'BW-CREPE', 'product_name' => 'Crepe Sheet', 'is_wip' => true]);
    $row(['esb_product_detail_id' => 3, 'product_code' => 'RM-FLOUR', 'product_name' => 'Tepung Crepe', 'depth' => 1, 'parent_material_id' => $wip->id]);
    $row(['esb_product_detail_id' => 4, 'product_code' => 'BW212', 'product_name' => 'PRX | CRP02', 'depth' => 1, 'parent_material_id' => $wip->id, 'is_wip' => true]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertDontSee('Ringkasan Item Akhir')
        ->assertSeeHtml('role="group" aria-label="Product Active Store"')
        ->assertSeeHtml('x-data="{ tab: \'wip\' }"')
        ->assertSeeInOrder(['Product Active Store', 'WIP Store', 'RAW Store', 'Crepe Sheet', 'Box Cake', 'Product Active Kitchen', 'WIP Kitchen', 'RAW Kitchen', 'PRX | CRP02', 'Tepung Crepe'])
        ->call('editMinimumOrder', 'kitchen|pd:3', null)
        ->set('minimumOrderValue', '50')
        ->call('saveMinimumOrder')
        ->assertHasNoErrors();

    expect((float) $menu->materials()->where('product_code', 'RM-FLOUR')->value('minimum_order'))->toBe(50.0);
});

it('lists the selected Menus as a table with Category and Category Detail', function () {
    $memo = RndInternalMemo::factory()->create();
    $memo->menus()->create([
        'company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_code' => 'MC-WH0001', 'menu_name' => 'Belgian Chocolate Mille Crepe Cake 20cm',
        'category_detail' => 'CAKE - WHOLE CAKE', 'esb_bom_id' => 42, 'bom_name' => 'Whole Cake Belgian Chocolate New', 'release_date' => now(), 'menu_snapshot' => [],
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertSeeInOrder(['Menu Active Store', '1 Menu', 'Menu', 'Category', 'Category Detail', 'Diperbarui'])
        ->assertSeeInOrder(['Belgian Chocolate Mille Crepe Cake 20cm', 'MC-WH0001', 'CAKE', 'WHOLE CAKE'])
        ->assertDontSee('Whole Cake Belgian Chocolate New')
        ->assertSeeHtml('aria-label="Refresh Belgian Chocolate Mille Crepe Cake 20cm"')
        ->assertSeeHtml('aria-label="Hapus Menu Belgian Chocolate Mille Crepe Cake 20cm"');
});

it('refreshes every Menu of the Memo with one button, continuing past a failing Menu', function () {
    $memo = RndInternalMemo::factory()->create();
    $ok = $memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Menu Berhasil', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => []]);
    $broken = $memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 502, 'menu_name' => 'Menu Gagal', 'esb_bom_id' => 43, 'release_date' => now(), 'menu_snapshot' => []]);
    $refreshed = [];
    $this->mock(RefreshInternalMemoMenuAction::class, function ($mock) use (&$refreshed, $broken): void {
        $mock->shouldReceive('execute')->twice()->andReturnUsing(function ($menu) use (&$refreshed, $broken) {
            $refreshed[] = $menu->id;
            if ($menu->id === $broken->id) {
                throw new RuntimeException('ESB timeout');
            }

            return $menu;
        });
    });

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertSeeHtml('aria-label="Refresh Semua Menu"')
        ->call('refreshAllMenus')
        ->assertNotified('1 dari 2 Menu berhasil disegarkan');

    expect($refreshed)->toBe([$ok->id, $broken->id]);

    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $this->actingAs($viewer);
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->assertDontSeeHtml('aria-label="Refresh Semua Menu"')
        ->call('refreshAllMenus')
        ->assertForbidden();
});
