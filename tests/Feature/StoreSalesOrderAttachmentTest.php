<?php

use App\Enums\StoreSalesOrderProductType;
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
    Storage::fake('b2');
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
    $this->submitter = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $this->submitter->givePermissionTo('create store sales orders');
    config()->set('esb.core.companies.BLSS', ['username' => 'sales-user', 'password' => 'sales-secret']);
});

function fakeAttachmentTestLookup(): void
{
    Http::fake([
        'https://services.esb.co.id/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => [[
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
        ]]]]),
        'https://services.esb.co.id/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);
}

function attachedStoreSalesOrder(Branch $branch, User $submitter): StoreSalesOrder
{
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    Livewire::actingAs($submitter);
    fakeAttachmentTestLookup();

    Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('items', [['product_type' => StoreSalesOrderProductType::Db50Pack->value, 'custom_detail' => '', 'quantity' => '2', 'notes' => '']])
        ->set('attachments', [UploadedFile::fake()->image('referensi.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    return StoreSalesOrder::query()->sole();
}

it('lets the submitter and a branch-scoped reviewer open the attachment through the authorized route', function () {
    $order = attachedStoreSalesOrder($this->branch, $this->submitter);
    $path = $order->attachment_paths[0];

    $this->actingAs($this->submitter)
        ->get(route('helpdesk.store-sales-orders.attachments.show', ['path' => $path]))
        ->assertOk();

    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $reviewer->givePermissionTo(['view any store sales orders', 'view store sales orders']);
    $this->actingAs($reviewer)
        ->get(route('helpdesk.store-sales-orders.attachments.show', ['path' => $path]))
        ->assertOk();
});

it('refuses an outsider or a reviewer without branch access', function () {
    $order = attachedStoreSalesOrder($this->branch, $this->submitter);
    $path = $order->attachment_paths[0];

    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider)
        ->get(route('helpdesk.store-sales-orders.attachments.show', ['path' => $path]))
        ->assertForbidden();

    $reviewerElsewhere = User::factory()->create(['is_active' => true, 'branch_id' => Branch::factory()->create()->id]);
    $reviewerElsewhere->givePermissionTo(['view any store sales orders', 'view store sales orders']);
    $this->actingAs($reviewerElsewhere)
        ->get(route('helpdesk.store-sales-orders.attachments.show', ['path' => $path]))
        ->assertForbidden();
});

it('404s for a path that is not registered on any order, instead of serving any file on the disk', function () {
    attachedStoreSalesOrder($this->branch, $this->submitter);
    Storage::disk('b2')->put('store-sales-orders/'.$this->branch->id.'/not-registered.jpg', 'fake-bytes');

    $this->actingAs($this->submitter)
        ->get(route('helpdesk.store-sales-orders.attachments.show', ['path' => 'store-sales-orders/'.$this->branch->id.'/not-registered.jpg']))
        ->assertNotFound();
});

it('deletes already-uploaded files when the Action rejects the submission, instead of leaving them orphaned', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));

    // A record already exists for the same ESB identity (company + numeric ESB branch ID) under a
    // *different* local Branch the submitter has no view access to — duplicate detection matches
    // on that ESB identity, not the local branch_id (docs/store-sales-order-prd.md §14), so this
    // rejection happens inside CreateStoreSalesOrderAction itself, after the Page has already
    // stored the file, without needing to defeat the lookup-clearing side effect of changing
    // `branchId` mid-flow (which clearLookup() would otherwise trigger).
    $otherBranch = Branch::factory()->create();
    $otherMapping = BranchEsbCode::query()->create([
        'branch_id' => $otherBranch->id,
        'esb_comcode' => 'BLSS',
        'esb_branch_id' => 6,
        'esb_branch_code' => 'PBL2',
        'label' => 'NO LABEL',
        'is_active' => true,
    ]);
    StoreSalesOrder::factory()->create([
        'branch_id' => $otherBranch->id,
        'branch_esb_code_id' => $otherMapping->id,
        'company_code_snapshot' => 'BLSS',
        'esb_branch_id_snapshot' => 6,
        'product_sales_number' => 'SL-00123',
        'submitted_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->submitter);
    fakeAttachmentTestLookup();

    $component = Livewire::test(StoreSalesOrderPage::class)
        ->set('branchId', $this->branch->id)
        ->set('productSalesNumber', 'SL-00123')
        ->call('lookupEsb')
        ->assertHasNoErrors()
        ->set('items', [['product_type' => StoreSalesOrderProductType::Db50Pack->value, 'custom_detail' => '', 'quantity' => '2', 'notes' => '']])
        ->set('attachments', [UploadedFile::fake()->image('bukti.jpg')]);

    $component->call('submit')->assertHasErrors();

    Storage::disk('b2')->assertDirectoryEmpty('store-sales-orders/'.$this->branch->id);
    expect(StoreSalesOrder::query()->count())->toBe(1);
});

it('does not delete attachments from disk when an order is soft-deleted', function () {
    $order = attachedStoreSalesOrder($this->branch, $this->submitter);
    $path = $order->attachment_paths[0];
    Storage::disk('b2')->assertExists($path);

    $order->delete();

    Storage::disk('b2')->assertExists($path);
    expect(StoreSalesOrder::withTrashed()->find($order->id)->trashed())->toBeTrue();
});
