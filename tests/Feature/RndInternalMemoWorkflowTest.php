<?php

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ListRndInternalMemos;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Jobs\Rnd\SynchronizeInternalMemoJob;
use App\Models\RndInternalMemo;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
    $page = Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', '001/RND/IX/2026')
        ->set('memoTitle', 'Rilis Menu September')
        ->set('periodMonth', '2026-09')
        ->set('memoDate', '2026-09-01')
        ->set('recipient', 'Tim Operasional')
        ->set('sender', 'Tim R&D')
        ->set('subject', 'Rilis Menu Bulanan')
        ->call('createMemo')
        ->assertHasNoErrors();

    $memo = RndInternalMemo::sole();
    expect($memo->company_code)->toBe('BLSS')
        ->and($memo->status)->toBe(RndInternalMemoStatus::Draft)
        ->and($memo->revision)->toBe(1)
        ->and($memo->created_by)->toBe($this->operator->id);

    $page->assertRedirect();
});

it('rejects a second memo for the same period and a duplicate memo number', function () {
    RndInternalMemo::factory()->create(['period_month' => '2026-09-01', 'memo_number' => 'EXISTING-001']);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'NEW-001')
        ->set('memoTitle', 'Rilis Menu September Ganda')
        ->set('periodMonth', '2026-09')
        ->set('memoDate', '2026-09-01')
        ->set('recipient', 'Tim Operasional')
        ->set('sender', 'Tim R&D')
        ->set('subject', 'Rilis Menu Bulanan')
        ->call('createMemo')
        ->assertHasErrors(['periodMonth']);

    Livewire::test(ListRndInternalMemos::class)
        ->set('memoNumber', 'EXISTING-001')
        ->set('memoTitle', 'Judul Lain')
        ->set('periodMonth', '2026-10')
        ->set('memoDate', '2026-10-01')
        ->set('recipient', 'Tim Operasional')
        ->set('sender', 'Tim R&D')
        ->set('subject', 'Rilis Menu Bulanan')
        ->call('createMemo')
        ->assertHasErrors(['memoNumber']);
});

it('adds a Menu with bomID > 0 through the picker and stores a snapshot', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('addMenu', fakeMenuRow(501, 42))
        ->assertHasNoErrors();

    $menu = $memo->menus()->sole();
    expect($menu->esb_menu_id)->toBe(501)
        ->and($menu->esb_bom_id)->toBe(42)
        ->and($menu->menu_snapshot)->toBe(['menuID' => 501, 'menuName' => 'Croissant Butter'])
        ->and($menu->sync_status->value)->toBe('pending');
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

it('refuses adding a Menu once the memo is no longer Draft', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Ready]);

    expect(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42)))
        ->toThrow(RuntimeException::class);
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
                ['menuID' => 501, 'menuName' => 'Croissant Butter', 'bomID' => 42],
                ['menuID' => 502, 'menuName' => 'Menu Belum BOM', 'bomID' => 0],
            ], 'limit' => 10, 'count' => 2],
        ]),
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openMenuPicker')
        ->assertSee('Croissant Butter')
        ->assertSee('Menu Belum BOM')
        ->assertSee('Belum Memiliki BOM');
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
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertDontSee('Pilih Menu');
});

it('warns instead of dispatching a sync when the memo has no Menu yet', function () {
    $this->operator->givePermissionTo('sync rnd internal memo');
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    Queue::fake();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('runSync');

    Queue::assertNothingPushed();
    expect($memo->fresh()->status)->toBe(RndInternalMemoStatus::Draft);
});

it('flips the memo to Syncing and dispatches the sync job immediately on click', function () {
    $this->operator->givePermissionTo('sync rnd internal memo');
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));
    Queue::fake();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('runSync');

    expect($memo->fresh()->status)->toBe(RndInternalMemoStatus::Syncing);
    Queue::assertPushed(SynchronizeInternalMemoJob::class, fn ($job) => $job->memoId === $memo->id && $job->actorId === $this->operator->id);
});

it('refuses to dispatch a sync for a user without the sync permission', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));
    Queue::fake();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('runSync')->assertForbidden();

    Queue::assertNothingPushed();
});

it('runs the sync job end-to-end and leaves the memo NeedsAttention when a blocker is found', function () {
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    $this->operator->givePermissionTo('sync rnd internal memo');
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

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('runSync');

    $memo->refresh();
    expect($memo->status)->toBe(RndInternalMemoStatus::NeedsAttention);
    $menu = $memo->menus()->sole();
    expect($menu->sync_status->value)->toBe('synced')
        ->and($menu->sync_error)->not->toBeNull()
        ->and($menu->materials()->count())->toBe(1);
});

it('updates Forecast Quantity and Shelf Life through the modal and recalculates net_quantity', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));
    $menu->materials()->create(['product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 42, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openForecastModal', $menu->id)
        ->set('forecastQuantity', '4')
        ->set('shelfLifeValue', '3')
        ->set('shelfLifeUnit', 'hari')
        ->set('storageCondition', 'Chiller')
        ->call('saveForecast')
        ->assertHasNoErrors();

    $menu->refresh();
    expect((float) $menu->forecast_quantity)->toBe(4.0)
        ->and((float) $menu->shelf_life_value)->toBe(3.0)
        ->and($menu->shelf_life_unit)->toBe('hari')
        ->and($menu->storage_condition)->toBe('Chiller')
        ->and((float) $menu->materials()->sole()->net_quantity)->toBe(1000.0);
});

it('rejects a negative Forecast Quantity from the modal', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openForecastModal', $menu->id)
        ->set('forecastQuantity', '-5')
        ->call('saveForecast')
        ->assertHasErrors(['forecastQuantity']);
});

it('refuses to open the Forecast modal for a user without update permission', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, fakeMenuRow(501, 42));
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $this->actingAs($viewer);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openForecastModal', $menu->id)
        ->assertForbidden();
});

it('still allows editing Forecast Quantity while the memo is NeedsAttention', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::NeedsAttention]);
    $menu = $memo->menus()->create([
        'esb_menu_id' => 501, 'menu_name' => 'Croissant Butter', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => [],
    ]);

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])
        ->call('openForecastModal', $menu->id)
        ->set('forecastQuantity', '10')
        ->call('saveForecast')
        ->assertHasNoErrors();

    expect((float) $menu->fresh()->forecast_quantity)->toBe(10.0);
});

it('runs the sync job end-to-end and reaches Ready once Forecast and Shelf Life are already filled', function () {
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    $this->operator->givePermissionTo('sync rnd internal memo');
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

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('runSync');

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
    $this->operator->givePermissionTo('sync rnd internal memo');
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

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('runSync');

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
