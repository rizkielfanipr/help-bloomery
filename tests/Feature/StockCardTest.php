<?php

use App\Enums\StockCardStatus;
use App\Filament\Casual\Pages\StockCardEntryPage;
use App\Filament\Casual\Pages\StockCardHistoryPage;
use App\Filament\Casual\Pages\StockCardPage;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ListStockCards;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ViewStockCard;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Models\User;
use App\Services\EsbService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->branch->esbCodes()->create(['esb_branch_code' => 'TST01', 'esb_comcode' => 'COM01']);

    $this->storeUser = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $this->storeUser->assignRole('STORE_STAFF');

    $this->employee = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'employee_code' => 'EMP-001',
        'name' => 'Staff Gudang',
        'position' => 'Warehouse Staff',
    ]);
});

// ── Casual panel ─────────────────────────────────────────────────────────────

it('shows stock card index page for store staff', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardPage::class)
        ->assertOk()
        ->assertSet('reportDate', now()->toDateString());
});

it('returns null status when no stock card exists', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $status = Livewire::test(StockCardPage::class)->instance()->getDailyStatus();

    expect($status)->toBeNull();
});

it('returns submitted_at when stock card is submitted', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    StockCard::factory()->submitted()->create([
        'branch_id' => $this->branch->id,
        'report_date' => now()->toDateString(),
        'submitted_by' => $this->storeUser->id,
    ]);

    $status = Livewire::test(StockCardPage::class)->instance()->getDailyStatus();

    expect($status)->not->toBeNull();
});

it('persists progress as a draft so a refresh does not lose it', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [[
            'product_code' => 'MAT-001',
            'product_name' => 'Ayam',
            'product_category' => 'Barang WIP',
            'system_unit' => 'KG',
            'actual_qty' => '',
            'notes' => '',
        ]])
        ->set('rows.0.actual_qty', '5')
        ->set('rows.0.notes', 'Catatan uji')
        ->set('employeeIds', [$this->employee->id]);

    // Simulate a page refresh: a brand new component instance mounts fresh.
    Livewire::test(StockCardEntryPage::class)
        ->assertSet('isSubmitted', false)
        ->assertSet('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'product_category' => 'Barang WIP', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => 'Catatan uji'],
        ])
        ->assertSet('employeeIds', [$this->employee->id]);
});

it('does not expose manual product add or remove actions', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $page = Livewire::test(StockCardEntryPage::class);

    expect(method_exists($page->instance(), 'addProduct'))->toBeFalse()
        ->and(method_exists($page->instance(), 'removeProduct'))->toBeFalse()
        ->and(method_exists($page->instance(), 'searchProducts'))->toBeFalse();
});

it('blocks requestConfirm when no product has been added', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('catalogLoaded', true)
        ->call('requestConfirm')
        ->assertNotified();
});

it('blocks save when a row has empty actual_qty', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '', 'notes' => ''],
        ])
        ->set('catalogLoaded', true)
        ->call('requestConfirm')
        ->assertNotified();
});

it('saves stock card with entries and marks as submitted', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $page = Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => ''],
            ['product_code' => 'MAT-002', 'product_name' => 'Beras', 'system_unit' => 'KG', 'actual_qty' => '9', 'notes' => 'Tercecer saat pindah'],
        ])
        ->set('employeeIds', [$this->employee->id])
        ->set('catalogLoaded', true)
        ->call('requestConfirm')
        ->call('save')
        ->assertSet('isSubmitted', true);

    // The read-only view right after submit renders from submittedEmployees
    // without a fresh mount(), so it must be populated in the same request.
    $page->assertSet('submittedEmployees', [
        ['code' => 'EMP-001', 'name' => 'Staff Gudang', 'position' => 'Warehouse Staff'],
    ]);

    $card = StockCard::where('branch_id', $this->branch->id)->whereDate('report_date', now())->first();

    expect($card)->not->toBeNull();
    expect(StockCardEntry::count())->toBe(2);
    expect($card->employees()->count())->toBe(1);
    expect($card->employees()->first()->employee_code)->toBe('EMP-001');
});

it('requires at least one staff in charge before confirming submission', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => ''],
        ])
        ->set('employeeIds', [])
        ->set('catalogLoaded', true)
        ->call('requestConfirm')
        ->assertHasErrors(['employeeIds']);
});

