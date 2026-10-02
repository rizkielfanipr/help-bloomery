<?php

use App\Enums\StoreSalesOrderEventType;
use App\Enums\StoreSalesOrderProductType;
use App\Enums\StoreSalesOrderStatus;
use App\Filament\Helpdesk\Resources\StoreSalesOrders\Pages\ListStoreSalesOrders;
use App\Filament\Helpdesk\Resources\StoreSalesOrders\Pages\ViewStoreSalesOrder;
use App\Filament\Helpdesk\Resources\StoreSalesOrders\StoreSalesOrderResource;
use App\Models\Branch;
use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

it('shows the Store Sales Orders menu under Operational in the custom back office sidebar', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $this->get(route('filament.helpdesk.resources.store-sales-orders.index'))
        ->assertOk()
        ->assertSee('Operational')
        ->assertSee('Store Sales Orders')
        ->assertSee(route('filament.helpdesk.resources.store-sales-orders.index'), false);
});

it('renders Store Sales Order filters inline in the table header', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    Livewire::test(ListStoreSalesOrders::class)
        ->assertSuccessful()
        ->assertSee('Cari SO/customer...')
        ->assertSee('Pilih rentang tanggal')
        ->assertSee('- Semua Branch -')
        ->assertSee('- Semua Event -');
});

it('scopes the index to the reviewer\'s accessible branches, plus their own submitted orders', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $ownBranch->id]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders']);

    $visible = StoreSalesOrder::factory()->create(['branch_id' => $ownBranch->id]);
    $ownSubmission = StoreSalesOrder::factory()->create(['branch_id' => $otherBranch->id, 'submitted_by' => $reviewer->id]);
    $hidden = StoreSalesOrder::factory()->create(['branch_id' => $otherBranch->id]);

    $this->actingAs($reviewer);

    Livewire::test(ListStoreSalesOrders::class)
        ->assertCanSeeTableRecords([$visible, $ownSubmission])
        ->assertCanNotSeeTableRecords([$hidden]);
});

it('lets a user with access_all_branches see orders from every branch', function () {
    $manager = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $manager->givePermissionTo(['view any store sales orders', 'view store sales orders']);

    $orderA = StoreSalesOrder::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $orderB = StoreSalesOrder::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs($manager);

    Livewire::test(ListStoreSalesOrders::class)->assertCanSeeTableRecords([$orderA, $orderB]);
});

it('searches by Sales Order number, customer, Order By, phone number, and preparation notes', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders']);

    $byNumber = StoreSalesOrder::factory()->create(['branch_id' => $branch->id, 'product_sales_number' => 'SL-UNIQUE-001']);
    $byCustomer = StoreSalesOrder::factory()->create(['branch_id' => $branch->id, 'customer_name_snapshot' => 'Zonk Pelanggan']);
    $byOrderedBy = StoreSalesOrder::factory()->create(['branch_id' => $branch->id, 'ordered_by' => 'Unique Orderer Name']);
    $byPhone = StoreSalesOrder::factory()->create(['branch_id' => $branch->id, 'phone_number' => '089988887777']);
    $byNotes = StoreSalesOrder::factory()->create(['branch_id' => $branch->id, 'preparation_notes' => 'Catatan unik soal dekorasi kue']);
    $unrelated = StoreSalesOrder::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($reviewer);

    Livewire::test(ListStoreSalesOrders::class)
        ->filterTable('search', ['value' => 'SL-UNIQUE-001'])
        ->assertCanSeeTableRecords([$byNumber])
        ->assertCanNotSeeTableRecords([$unrelated]);

    Livewire::test(ListStoreSalesOrders::class)
        ->filterTable('search', ['value' => 'Zonk Pelanggan'])
        ->assertCanSeeTableRecords([$byCustomer]);

    Livewire::test(ListStoreSalesOrders::class)
        ->filterTable('search', ['value' => 'Unique Orderer Name'])
        ->assertCanSeeTableRecords([$byOrderedBy]);

    Livewire::test(ListStoreSalesOrders::class)
        ->filterTable('search', ['value' => '089988887777'])
        ->assertCanSeeTableRecords([$byPhone]);

    Livewire::test(ListStoreSalesOrders::class)
        ->filterTable('search', ['value' => 'dekorasi kue'])
        ->assertCanSeeTableRecords([$byNotes]);
});

