<?php

use App\Actions\Inventory\Receiving\SaveNonPoReceiptDraftAction;
use App\Actions\Inventory\Receiving\SubmitNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Filament\Casual\Pages\GoodsReceiptPage;
use App\Filament\Casual\Pages\NonPoReceiptPage;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use App\Models\User;
use App\Services\EsbGoodsReceiptService;
use App\Services\EsbItemJournalService;
use App\Services\EsbService;
use App\Services\EsbStockMovementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** @return array<string, mixed> */
function masterProduct(int $detailId = 501, string $unit = 'KG', string $name = 'Butter'): array
{
    return [
        'productDetailID' => $detailId, 'productID' => $detailId + 1000, 'productCode' => "PRD-{$detailId}", 'productName' => $name,
        'categoryName' => 'Bahan', 'subCategoryName' => 'Dairy', 'unit' => $unit, 'baseUnit' => $unit, 'conversionFactor' => 1.0,
    ];
}

/**
 * ESB as a read-only boundary: creating a Goods Receipt or reading a PO during draft/submit fails the test.
 *
 * @param  array<int, array<string, mixed>>|null  $products
 */
function fakeInventoryEsb(?array $products = null): void
{
    $branches = Mockery::mock(EsbItemJournalService::class);
    $branches->shouldReceive('branches')->with('BLSS')->andReturn([['branchID' => 10, 'branchCode' => 'BLS', 'branchName' => 'Central Kitchen']]);
    $products ??= [501 => masterProduct(), 502 => masterProduct(502, 'PCS', 'Box')];
    $branches->shouldReceive('products')->andReturnUsing(fn (string $company, array $filters): array => [
        'page' => $filters['page'], 'limit' => 20, 'count' => count($products), 'prev' => null, 'next' => null,
        'data' => array_values(array_map(fn (array $product): array => ['productCode' => $product['productCode']], $products)),
    ])->byDefault();
    app()->instance(EsbItemJournalService::class, $branches);

    $goodsReceipts = Mockery::mock(EsbGoodsReceiptService::class);
    $goodsReceipts->shouldReceive('locations')->with('BLSS', 10)->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    $goodsReceipts->shouldNotReceive('create');
    $goodsReceipts->shouldNotReceive('purchaseOrder');
    app()->instance(EsbGoodsReceiptService::class, $goodsReceipts);

    $esb = Mockery::mock(EsbService::class);
    $esb->shouldReceive('getActiveProductDetailsByCodes')->andReturn($products);
    app()->instance(EsbService::class, $esb);

    $stock = Mockery::mock(EsbStockMovementService::class);
    $stock->shouldNotReceive('availableBalancesForItemJournal');
    app()->instance(EsbStockMovementService::class, $stock);
}

function inventoryUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('INVENTORY_STAFF');

    return $user;
}

/** A complete form with one product, ready to submit once the photos are attached. */
function filledNonPoForm(): Testable
{
    return Livewire::test(NonPoReceiptPage::class)
        ->call('openForm')
        ->set('branchId', '10')
        ->set('supplierText', 'PT Sumber Dairy')
        ->set('reason', 'Barang datang sebelum PO terbit')
        ->set('documentNumber', 'DO-1')
        ->set('documentPhotos', [UploadedFile::fake()->image('document.jpg')])
        ->set('goodsPhotos', [UploadedFile::fake()->image('goods.jpg')])
        ->call('openProductPicker')
        ->call('loadProducts')
        ->call('addProduct', 501)
        ->set('items.0.physicalQty', 10);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    Storage::fake('b2');
    fakeInventoryEsb();
    $this->inventory = inventoryUser();
    $this->actingAs($this->inventory);
});

it('offers Tanpa PO inside the Receiving tile only to Inventory with the permission', function () {
    $goodsReceipts = app(EsbGoodsReceiptService::class);
    $goodsReceipts->shouldReceive('purchaseOrders')->andReturn([]);

    Livewire::test(GoodsReceiptPage::class)->assertSee('Dengan PO')->assertSee('Tanpa PO');

    $receivingOnly = User::factory()->create(['is_active' => true]);
    $receivingOnly->givePermissionTo('access employee app goods receipt');
    $this->actingAs($receivingOnly);

    Livewire::test(GoodsReceiptPage::class)->assertDontSee('Tanpa PO');
    Livewire::test(NonPoReceiptPage::class)->assertForbidden();
});

it('saves an incomplete draft locally without reading or writing ESB purchasing data', function () {
    Livewire::test(NonPoReceiptPage::class)
        ->call('openForm')
        ->assertSet('branches.0.branchID', 10)
        ->set('supplierText', 'PT Draft')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $receipt = NonPoReceipt::query()->sole();
    expect($receipt->status)->toBe(NonPoReceiptStatus::Draft)
        ->and($receipt->receipt_number)->toBeNull()
        ->and($receipt->company_code)->toBe('BLSS')
        ->and($receipt->created_by)->toBe($this->inventory->id)
        ->and($receipt->statusHistories()->count())->toBe(1);
});