it('rejects an employee id that does not belong to an active employee on the branch', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $otherBranchEmployee = Employee::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => ''],
        ])
        ->set('employeeIds', [$otherBranchEmployee->id])
        ->set('catalogLoaded', true)
        ->call('requestConfirm')
        ->assertHasErrors(['employeeIds.0']);
});

it('loads the rolling Daily Usage catalog into the stock card application', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.COM01' => 'branch-token',
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    (new EsbService)->cacheStockCardCatalog($this->branch, now(), 'stockUnit', [
        'products' => [[
            'product_code' => 'WIP-001',
            'product_name' => 'Adonan Croissant',
            'category' => 'Barang WIP',
            'unit' => 'KG',
            'usage_days' => 1,
            'total_qty' => 2.0,
        ]],
        'period_from' => now()->subMonthNoOverflow()->toDateString(),
        'period_to' => now()->toDateString(),
        'failed_requests' => 0,
    ]);

    Livewire::test(StockCardEntryPage::class)
        ->call('loadProductCatalog')
        ->assertSet('catalogLoaded', true)
        ->assertSet('rows.0.product_code', 'WIP-001')
        ->assertSet('rows.0.product_category', 'Barang WIP');
});

it('loads Daily Usage progressively and exposes its progress to the application', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.COM01' => 'branch-token',
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Http::fake([
        'https://sales-esb.test/corev1/sales/get-daily-sales-material-usage*' => Http::response([[
            'productCode' => 'WIP-001',
            'productName' => 'Adonan Croissant',
            'totalQty' => 2,
            'unit' => 'KG',
        ]]),
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'data' => [[
                    'productID' => 1,
                    'productCode' => 'WIP-001',
                    'productName' => 'Adonan Croissant',
                    'categoryName' => 'Barang WIP',
                    'productDetails' => [],
                ]],
                'next' => '',
            ],
        ]),
    ]);

    $pairId = $this->branch->esbCodes()->value('id');
    $page = Livewire::test(StockCardEntryPage::class)
        ->call('loadProductCatalog')
        ->assertSet('catalogLoading', true)
        ->assertSet('catalogTaskIndex', 0);

    $page
        ->set('catalogPairIds', [$pairId])
        ->set('catalogDates', [now()->toDateString()])
        ->set('catalogTaskTotal', 1)
        ->call('fetchNextCatalogUsage')
        ->assertSet('catalogLoading', true)
        ->assertSet('catalogPhase', 'category')
        ->assertSet('catalogCategoryTotal', 1)
        ->call('fetchNextCatalogUsage')
        ->assertSet('catalogLoading', false)
        ->assertSet('catalogLoaded', true)
        ->assertSet('catalogTaskIndex', 1)
        ->assertSet('rows.0.product_code', 'WIP-001');

    expect(Cache::has($page->get('catalogFetchKey') ?? 'missing'))->toBeFalse();
});

it('uses a moving one month window and keeps all WIP plus 30 other products', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.COM01' => 'branch-token',
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    $usageRows = collect(range(1, 39))->map(fn (int $number): array => [
        'productCode' => 'PRD-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
        'productName' => 'Product '.$number,
        'totalQty' => $number,
        'unit' => 'PCS',
    ])->all();
    $masterProducts = collect($usageRows)->map(function (array $row, int $index): array {
        $number = $index + 1;

        return [
            'productID' => $number,
            'productCode' => $row['productCode'],
            'productName' => $row['productName'],
            'categoryName' => $number <= 4 ? 'Barang WIP' : 'Bahan Baku',
            'productDetails' => [[
                'productDetailID' => $number,
                'unit' => 'PCS',
                'conversionFactor' => 1,
                'basePrice' => 1000,
            ]],
        ];
    })->all();

    Http::fake([
        'https://sales-esb.test/corev1/sales/get-daily-sales-material-usage*' => Http::response($usageRows),
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 100,
                'count' => count($masterProducts),
                'data' => $masterProducts,
                'next' => '',
            ],
        ]),
    ]);

    $catalog = (new EsbService)->getRollingStockCardProductsForBranch(
        $this->branch,
        '2026-08-10',
    );

    $products = collect($catalog['products']);

    expect($catalog['period_from'])->toBe('2026-07-10')
        ->and($catalog['period_to'])->toBe('2026-08-10')
        ->and($products)->toHaveCount(34)
        ->and($products->where('category', 'Barang WIP'))->toHaveCount(4)
        ->and($products->where('category', 'Bahan Baku'))->toHaveCount(30)
        ->and($products->pluck('product_code')->unique())->toHaveCount(34)
        ->and($products->pluck('product_code'))->toContain('PRD-039')
        ->and($products->pluck('product_code'))->not->toContain('PRD-005');
});

