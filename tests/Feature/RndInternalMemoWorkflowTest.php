<?php

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\SynchronizeInternalMemoAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMenuForecastAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMenuShelfLifeAction;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ListRndInternalMemos;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function fakeMenuRow(int $menuId, int $bomId, string $name = 'Croissant Butter'): array
{
    return [
        'menuID' => $menuId, 'menuCode' => 'MENU-'.$menuId, 'menuName' => $name,
        'categoryDetail' => 'Pastry', 'bomID' => $bomId, 'bomName' => $bomId > 0 ? 'BOM-'.$bomId : null,
        'flagActive' => true, 'hasBom' => $bomId > 0, 'raw' => ['menuID' => $menuId, 'menuName' => $name],
    ];
}

beforeEach(function () {
    config()->set('esb.base_url', 'https://esb.test');
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($this->operator);
});

it('creates a Draft memo with company_code fixed to BLSS without the user choosing it', function () {
    // docs/rnd-internal-memo-simplification-prd.md §7.1: the simplified form only collects Nama
    // Memo, Bulan Memo, Nomor Memo (optional), and Catatan.
    $page = Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', '001/RND/IX/2026')
        ->set('memoTitle', 'Rilis Menu September')
        ->set('periodMonth', '2026-09')
        ->call('createMemo')
        ->assertHasNoErrors();

    $memo = RndInternalMemo::sole();
    expect($memo->company_code)->toBe('BLSS')
        ->and($memo->status)->toBe(RndInternalMemoStatus::Draft)
        ->and($memo->revision)->toBe(1)
        ->and($memo->created_by)->toBe($this->operator->id);

    $page->assertRedirect();
});

it('requires Nomor Memo to be filled, either by Generate or manually', function () {
    Livewire::test(ListRndInternalMemos::class)
        ->set('memoTitle', 'Rilis Menu September')
        ->set('periodMonth', '2026-09')
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
        ->call('createMemo')
        ->assertHasNoErrors();
});

it('rejects a second memo for the same period and a duplicate memo number', function () {
    RndInternalMemo::factory()->create(['period_month' => '2026-09-01', 'memo_number' => 'EXISTING-001']);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'NEW-001')
        ->set('memoTitle', 'Rilis Menu September Ganda')
        ->set('periodMonth', '2026-09')
        ->call('createMemo')
        ->assertHasErrors(['periodMonth']);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'EXISTING-001')
        ->set('memoTitle', 'Judul Lain')
        ->set('periodMonth', '2026-10')
        ->call('createMemo')
        ->assertHasErrors(['memoNumber']);
});

it('allows creating a new memo for a period whose previous memo was deleted', function () {
    // Regression: the (company_code, period_month, revision) unique index applied to every row
    // at the database level, including soft-deleted ones (MySQL has no partial unique index), so
    // deleting a Memo and recreating one for the same period raised a raw
    // UniqueConstraintViolationException even though the app-level duplicate-period check
    // correctly ignores soft-deleted records.
    $old = RndInternalMemo::factory()->create(['period_month' => '2026-09-01', 'memo_number' => 'OLD-001']);
    app(DeleteInternalMemoAction::class)->execute($old);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'NEW-001')
        ->set('memoTitle', 'Rilis Menu September Baru')
        ->set('periodMonth', '2026-09')
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
        ->call('addMenu', fakeMenuRow(501, 42))
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
        ->call('addMenu', fakeMenuRow(501, 42))
        ->assertHasNoErrors();

    $menu = $memo->menus()->sole();
    expect($menu->sync_status->value)->toBe('failed')
        ->and($menu->sync_error)->not->toBeNull();
});

it('refuses a Menu with bomID = 0 and does not persist a row', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    expect(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(502, 0)))
        ->toThrow(ValidationException::class);

    expect($memo->menus()->count())->toBe(0);
});

it('refuses adding the same Menu twice to one memo', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

    expect(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42)))
        ->toThrow(ValidationException::class);

    expect($memo->menus()->count())->toBe(1);
});

