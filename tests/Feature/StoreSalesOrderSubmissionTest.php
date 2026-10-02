<?php

use App\Enums\StoreSalesOrderProductType;
use App\Filament\Casual\Pages\LauncherPage;
use App\Filament\Casual\Pages\StoreSalesOrderPage;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\StoreSalesOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $mapping = BranchEsbCode::query()->create([
        'branch_id' => $this->branch->id,
        'esb_comcode' => 'BLSS',
        'esb_branch_id' => 6,
        'esb_branch_code' => 'PBL',
        'label' => 'NO LABEL',
        'is_active' => true,
    ]);
    $this->branch->update(['stock_card_esb_code_id' => $mapping->id]);
    $this->branch->refresh();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->user->givePermissionTo('create store sales orders');
    config()->set('esb.core.companies.BLSS', ['username' => 'sales-user', 'password' => 'sales-secret']);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
});

/** @return array<string, mixed> */
function storeSalesOrderFixtureRow(array $overrides = []): array
{
    return array_replace([
        'productSalesNum' => 'SL-00123',
        'productSalesDate' => '2026-10-01',
        'requiredDate' => '2026-10-05',
        'branchID' => 6,
        'branchName' => 'Bloomery Pabelan',
        'customerID' => 'CUST-01',
        'customerName' => 'Budi Santoso',
        'customerAddress' => 'Jl. Merdeka No. 1',
        'productSalesTotal' => 500000,
        'currencySign' => 'Rp',
        'statusID' => '1',
        'statusName' => 'Open',
        'createdBy' => 'admin.store',
        'linkPurchaseNum' => null,
        'additionalInfo' => null,
    ], $overrides);
}

function fakeStoreSalesOrderLookup(array $overrides = []): void
{
    Http::fake([
        'https://services.esb.co.id/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => [storeSalesOrderFixtureRow($overrides)]]]),
        'https://services.esb.co.id/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);
}

/** @return array{product_type: string, custom_detail: string, quantity: string, notes: string} */
function validItemRow(array $overrides = []): array
{
    return array_merge(['product_type' => StoreSalesOrderProductType::Db50Pack->value, 'custom_detail' => '', 'quantity' => '2', 'notes' => ''], $overrides);
}

it('shows the Store Sales Order tile only to users with the create permission', function () {
    $this->actingAs($this->user);
    $withPermission = Livewire::test(LauncherPage::class);
    expect(collect($withPermission->instance()->tiles())->pluck('label'))->toContain('Store Sales Order');

    $outsider = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $outsider->givePermissionTo('access employee app attendance');
    $this->actingAs($outsider);
    $withoutPermission = Livewire::test(LauncherPage::class);
    expect(collect($withoutPermission->instance()->tiles())->pluck('label'))->not->toContain('Store Sales Order');
});

it('defaults the branch field to the user\'s primary branch', function () {
    $this->actingAs($this->user);

    Livewire::test(StoreSalesOrderPage::class)->assertSet('branchId', $this->branch->id);
});

it('populates the confirmation card after a successful ESB lookup, and clears it when the number changes', function () {
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    $component = Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors();

    expect($component->get('lookupResult')['customer_name'])->toBe('Budi Santoso');

    $component->set('productSalesNumber', 'SL-00999');
    expect($component->get('lookupResult'))->toBeNull();
});

it('shows a validation error and no confirmation card when ESB has no match', function () {
    $this->actingAs($this->user);
    Http::fake([
        'https://services.esb.co.id/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => []]]),
        'https://services.esb.co.id/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-NOTFOUND')
        ->call('lookupEsb')
        ->assertHasErrors(['product_sales_number'])
        ->assertSet('lookupResult', null);
});

it('blocks submit until a successful lookup has been performed', function () {
    $this->actingAs($this->user);

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->set('items', [validItemRow()])
        ->call('submit')
        ->assertHasErrors(['product_sales_number']);

    expect(StoreSalesOrder::query()->count())->toBe(0);
});

it('submits a store sales order with a Submitted status after a valid lookup', function () {
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('phoneNumber', '0812xxxx')
        ->set('orderedBy', 'Budi')
        ->set('items', [validItemRow()])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $order = StoreSalesOrder::query()->sole();
    expect($order->product_sales_number)->toBe('SL-00123')
        ->and($order->submitted_by)->toBe($this->user->id)
        ->and($order->items)->toHaveCount(1);
});

it('rejects a submission for a branch the user cannot access, even if the component state is tampered', function () {
    $otherBranch = Branch::factory()->create();
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('branchId', $otherBranch->id)
        ->set('items', [validItemRow()])
        ->call('submit')
        ->assertHasErrors();

    expect(StoreSalesOrder::query()->count())->toBe(0);
});

it('requires a Custom detail when the Custom product type is chosen', function () {
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('items', [validItemRow(['product_type' => StoreSalesOrderProductType::Custom->value, 'custom_detail' => ''])])
        ->call('submit')
        ->assertHasErrors(['items.0.custom_detail']);

    expect(StoreSalesOrder::query()->count())->toBe(0);
});

it('requires a positive quantity for every item', function () {
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('items', [validItemRow(['quantity' => '0'])])
        ->call('submit')
        ->assertHasErrors(['items.0.quantity']);

    expect(StoreSalesOrder::query()->count())->toBe(0);
});

it('can add and remove product rows, but never below one row', function () {
    $this->actingAs($this->user);

    Livewire::test(StoreSalesOrderPage::class)
        ->assertCount('items', 1)
        ->call('addItem')
        ->assertCount('items', 2)
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->call('removeItem', 0)
        ->assertCount('items', 1);
});

it('does not create two orders from a rapid duplicate submit', function () {
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    $component = Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('items', [validItemRow()]);

    $component->set('isSubmitting', true)->call('submit');
    expect(StoreSalesOrder::query()->count())->toBe(0);

    $component->set('isSubmitting', false)->call('submit')->assertHasNoErrors();
    expect(StoreSalesOrder::query()->count())->toBe(1);
});

it('uploads valid attachments to the private disk and stores their paths', function () {
    Storage::fake('b2');
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('items', [validItemRow()])
        ->set('attachments', [UploadedFile::fake()->image('referensi.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    $order = StoreSalesOrder::query()->sole();
    expect($order->attachment_paths)->toHaveCount(1);
    Storage::disk('b2')->assertExists($order->attachment_paths[0]);
});

it('rejects an attachment with a disallowed file type', function () {
    Storage::fake('b2');
    $this->actingAs($this->user);
    fakeStoreSalesOrderLookup();

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('items', [validItemRow()])
        ->set('attachments', [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
        ->call('submit')
        ->assertHasErrors(['attachments.0']);

    expect(StoreSalesOrder::query()->count())->toBe(0);
});

it('shows up to five recent orders accessible to the user', function () {
    $this->actingAs($this->user);
    StoreSalesOrder::factory()->count(6)->create(['branch_id' => $this->branch->id, 'submitted_by' => $this->user->id]);

    $component = Livewire::test(StoreSalesOrderPage::class);
    expect($component->instance()->getRecentOrders())->toHaveCount(5);
});