it('saving a draft twice keeps one record for one submission key', function () {
    $component = filledNonPoForm()->call('saveDraft')->assertHasNoErrors()->set('items.0.physicalQty', 12)->call('saveDraft')->assertHasNoErrors();

    $receipt = NonPoReceipt::query()->sole();
    expect($receipt->items()->count())->toBe(1)
        ->and((float) $receipt->items()->sole()->physical_qty)->toBe(12.0)
        ->and(count($receipt->document_photos))->toBe(1);
    $component->assertSet('documentPhotos', []);
});

it('submits to Purchasing locally, numbers the receipt, and notifies once even when repeated', function () {
    $purchasing = User::factory()->create(['is_active' => true]);
    $purchasing->assignRole('PURCHASING_STAFF');

    filledNonPoForm()->call('submit')->assertHasNoErrors()->assertSet('formOpen', false);

    $receipt = NonPoReceipt::query()->with('items')->sole();
    expect($receipt->status)->toBe(NonPoReceiptStatus::AwaitingPurchasing)
        ->and($receipt->receipt_number)->toStartWith('NPR-')
        ->and($receipt->submitted_by)->toBe($this->inventory->id)
        ->and($receipt->items->sole()->disposition)->toBe('accepted')
        ->and((float) $receipt->items->sole()->accepted_qty)->toBe(10.0)
        ->and($receipt->goods_receipt_id)->toBeNull()
        ->and($purchasing->notifications()->count())->toBe(1);

    $number = $receipt->receipt_number;
    $again = app(SubmitNonPoReceiptAction::class)->execute($this->inventory, $receipt);

    expect($again->status)->toBe(NonPoReceiptStatus::AwaitingPurchasing)
        ->and($again->receipt_number)->toBe($number)
        ->and($receipt->statusHistories()->where('to_status', 'awaiting_purchasing')->count())->toBe(1)
        ->and($purchasing->notifications()->count())->toBe(1);
});

it('requires the fields and evidence of a real receipt before submit', function () {
    Livewire::test(NonPoReceiptPage::class)
        ->call('openForm')
        ->call('submit')
        ->assertHasErrors(['supplierText', 'reason', 'branchId', 'locationId', 'documentNumber', 'documentPhotos', 'goodsPhotos', 'items']);

    expect(NonPoReceipt::query()->sole()->status)->toBe(NonPoReceiptStatus::Draft);
});

it('rejects zero qty and a split that does not add up to the physical qty', function () {
    filledNonPoForm()->set('items.0.physicalQty', 0)->call('submit')->assertHasErrors('items.0.physicalQty');

    filledNonPoForm()
        ->call('openItemDetail', 0)
        ->set('items.0.physicalQty', 10)
        ->set('items.0.acceptedQty', 4)
        ->set('items.0.holdQty', 1)
        ->call('submit')
        ->assertHasErrors('items.0.physicalQty');

    expect(NonPoReceipt::query()->pluck('status')->unique()->all())->toBe([NonPoReceiptStatus::Draft]);
});

it('picks products from the same sheet as Item Journal, one at a time, and refuses duplicates', function () {
    $component = Livewire::test(NonPoReceiptPage::class)
        ->call('openForm')
        ->call('openProductPicker')
        ->call('loadProducts')
        ->assertSee('Pilih Produk')
        ->assertSee('Master Product ESB aktif untuk Receiving Tanpa PO.')
        ->assertSeeHtml('placeholder="Cari nama produk"')
        ->assertSeeHtml('placeholder="Cari kode produk"')
        ->assertSeeHtml('wire:click="addProduct(501)"')
        ->assertSee('Butter')
        ->assertDontSee('stok saat ini')
        ->assertDontSee('Stok saat ini')
        ->call('addProduct', 501)
        ->assertSet('productPickerOpen', false)
        ->assertDontSee('Cari nama produk');

    $component->call('openProductPicker')->call('loadProducts')->call('addProduct', 502)->assertSet('productPickerOpen', false);
    $component->call('openProductPicker')->call('loadProducts')->call('addProduct', 501)->assertNotified('Produk sudah dipilih');

    expect($component->get('items'))->toHaveCount(2);

    $component->call('openProductPicker')->call('loadProducts')->call('addProduct', 999)->assertStatus(422);
});