it('filters the index by branch and operational status', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders']);

    $matching = StoreSalesOrder::factory()->create(['branch_id' => $branchA->id, 'operational_status' => StoreSalesOrderStatus::InPreparation]);
    $otherBranch = StoreSalesOrder::factory()->create(['branch_id' => $branchB->id, 'operational_status' => StoreSalesOrderStatus::InPreparation]);
    $otherStatus = StoreSalesOrder::factory()->create(['branch_id' => $branchA->id, 'operational_status' => StoreSalesOrderStatus::Submitted]);

    $this->actingAs($reviewer);

    Livewire::test(ListStoreSalesOrders::class)
        ->filterTable('branch_id', $branchA->id)
        ->filterTable('operational_status', StoreSalesOrderStatus::InPreparation->value)
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$otherBranch, $otherStatus]);
});

it('filters realistic dummy orders through every inline header filter', function () {
    $branchA = Branch::factory()->create(['name' => 'UI Test Pabelan']);
    $branchB = Branch::factory()->create(['name' => 'UI Test Jakarta']);
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders']);

    $matching = StoreSalesOrder::factory()->create([
        'branch_id' => $branchA->id,
        'product_sales_number' => 'UI-SL-1001',
        'customer_name_snapshot' => 'Nadia Wedding',
        'required_date' => '2026-10-05',
        'event_type' => StoreSalesOrderEventType::Wedding,
        'esb_status_name' => 'Open',
        'operational_status' => StoreSalesOrderStatus::Submitted,
    ]);
    $other = StoreSalesOrder::factory()->create([
        'branch_id' => $branchB->id,
        'product_sales_number' => 'UI-SL-2002',
        'customer_name_snapshot' => 'Corporate Event',
        'required_date' => '2026-11-15',
        'event_type' => StoreSalesOrderEventType::Corporate,
        'esb_status_name' => 'Processing',
        'operational_status' => StoreSalesOrderStatus::InPreparation,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ListStoreSalesOrders::class)
        ->assertSee('UI-SL-1001')
        ->set('tableFilters.search.value', 'Nadia Wedding')
        ->set('tableFilters.branch_id.value', $branchA->id)
        ->set('tableFilters.event_type.value', StoreSalesOrderEventType::Wedding->value)
        ->set('tableFilters.esb_status_name.value', 'Open')
        ->set('tableFilters.operational_status.value', StoreSalesOrderStatus::Submitted->value)
        ->set('tableFilters.required_date.from', '2026-10-01')
        ->set('tableFilters.required_date.until', '2026-10-10')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('accepts the Submitted -> InPreparation -> Ready -> Delivered transition and records each as an activity', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders', 'update store sales order status']);
    $order = StoreSalesOrder::factory()->create(['branch_id' => $branch->id, 'operational_status' => StoreSalesOrderStatus::Submitted]);

    $this->actingAs($reviewer);
    $page = Livewire::test(ViewStoreSalesOrder::class, ['record' => $order->id]);

    $page->callAction('change_status', ['operational_status' => StoreSalesOrderStatus::InPreparation->value, 'cancellation_reason' => null])
        ->assertHasNoActionErrors();
    expect($order->refresh()->operational_status)->toBe(StoreSalesOrderStatus::InPreparation);

    $page->callAction('change_status', ['operational_status' => StoreSalesOrderStatus::Ready->value, 'cancellation_reason' => null])
        ->assertHasNoActionErrors();
    expect($order->refresh()->operational_status)->toBe(StoreSalesOrderStatus::Ready);

    $page->callAction('change_status', ['operational_status' => StoreSalesOrderStatus::Delivered->value, 'cancellation_reason' => null])
        ->assertHasNoActionErrors();
    expect($order->refresh()->operational_status)->toBe(StoreSalesOrderStatus::Delivered);

    expect(StoreSalesOrderActivity::query()->where('store_sales_order_id', $order->id)
        ->where('activity_type', StoreSalesOrderActivity::TYPE_STATUS_CHANGED)->count())->toBe(3);
});

it('rejects skipping a status in the transition, and rejects Cancelled without a reason', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders', 'update store sales order status']);
    $order = StoreSalesOrder::factory()->create(['branch_id' => $branch->id, 'operational_status' => StoreSalesOrderStatus::Submitted]);

    $this->actingAs($reviewer);
    $page = Livewire::test(ViewStoreSalesOrder::class, ['record' => $order->id]);

    // Submitted -> Delivered skips InPreparation and Ready entirely.
    $page->callAction('change_status', ['operational_status' => StoreSalesOrderStatus::Delivered->value, 'cancellation_reason' => null]);
    expect($order->refresh()->operational_status)->toBe(StoreSalesOrderStatus::Submitted);

    $page->callAction('change_status', ['operational_status' => StoreSalesOrderStatus::Cancelled->value, 'cancellation_reason' => null]);
    expect($order->refresh()->operational_status)->toBe(StoreSalesOrderStatus::Submitted)
        ->and($order->cancellation_reason)->toBeNull();

    $page->callAction('change_status', ['operational_status' => StoreSalesOrderStatus::Cancelled->value, 'cancellation_reason' => 'Customer membatalkan.'])
        ->assertHasNoActionErrors();
    expect($order->refresh()->operational_status)->toBe(StoreSalesOrderStatus::Cancelled)
        ->and($order->cancellation_reason)->toBe('Customer membatalkan.');
});

it('only lets a user with update permission and branch access see Edit Informasi and Ubah Status', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $ownBranch->id]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders', 'update store sales orders', 'update store sales order status']);

    $ownBranchOrder = StoreSalesOrder::factory()->create(['branch_id' => $ownBranch->id]);
    $otherBranchOrder = StoreSalesOrder::factory()->create(['branch_id' => $otherBranch->id, 'submitted_by' => $reviewer->id]);

    $this->actingAs($reviewer);

    expect(StoreSalesOrderResource::canEdit($ownBranchOrder))->toBeTrue()
        ->and(StoreSalesOrderResource::canEdit($otherBranchOrder))->toBeFalse()
        ->and(StoreSalesOrderResource::canView($otherBranchOrder))->toBeTrue();
});