it('allows adding a Menu regardless of the memo legacy workflow status', function () {
    // docs/rnd-internal-memo-simplification-prd.md §7.5: "Menu dapat ditambah dan dihapus kapan
    // saja" — the old Draft-only gate belonged to the finalize/lock workflow this PRD removes.
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready]);
    Http::fake(['https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']])]);

    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

    expect($memo->menus()->count())->toBe(1)
        ->and($menu->esb_menu_id)->toBe(501);
});

it('auto-fills Shelf Life from the local master when adding a Menu that has one', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    RndProductEsbShelfLife::factory()->create([
        'esb_menu_id' => 501,
        'shelf_life_value' => 3,
        'shelf_life_unit' => 'hari',
        'storage_condition' => 'Chiller',
        'is_active' => true,
    ]);

    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

    expect((float) $menu->shelf_life_value)->toBe(3.0)
        ->and($menu->shelf_life_unit)->toBe('hari')
        ->and($menu->hasShelfLife())->toBeTrue();
});

it('lets a Draft memo remove an added Menu', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('removeMenu', $menu->id)
        ->assertHasNoErrors();

    expect($memo->menus()->count())->toBe(0);
});

it('searches the Menu picker against the live ESB fake and shows a Belum Memiliki BOM Menu as disabled', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'core-token']]),
        'https://esb.test/core/branch' => Http::response([
            'status' => 'ok',
            'result' => [['branchID' => 6, 'branchCode' => 'BLS', 'branchName' => 'Bloomery Pabelan']],
        ]),
        'https://esb.test/corev1/master/get-menu*' => Http::response([
            'status' => 'ok',
            'result' => ['data' => [
                ['menuID' => 501, 'menuCode' => 'MENU-501', 'menuName' => 'Croissant Butter', 'bomID' => 42, 'categoryDetail' => 'BEVERAGES - COFFEE'],
                ['menuID' => 502, 'menuName' => 'Menu Belum BOM', 'bomID' => 0],
            ], 'limit' => 10, 'count' => 2],
        ]),
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->assertSee('MENU-501')
        ->assertSee('Croissant Butter')
        ->assertSee('BEVERAGES')
        ->assertSee('COFFEE')
        ->assertSee('Menu Belum BOM')
        ->assertSee('Belum Memiliki BOM');
});

it('splits categoryDetail into Category and Category Detail, falling back to Category only without a delimiter', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $page = Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id]);

    expect($page->instance()->splitMenuCategory('BEVERAGES - COFFEE'))->toBe(['category' => 'BEVERAGES', 'detail' => 'COFFEE'])
        ->and($page->instance()->splitMenuCategory('PASTRY'))->toBe(['category' => 'PASTRY', 'detail' => null])
        ->and($page->instance()->splitMenuCategory(null))->toBe(['category' => null, 'detail' => null]);
});

it('re-fetches the Menu picker automatically as the user types a search, without a separate search button', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'core-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [['branchID' => 6, 'branchCode' => 'BLS']]]),
        'https://esb.test/corev1/master/get-menu*' => Http::response(['status' => 'ok', 'result' => ['data' => [
            ['menuID' => 1, 'menuName' => 'Croissant Butter', 'menuCode' => 'A1'],
        ], 'limit' => 20, 'count' => 1]]),
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->set('menuSearchName', 'Croissant')
        ->assertSet('menuPickerPage', 1)
        ->assertSee('Croissant Butter');

    // Name search is filtered locally (menuName is proven to be ignored server-side), so typing
    // triggers the full-catalog fetch rather than forwarding the term as a query parameter.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/corev1/master/get-menu'));
});

it('paginates the Menu picker with goToMenuPage/previousMenuPage/nextMenuPage', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'core-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [['branchID' => 6, 'branchCode' => 'BLS']]]),
        'https://esb.test/corev1/master/get-menu*' => Http::response(['status' => 'ok', 'result' => ['data' => [], 'limit' => 10, 'count' => 25]]),
    ]);

    $page = Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
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
    app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

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
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));
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
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

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
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));
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
    app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

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