it('searches and pages the picker on the server without reading or showing stock', function () {
    $journalService = app(EsbItemJournalService::class);
    $journalService->shouldReceive('products')->with('BLSS', Mockery::on(fn (array $filters): bool => $filters['productName'] === 'butter' && $filters['page'] === 1))
        ->once()->andReturn(['page' => 1, 'limit' => 20, 'count' => 40, 'prev' => null, 'next' => 'next', 'data' => [['productCode' => 'PRD-501']]]);
    $journalService->shouldReceive('products')->with('BLSS', Mockery::on(fn (array $filters): bool => $filters['productName'] === 'butter' && $filters['page'] === 2))
        ->once()->andReturn(['page' => 2, 'limit' => 20, 'count' => 40, 'prev' => 'prev', 'next' => null, 'data' => [['productCode' => 'PRD-501']]]);

    Livewire::test(NonPoReceiptPage::class)
        ->call('openForm')
        ->set('branchId', '10')
        ->assertSet('locationId', '9')
        ->call('openProductPicker')
        ->set('productSearch', 'butter')
        ->assertSet('productHasNext', true)
        ->assertDontSee('Stok saat ini')
        ->call('nextProductPage')
        ->assertSet('productPage', 2)
        ->assertSet('productHasNext', false);
});

it('rejects duplicate product identity on the action and in the database', function () {
    $item = array_replace(NonPoReceiptItem::defaultQcData(), [
        'productID' => 1, 'productDetailID' => 501, 'productCode' => 'A', 'productName' => 'Butter', 'uomName' => 'KG',
        'physicalQty' => 1, 'acceptedQty' => 1, 'holdQty' => 0, 'rejectedQty' => 0, 'batches' => [], 'notes' => '',
    ]);

    expect(fn () => app(SaveNonPoReceiptDraftAction::class)->execute($this->inventory, (string) Str::uuid(), [
        'receipt_date' => today()->toDateString(), 'document_photos' => [], 'goods_photos' => [],
    ], [$item, array_replace($item, ['uomName' => ' kg '])]))->toThrow(ValidationException::class);

    $receipt = NonPoReceipt::factory()->create();
    NonPoReceiptItem::factory()->for($receipt)->create(['product_detail_id' => 501, 'uom_name' => 'KG']);

    expect(fn () => NonPoReceiptItem::factory()->for($receipt)->create(['product_detail_id' => 501, 'uom_name' => 'KG']))
        ->toThrow(QueryException::class);
});

it('takes product fields from the server and rejects a tampered product', function () {
    filledNonPoForm()->set('items.0.productName', 'Hacked')->set('items.0.uomName', 'TON')->call('saveDraft')->assertHasNoErrors();

    $item = NonPoReceiptItem::query()->sole();
    expect($item->product_name)->toBe('Butter')->and($item->uom_name)->toBe('KG');

    filledNonPoForm()->set('items.0.productDetailID', 777)->call('saveDraft')->assertHasErrors('items.0.productDetailID');
});

it('blocks submit when Master Product no longer has the product active', function () {
    $component = filledNonPoForm()->call('saveDraft');
    fakeInventoryEsb([]);

    $component->call('submit')->assertHasErrors('items.0.productDetailID');

    expect(NonPoReceipt::query()->sole()->status)->toBe(NonPoReceiptStatus::Draft);
});

it('rejects a location that is not in the branch on submit', function () {
    $receipt = NonPoReceipt::factory()->create(['location_id' => 12345]);
    NonPoReceiptItem::factory()->for($receipt)->create(['product_detail_id' => 501, 'product_code' => 'PRD-501', 'uom_name' => 'KG']);

    expect(fn () => app(SubmitNonPoReceiptAction::class)->execute($this->inventory, $receipt))->toThrow(ValidationException::class);
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::Draft);
});

it('keeps all-Hold local and closes all-Rejected without involving Purchasing', function (string $qtyField, NonPoReceiptStatus $expected) {
    $purchasing = User::factory()->create(['is_active' => true]);
    $purchasing->assignRole('PURCHASING_STAFF');
    $receipt = NonPoReceipt::factory()->create();
    NonPoReceiptItem::factory()->for($receipt)->exception($qtyField)->create(['product_detail_id' => 501, 'product_code' => 'PRD-501', 'uom_name' => 'KG']);

    $updated = app(SubmitNonPoReceiptAction::class)->execute($this->inventory, $receipt);

    expect($updated->status)->toBe($expected)
        ->and($updated->receipt_number)->toStartWith('NPR-')
        ->and($purchasing->notifications()->count())->toBe(0);
})->with([
    'hold' => ['hold_qty', NonPoReceiptStatus::QcHold],
    'rejected' => ['rejected_qty', NonPoReceiptStatus::QcRejected],
]);

it('requires QC evidence for Hold and Rejected qty', function () {
    $receipt = NonPoReceipt::factory()->create();
    NonPoReceiptItem::factory()->for($receipt)->exception('rejected_qty')->create(['product_detail_id' => 501, 'product_code' => 'PRD-501', 'uom_name' => 'KG', 'evidence_photos' => []]);

    expect(fn () => app(SubmitNonPoReceiptAction::class)->execute($this->inventory, $receipt))->toThrow(ValidationException::class);
});

