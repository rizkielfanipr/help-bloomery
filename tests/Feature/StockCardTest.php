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
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
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
        ->call('addProduct', 'MAT-001', 'Ayam', 'KG')
        ->set('rows.0.actual_qty', '5')
        ->set('rows.0.notes', 'Catatan uji')
        ->set('employeeIds', [$this->employee->id]);

    // Simulate a page refresh: a brand new component instance mounts fresh.
    Livewire::test(StockCardEntryPage::class)
        ->assertSet('isSubmitted', false)
        ->assertSet('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => 'Catatan uji'],
        ])
        ->assertSet('employeeIds', [$this->employee->id]);
});

it('removes the draft entry from the database when the product is removed before it is filled', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->call('addProduct', 'MAT-001', 'Ayam', 'KG')
        ->call('removeProduct', 'MAT-001');

    expect(StockCardEntry::where('product_code', 'MAT-001')->exists())->toBeFalse();
});

it('blocks requestConfirm when no product has been added', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
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
        ->call('requestConfirm')
        ->assertHasErrors(['employeeIds.0']);
});

it('searches master products via the Core login-based API and excludes ones already in the list', function () {
    config()->set([
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
        'esb.master_product.base_url' => 'https://master-product.test',
        'esb.master_product.token' => 'static-token',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/list*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 20,
                'count' => 1,
                'data' => [['productID' => 1, 'productCode' => 'MAT-001', 'productName' => 'Ayam Fillet']],
                'prev' => '',
                'next' => '',
            ],
        ]),
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'data' => [[
                    'productID' => 1,
                    'productCode' => 'MAT-001',
                    'productName' => 'Ayam Fillet',
                    'productDetails' => [['productDetailID' => 101, 'unit' => 'KG', 'conversionFactor' => 1, 'basePrice' => 50000]],
                ]],
                'next' => '',
            ],
        ]),
    ]);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Sudah Ada', 'system_unit' => 'KG', 'actual_qty' => '', 'notes' => ''],
        ])
        ->set('productSearch', 'Ayam')
        ->assertSet('productSearchResults', []);
});

it('adds a product and removes it while unfilled', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    $page = Livewire::test(StockCardEntryPage::class)
        ->call('addProduct', 'MAN-001', 'Gula', 'KG')
        ->assertSet('rows', [
            ['product_code' => 'MAN-001', 'product_name' => 'Gula', 'system_unit' => 'KG', 'actual_qty' => '', 'notes' => ''],
        ]);

    $page->call('removeProduct', 'MAN-001')
        ->assertSet('rows', []);
});

it('does not remove a product once its actual qty has been filled in', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->call('addProduct', 'MAN-001', 'Gula', 'KG')
        ->set('rows.0.actual_qty', '3')
        ->call('removeProduct', 'MAN-001')
        ->assertSet('rows.0.product_code', 'MAN-001');
});

it('always marks entries as manual since there is no ESB usage baseline at entry time', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    actingAs($this->storeUser);

    Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'MAT-001', 'product_name' => 'Ayam', 'system_unit' => 'KG', 'actual_qty' => '5', 'notes' => ''],
        ])
        ->set('employeeIds', [$this->employee->id])
        ->call('requestConfirm')
        ->call('save')
        ->assertSet('isSubmitted', true);

    $entry = StockCardEntry::where('product_code', 'MAT-001')->first();

    expect($entry->is_manual)->toBeTrue()
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