it('preserves filled draft rows that are no longer returned by the rolling catalog', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [[
            'product_code' => 'OLD-001',
            'product_name' => 'Draft Lama',
            'product_category' => 'Bahan Baku',
            'system_unit' => 'KG',
            'actual_qty' => '3',
            'notes' => '',
        ]])
        ->set('employeeIds', [$this->employee->id]);

    Livewire::test(StockCardEntryPage::class)
        ->assertSet('rows.0.product_code', 'OLD-001')
        ->assertSet('rows.0.actual_qty', '3');
});

it('marks rolling Daily Usage entries as automatic products', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => ''],
        ])
        ->set('employeeIds', [$this->employee->id])
        ->set('catalogLoaded', true)
        ->call('requestConfirm')
        ->call('save')
        ->assertSet('isSubmitted', true);

    $entry = StockCardEntry::where('product_code', 'MAT-001')->first();

    expect($entry->is_manual)->toBeFalse()
        ->and($entry->system_qty)->toBeNull();
});

it('shows back-office-matching status text and complete data on the history page', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $card = StockCard::factory()->pendingFinance()->create([
        'branch_id' => $this->branch->id,
        'report_date' => today()->subDay(),
    ]);
    StockCardEntry::factory()->create([
        'stock_card_id' => $card->id,
        'reported_qty' => 5,
        'system_qty' => 4,
        'actual_qty' => 4,
        'supervisor_notes' => 'Dikoreksi setelah cek ulang.',
    ]);
    $card->employees()->create([
        'employee_id' => $this->employee->id,
        'employee_code' => $this->employee->employee_code,
        'employee_name' => $this->employee->name,
        'employee_position' => $this->employee->position,
    ]);

    Livewire::test(StockCardHistoryPage::class)
        ->assertSee(StockCardStatus::PendingFinance->getLabel())
        ->assertDontSee('Menunggu Review Finance')
        ->call('toggleCard', $card->id)
        ->assertSee('Staff Gudang')
        ->assertSee('Dikoreksi setelah cek ulang.');
});

// ── Helpdesk panel ───────────────────────────────────────────────────────────

it('allows admin to list stock cards', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->givePermissionTo('view stock cards');
    actingAs($admin);

    StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id]);

    Livewire::test(ListStockCards::class)->assertOk();
});

it('shows the delete action on the index and deletes related stock card data for permitted users', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo(['view stock cards', 'delete stock cards']);
    actingAs($admin);

    $card = StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id]);
    $entry = StockCardEntry::factory()->create(['stock_card_id' => $card->id]);

    Livewire::test(ListStockCards::class)
        ->assertTableActionVisible('delete', $card)
        ->callTableAction('delete', $card)
        ->assertHasNoActionErrors();

    expect(StockCard::find($card->id))->toBeNull()
        ->and(StockCardEntry::find($entry->id))->toBeNull();
});

it('hides the delete action on the index without delete stock cards permission', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $viewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $viewer->givePermissionTo('view stock cards');
    actingAs($viewer);

    $card = StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id]);

    Livewire::test(ListStockCards::class)
        ->assertTableActionHidden('delete', $card);
});

it('allows admin to view stock card detail with entries', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo('view stock cards');
    actingAs($admin);

    $card = StockCard::factory()->submitted()->create(['branch_id' => $this->branch->id]);
    StockCardEntry::factory()->create([
        'stock_card_id' => $card->id,
        'actual_qty' => 4.5,
        'notes' => 'Defisit kecil',
    ]);

    Livewire::test(ViewStockCard::class, ['record' => $card])->assertOk();
});

it('shows the staff in charge in the back office list and detail view', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => ''],
        ])
        ->set('employeeIds', [$this->employee->id])
        ->set('catalogLoaded', true)
        ->call('requestConfirm')
        ->call('save')
        ->assertSet('isSubmitted', true);

    $card = StockCard::where('branch_id', $this->branch->id)->whereDate('report_date', now())->firstOrFail();

    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->givePermissionTo('view stock cards');
    actingAs($admin);

    Livewire::test(ListStockCards::class)->assertSee('Staff Gudang');

    Livewire::test(ViewStockCard::class, ['record' => $card])
        ->assertOk()
        ->assertSee('Staff Gudang');
});