it('releases a QC Hold to Purchasing once the qty is Accepted', function () {
    $receipt = NonPoReceipt::factory()->create(['status' => NonPoReceiptStatus::QcHold, 'receipt_number' => 'NPR-1']);
    NonPoReceiptItem::factory()->for($receipt)->create(['product_detail_id' => 501, 'product_code' => 'PRD-501', 'uom_name' => 'KG']);

    expect(app(SubmitNonPoReceiptAction::class)->execute($this->inventory, $receipt)->status)->toBe(NonPoReceiptStatus::AwaitingPurchasing)
        ->and($receipt->refresh()->receipt_number)->toBe('NPR-1');
});

it('locks a submitted receipt and reopens it only through a revision', function () {
    $receipt = NonPoReceipt::factory()->awaitingPurchasing()->create();
    NonPoReceiptItem::factory()->for($receipt)->create(['product_detail_id' => 501, 'product_code' => 'PRD-501', 'uom_name' => 'KG']);

    Livewire::test(NonPoReceiptPage::class)->call('editReceipt', $receipt->id)->assertForbidden();

    $receipt->update(['status' => NonPoReceiptStatus::RevisionRequested]);
    $receipt->statusHistories()->create(['to_status' => NonPoReceiptStatus::RevisionRequested, 'notes' => 'Nomor dokumen salah']);

    Livewire::test(NonPoReceiptPage::class)
        ->call('editReceipt', $receipt->id)
        ->assertSee('Nomor dokumen salah')
        ->assertSet('submissionKey', $receipt->submission_key)
        ->set('documentNumber', 'DO-BARU')
        ->call('submit')
        ->assertHasNoErrors();

    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::AwaitingPurchasing)->and($receipt->document_number)->toBe('DO-BARU');
});

it('locks the submission key against the browser', function () {
    filledNonPoForm()->set('submissionKey', (string) Str::uuid());
})->throws(CannotUpdateLockedPropertyException::class);

it('deletes uploaded files when saving fails and files removed from a draft', function () {
    $component = filledNonPoForm()->call('saveDraft')->assertHasNoErrors();
    $receipt = NonPoReceipt::query()->sole();
    $document = $receipt->document_photos[0];
    Storage::disk('b2')->assertExists($document);

    $component->call('removeStoredDocumentPhoto', 0)->call('saveDraft')->assertHasNoErrors();
    Storage::disk('b2')->assertMissing($document);
    expect($receipt->refresh()->document_photos)->toBe([]);

    $receipt->update(['status' => NonPoReceiptStatus::AwaitingPurchasing]);
    $component->set('documentPhotos', [UploadedFile::fake()->image('late.jpg')])->call('saveDraft')->assertHasErrors('status');

    expect(collect(Storage::disk('b2')->allFiles())->filter(fn (string $path): bool => str_contains($path, '/documents/')))->toBeEmpty();
});

it('stores files under the receipt key and drops a stored path the record does not own', function () {
    $foreign = 'non-po-receipts/other/documents/secret.jpg';
    Storage::disk('b2')->put($foreign, 'x');

    filledNonPoForm()->set('storedDocumentPhotos', [$foreign])->call('saveDraft')->assertHasNoErrors();

    $receipt = NonPoReceipt::query()->sole();
    expect($receipt->document_photos)->toHaveCount(1)
        ->and($receipt->document_photos[0])->toStartWith("non-po-receipts/{$receipt->submission_key}/documents/");
    Storage::disk('b2')->assertExists($foreign);
});

it('lets Inventory cancel what it still owns with a reason, but not a request Purchasing holds', function () {
    $draft = NonPoReceipt::factory()->create();

    Livewire::test(NonPoReceiptPage::class)->call('cancelReceipt', $draft->id, '  ')->assertHasErrors('reason');
    Livewire::test(NonPoReceiptPage::class)->call('cancelReceipt', $draft->id, 'Salah input')->assertHasNoErrors();

    expect($draft->refresh()->status)->toBe(NonPoReceiptStatus::Cancelled)
        ->and($draft->statusHistories()->sole()->notes)->toBe('Salah input');

    $held = NonPoReceipt::factory()->awaitingPurchasing()->create();
    Livewire::test(NonPoReceiptPage::class)->call('cancelReceipt', $held->id, 'Batal')->assertForbidden();
});

it('enforces submit permission on the action, not just the button', function () {
    $receipt = NonPoReceipt::factory()->create();
    $createOnly = User::factory()->create(['is_active' => true]);
    $createOnly->givePermissionTo(['access employee app goods receipt', 'create non po receipts']);

    expect(fn () => app(SubmitNonPoReceiptAction::class)->execute($createOnly, $receipt))->toThrow(AuthorizationException::class);
});