it('updates operational info and items through the Edit Informasi action', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders', 'update store sales orders']);
    // No pre-existing item: the Repeater's fillForm gives each row a Filament-generated UUID key,
    // which a test can't predict, so a pre-seeded row here would sit alongside (not be replaced
    // by) the row this test submits under its own numeric key. Wholesale item *replacement* is
    // already covered at the Action level in StoreSalesOrderDomainTest.
    $order = StoreSalesOrder::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($reviewer);
    $page = Livewire::test(ViewStoreSalesOrder::class, ['record' => $order->id]);

    $page->callAction('edit_info', [
        'phone_number' => '0899999999',
        'ordered_by' => 'Siti',
        'event_type' => null,
        'event_type_other' => null,
        'delivery_time' => null,
        'preparation_notes' => 'Diubah dari back office.',
        'items' => [
            ['product_type' => StoreSalesOrderProductType::SnackBox->value, 'custom_detail' => null, 'quantity' => 3, 'notes' => null],
        ],
    ])->assertHasNoActionErrors();

    $order->refresh()->load('items');
    expect($order->phone_number)->toBe('0899999999')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_type)->toBe(StoreSalesOrderProductType::SnackBox);
});

it('does not grow the index query count as the number of orders grows (no N+1)', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders']);
    $this->actingAs($reviewer);

    StoreSalesOrder::factory()->create(['branch_id' => $branch->id]);
    Livewire::test(ListStoreSalesOrders::class);

    StoreSalesOrder::factory()->count(11)->create(['branch_id' => $branch->id]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(ListStoreSalesOrders::class);
    $smallCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    StoreSalesOrder::factory()->count(14)->create(['branch_id' => $branch->id]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(ListStoreSalesOrders::class);
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});
