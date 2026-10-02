<?php

use App\Filament\Casual\Pages\GoodsReceiptPage;
use App\Filament\Helpdesk\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptExpiry;
use App\Models\GoodsReceiptItem;
use App\Models\User;
use App\Services\EsbGoodsReceiptService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('goods receipt records store their items and expiry details', function () {
    $receipt = GoodsReceipt::factory()->create();
    $item = GoodsReceiptItem::factory()->for($receipt)->create();
    $expiry = GoodsReceiptExpiry::factory()->for($item, 'item')->create();

    expect($receipt->items()->first()->is($item))->toBeTrue()
        ->and($item->expiries()->first()->is($expiry))->toBeTrue()
        ->and(GoodsReceiptPage::getUrl(panel: 'casual'))->toContain('goods-receipt-page');
});

test('goods receipt menus use the Receiving label', function () {
    expect(app(GoodsReceiptPage::class)->getTitle())->toBe('Receiving')
        ->and(GoodsReceiptResource::getNavigationLabel())->toBe('Receiving');
});

test('purchase orders are ordered by the nearest required date with undated orders last', function () {
    $page = app(GoodsReceiptPage::class);
    $page->purchaseOrders = [
        ['purchaseNum' => 'PO-NO-DATE', 'requiredDate' => null],
        ['purchaseNum' => 'PO-LATER', 'requiredDate' => '2026-10-10T00:00:00+07:00'],
        ['purchaseNum' => 'PO-NEAREST-B', 'requiredDate' => '2026-09-24T00:00:00+07:00'],
        ['purchaseNum' => 'PO-NEAREST-A', 'requiredDate' => '2026-09-24T00:00:00+07:00'],
        ['purchaseNum' => 'PO-EARLIEST', 'requiredDate' => '2026-09-23T00:00:00+07:00'],
        ['purchaseNum' => 'PO-INVALID', 'requiredDate' => 'not-a-date'],
    ];

    expect($page->filteredPurchaseOrders()->pluck('purchaseNum')->all())->toBe([
        'PO-EARLIEST',
        'PO-NEAREST-A',
        'PO-NEAREST-B',
        'PO-LATER',
        'PO-INVALID',
        'PO-NO-DATE',
    ]);
});

test('employee app loads purchase orders that ESB allows to receive', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);

    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->once()->with([
        'page' => 1,
        'limit' => 100,
        'sort' => '-purchaseDate',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
    ])->andReturn(array_merge([[
        'purchaseNum' => 'PO-"AUTHORIZED"-1',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'statusName' => 'Authorized',
    ]], collect(range(2, 11))->map(fn (int $number): array => [
        'purchaseNum' => "PO-AUTH-{$number}",
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'statusName' => 'Authorized',
    ])->all()));
    $service->shouldReceive('purchaseOrders')->once()->with([
        'page' => 1,
        'limit' => 100,
        'sort' => '-purchaseDate',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_RECEIVING,
    ])->andReturn([]);
    $service->shouldReceive('purchaseOrder')->once()->with('PO-"AUTHORIZED"-1')->andReturn([
        'purchaseNum' => 'PO-"AUTHORIZED"-1',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'statusName' => 'Authorized',
        'branchID' => 10,
        'purchaseDetails' => [[
            'ID' => 11,
            'productID' => 12,
            'productDetailID' => 13,
            'productName' => 'Bahan Pending',
            'qty' => 2,
        ]],
    ]);
    $service->shouldReceive('locations')->once()->with(10)->andReturn([
        ['locationID' => 9, 'locationName' => 'Warehouse'],
    ]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    Livewire::test(GoodsReceiptPage::class)
        ->assertSee('PO-"AUTHORIZED"-1')
        ->assertSeeHtml('Buat GR & QC')
        ->assertSee('Buat GR')
        ->assertSee('Tanggal PO')
        ->assertSee('Dibutuhkan')
        ->assertSee('Tanpa Cabang')
        ->assertSeeHtml('Penerimaan & QC Inbound')
        ->assertSeeHtml('aria-label="Cari Purchase Order"')
        ->assertSee('Daftar Purchase Order')
        ->assertSee('Data Authorized dan Receiving dari ESB')
        ->assertSee('Sync ESB')
        ->assertSee('Menyinkronkan Data ESB...')
        ->assertSee('Menampilkan 1–10 dari 11 PO')
        ->assertSeeHtml('aria-label="Halaman Sebelumnya"')
        ->assertSeeHtml('aria-label="Halaman Berikutnya"')
        ->assertDontSee('PO-AUTH-11')
        ->call('goToPurchaseOrderPage', 2)
        ->assertSet('purchaseOrderPage', 2)
        ->assertSee('PO-AUTH-11')
        ->assertSee('Halaman 2 dari 2')
        ->call('goToPurchaseOrderPage', 1)
        ->set('search', 'does-not-match')
        ->assertSet('purchaseOrderPage', 1)
        ->assertDontSeeHtml('Buat GR & QC')
        ->assertSee('Tidak ada PO Authorized/Receiving')
        ->set('search', '')
        ->assertSeeHtml('wire:click="selectPurchaseOrder($event.currentTarget.dataset.po)"')
        ->assertSeeHtml('data-po="PO-&quot;AUTHORIZED&quot;-1"')
        ->call('selectPurchaseOrder', 'PO-"AUTHORIZED"-1')
        ->assertSet('purchaseOrder.purchaseNum', 'PO-"AUTHORIZED"-1')
        ->assertSet('locationId', '9')
        ->assertSeeHtml('aria-label="Kembali ke daftar Purchase Order"')
        ->assertDontSee('← Kembali')
        ->assertSee('Bahan Pending')
        ->assertSee('Warehouse')
        ->assertSee('Nomor Purchase Order')
        ->assertSee('Vendor / Supplier')
        ->assertSee('Cabang Penerima')
        ->assertSee('Tanggal Dibutuhkan')
        ->assertSee('1 Produk')
        ->assertSee('2. Pemeriksaan Produk')
        ->assertSee('Informasi Pengisian')
        ->assertSee('6. Konfirmasi Penerimaan')
        ->assertDontSee('Informasi Tutup PO')
        ->assertDontSee('Tutup PO Otomatis')
        ->assertSee('Dokumen Sesuai')
        ->assertSee('Foto Dokumen')
        ->assertSee('Foto Barang')
        // Compact row by default: the full QC detail is collapsed behind "Bermasalah" until opened.
        ->assertSet('items.0.condition', 'ok')
        ->assertDontSee('Suhu aktual °C')
        ->assertDontSee('Sampling Test Diperlukan')
        ->assertDontSee('Tambah Foto / Screenshot')
        ->assertSet('activeItemIndex', null)
        ->call('openItemDetail', 0)
        ->assertSet('activeItemIndex', 0)
        ->assertSet('items.0.condition', 'problem')
        ->assertSee('Hanya qty Accepted yang dikirim ke ESB')
        ->assertSee('minimum 80%')
        ->assertSee('2. Quantity Check')
        ->assertSee('3. Quality Check')
        ->assertSee('Feedback Loop ke Purchasing')
        ->assertSee('Informasi Proses Otomatis')
        ->assertSee('Sampling Test Diperlukan')
        ->assertDontSee('Suhu aktual °C')
        ->set('items.0.temperatureCategory', 'chilled')
        ->assertSee('Suhu aktual °C')
        ->set('items.0.shelfLifeRequired', true)
        ->assertSee('Minimum sisa shelf life %')
        ->call('addBatch', 0)
        ->assertSee('Hasil Perhitungan Shelf Life')
        ->assertSee('Lengkapi Tanggal ED dan Tanggal Produksi di Detail Batch')
        ->set('items.0.batches.0.manufacturedDate', '2026-09-01')
        ->set('items.0.batches.0.expiredDate', '2027-09-01')
        ->assertSee('Status Shelf Life Item')
        ->assertSee('Lulus')
        ->set('items.0.samplingRequired', true)
        ->assertSee('Hasil sampling')
        ->set('items.0.holdQty', 1)
        ->assertSee('Qty Rejected otomatis menjadi demerit vendor')
        ->assertSee('Lokasi quarantine')
        ->assertSee('Tambah Foto / Screenshot')
        ->set('items.0.holdQty', 0)
        ->call('closeItemDetail')
        ->assertSet('activeItemIndex', null)
        ->assertSeeHtml('Simpan QC & Proses Goods Receipt')
        ->assertDontSeeHtml('Buat GR & QC')
        ->set('goodsReceiptDate', '')
        ->set('locationId', '')
        ->call('submit')
        ->assertHasErrors(['goodsReceiptDate' => 'required', 'locationId' => 'required'])
        ->assertSee(__('validation.required', ['attribute' => 'goods receipt date']))
        ->call('backToList')
        ->assertSet('purchaseOrder', null)
        ->assertSeeHtml('Buat GR & QC')
        ->assertDontSeeHtml('Simpan QC & Proses Goods Receipt');
});

test('receiving saves calculated shelf life for form batches', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    $this->travelTo(now()->setDate(2026, 9, 16));

    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with('PO-BATCH')->twice()->andReturn([
        'purchaseNum' => 'PO-BATCH',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10,
        'purchaseDetails' => [['ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'qty' => 2]],
    ]);
    $service->shouldReceive('locations')->with(10)->andReturn([
        ['locationID' => 9, 'locationName' => 'Warehouse'],
    ]);
    $service->shouldReceive('create')->once()->with('PO-BATCH', Mockery::on(fn (array $payload): bool => $payload['goodsReceiptDetail'][0]['expiredDates'] === [['expiredDate' => '2027-09-01', 'qty' => 2.0]]
    ))->andReturn(['result' => ['goodsReceiptNum' => 'GR-BATCH'], 'response' => ['code' => 'OK', 'message' => 'OK']]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-BATCH')
        ->set('documentType', 'invoice')
        ->set('documentNumber', 'INV-BATCH')
        ->set('documentDate', '2026-09-16')
        ->call('openItemDetail', 0)
        ->set('items.0.shelfLifeRequired', true)
        ->call('addBatch', 0)
        ->set('items.0.batches.0', [
            'batchNumber' => 'BATCH-1', 'manufacturedDate' => '2026-09-01', 'expiredDate' => '2027-09-01',
            'quantity' => 2, 'acceptedQty' => 2, 'holdQty' => 0, 'rejectedQty' => 0,
        ])
        ->call('submit')
        ->assertHasNoErrors();

    $receipt = GoodsReceipt::where('reference_number', 'PO-BATCH')->sole();
    $expiry = $receipt->items()->sole()->expiries()->sole();
    expect($receipt->status)->toBe(GoodsReceipt::STATUS_SUCCEEDED)
        ->and($receipt->submission_key)->not->toBeNull()
        ->and($receipt->payload_hash)->toHaveLength(64)
        ->and($receipt->attempted_at)->not->toBeNull()
        ->and((float) $expiry->shelf_life_remaining_percentage)->toBe(95.89)
        ->and($expiry->qc_result)->toBe('pass');
});

test('receiving guards duplicate submissions and marks an uncertain connection result for reconciliation', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    $this->travelTo(now()->setDate(2026, 9, 16));

    $order = [
        'purchaseNum' => 'PO-UNKNOWN',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10,
        'purchaseDetails' => [[
            'ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'productCode' => 'BB001',
            'productName' => 'Bahan Test', 'uomID' => 1, 'uomName' => 'GR', 'qty' => 2,
        ]],
    ];
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with('PO-UNKNOWN')->times(3)->andReturn($order);
    $service->shouldReceive('locations')->with(10)->once()->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    $service->shouldReceive('create')->once()->andThrow(new ConnectionException('connection reset after send'));
    app()->instance(EsbGoodsReceiptService::class, $service);

    $component = Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-UNKNOWN')
        ->set('documentType', 'invoice')
        ->set('documentNumber', 'INV-UNKNOWN')
        ->set('documentDate', '2026-09-16')
        ->call('submit')
        ->assertHasNoErrors();

    $component->call('submit')->assertHasNoErrors();

    $receipt = GoodsReceipt::query()->where('reference_number', 'PO-UNKNOWN')->sole();
    expect($receipt->status)->toBe(GoodsReceipt::STATUS_UNKNOWN)
        ->and($receipt->esb_goods_receipt_number)->toBeNull()
        ->and($receipt->payload_hash)->toHaveLength(64)
        ->and(GoodsReceipt::query()->where('reference_number', 'PO-UNKNOWN')->count())->toBe(1);
});

test('a single document number and date save correctly for whichever document type is chosen, without requiring the other', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);

    $order = [
        'purchaseNum' => 'PO-DOC-TYPE',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10,
        'purchaseDetails' => [['ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'qty' => 2]],
    ];
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with('PO-DOC-TYPE')->twice()->andReturn($order);
    $service->shouldReceive('locations')->with(10)->once()->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    $service->shouldReceive('create')->once()->with('PO-DOC-TYPE', Mockery::on(fn (array $payload): bool => $payload['deliveryNum'] === 'DO-ONLY'))
        ->andReturn(['result' => ['goodsReceiptNum' => 'GR-DOC-TYPE'], 'response' => ['code' => 'OK', 'message' => 'OK']]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    // "Surat Jalan valid tanpa Invoice" (docs/receiving-simplification-prd.md §19): choosing
    // delivery_note never requires any invoice-specific field.
    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-DOC-TYPE')
        ->set('documentType', 'delivery_note')
        ->set('documentNumber', 'DO-ONLY')
        ->set('documentDate', now()->toDateString())
        ->call('submit')
        ->assertHasNoErrors();

    $receipt = GoodsReceipt::where('reference_number', 'PO-DOC-TYPE')->sole();
    expect($receipt->document_type)->toBe('delivery_note')
        ->and($receipt->document_number)->toBe('DO-ONLY')
        ->and($receipt->document_date->toDateString())->toBe(now()->toDateString())
        ->and($receipt->delivery_number)->toBe('DO-ONLY')
        ->and($receipt->delivery_date->toDateString())->toBe(now()->toDateString())
        ->and($receipt->invoice_number)->toBeNull()
        ->and($receipt->invoice_date)->toBeNull()
        ->and($receipt->invoice_status)->toBe('not_received');
});

test('document photos and goods photos are stored separately, each with an explicit upload-success status', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    Storage::fake('b2');

    $order = [
        'purchaseNum' => 'PO-PHOTOS',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10,
        'purchaseDetails' => [['ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'qty' => 2]],
    ];
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with('PO-PHOTOS')->twice()->andReturn($order);
    $service->shouldReceive('locations')->with(10)->once()->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    $service->shouldReceive('create')->once()->andReturn(['result' => ['goodsReceiptNum' => 'GR-PHOTOS'], 'response' => ['code' => 'OK', 'message' => 'OK']]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-PHOTOS')
        ->set('documentType', 'delivery_note')
        ->set('documentNumber', 'DO-PHOTOS')
        ->set('documentDate', now()->toDateString())
        ->set('documentPhotos', [UploadedFile::fake()->image('surat-jalan.jpg')])
        ->assertSee('Berhasil diunggah')
        ->set('goodsPhotos', [UploadedFile::fake()->image('barang.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    $receipt = GoodsReceipt::where('reference_number', 'PO-PHOTOS')->sole();
    expect($receipt->document_photos)->toHaveCount(1)
        ->and($receipt->goods_photos)->toHaveCount(1)
        ->and($receipt->document_photos)->not->toBe($receipt->goods_photos)
        ->and(Str::startsWith($receipt->document_photos[0], 'goods-receipts/qc/documents/'))->toBeTrue()
        ->and(Str::startsWith($receipt->goods_photos[0], 'goods-receipts/qc/goods/'))->toBeTrue()
        // document_evidence_photos stays populated with both, so legacy readers keep seeing everything.
        ->and($receipt->document_evidence_photos)->toHaveCount(2);
    Storage::disk('b2')->assertExists($receipt->document_photos[0]);
    Storage::disk('b2')->assertExists($receipt->goods_photos[0]);
});

test('submit is blocked client-side while a photo upload is still in flight', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);

    $order = [
        'purchaseNum' => 'PO-UPLOAD-GUARD',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10,
        'purchaseDetails' => [['ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'qty' => 2]],
    ];
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with('PO-UPLOAD-GUARD')->once()->andReturn($order);
    $service->shouldReceive('locations')->with(10)->once()->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    // docs/receiving-simplification-prd.md §5.3 "Tombol submit dinonaktifkan selama file masih
    // diunggah". The actual blocking is client-side Alpine state driven by Livewire's
    // `livewire-upload-start`/`livewire-upload-finish` window events (there is no server-side
    // "upload in progress" state to assert against in a Livewire test), so this asserts the
    // wiring that implements it is present: a shared uploadingCount tracked for the whole page,
    // and the submit button bound to it.
    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-UPLOAD-GUARD')
        ->assertSeeHtml('x-on:livewire-upload-start.window="uploadingCount++"')
        ->assertSeeHtml('x-on:livewire-upload-finish.window="uploadingCount--"')
        ->assertSeeHtml(':disabled="uploadingCount > 0"');
});

function mockMultiItemPurchaseOrder(string $purchaseNumber): EsbGoodsReceiptService
{
    $order = [
        'purchaseNum' => $purchaseNumber,
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10,
        'purchaseDetails' => [
            ['ID' => 1, 'productID' => 1, 'productDetailID' => 1, 'productCode' => 'A1', 'productName' => 'Butter', 'uomID' => 1, 'uomName' => 'KG', 'qty' => 10],
            ['ID' => 2, 'productID' => 2, 'productDetailID' => 2, 'productCode' => 'A2', 'productName' => 'Cream', 'uomID' => 1, 'uomName' => 'KG', 'qty' => 5],
        ],
    ];
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with($purchaseNumber)->andReturn($order);
    $service->shouldReceive('locations')->with(10)->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    return $service;
}

test('bulk actions select all, mark all Sesuai, fill qty from outstanding, and clear qty', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    mockMultiItemPurchaseOrder('PO-BULK');

    $component = Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-BULK')
        ->assertSet('items.0.physicalQty', 10.0)
        ->assertSet('items.1.physicalQty', 5.0)
        ->call('clearAllQty')
        ->assertSet('items.0.physicalQty', 0)
        ->assertSet('items.1.physicalQty', 0)
        ->call('fillQtyFromOutstanding')
        ->assertSet('items.0.physicalQty', 10.0)
        ->assertSet('items.1.physicalQty', 5.0)
        ->set('items.0.selected', false)
        ->call('selectAllItems')
        ->assertSet('items.0.selected', true);

    // Mark item 0 Bermasalah with Hold data, then confirm "Tandai Semua Sesuai" wipes it.
    $component->call('openItemDetail', 0)
        ->set('items.0.holdQty', 3)
        ->set('items.0.rejectionCategory', 'quality')
        ->call('markAllOk')
        ->assertSet('items.0.condition', 'ok')
        ->assertSet('items.0.holdQty', 0)
        ->assertSet('items.0.rejectionCategory', '')
        ->assertSet('items.1.condition', 'ok');
});

test('switching an item back to Sesuai clears exception data entered while Bermasalah', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    mockMultiItemPurchaseOrder('PO-RESET');

    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-RESET')
        ->call('openItemDetail', 0)
        ->assertSet('items.0.condition', 'problem')
        ->set('items.0.holdQty', 2)
        ->set('items.0.temperatureCategory', 'chilled')
        ->set('items.0.shelfLifeRequired', true)
        ->call('addBatch', 0)
        ->set('items.0.rejectionReason', 'Kemasan rusak')
        ->call('setItemCondition', 0, 'ok')
        ->assertSet('items.0.condition', 'ok')
        ->assertSet('items.0.holdQty', 0)
        ->assertSet('items.0.temperatureCategory', 'ambient')
        ->assertSet('items.0.shelfLifeRequired', false)
        ->assertSet('items.0.batches', [])
        ->assertSet('items.0.rejectionReason', '')
        // Switching back to Sesuai also closes the detail view (docs/receiving-simplification-prd.md §7).
        ->assertSet('activeItemIndex', null);
});

test('the product search and condition filter narrow the visible item list', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    mockMultiItemPurchaseOrder('PO-FILTER');

    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-FILTER')
        ->assertSee('Butter')
        ->assertSee('Cream')
        ->set('itemSearch', 'but')
        ->assertSee('Butter')
        ->assertDontSee('Cream')
        ->set('itemSearch', '')
        ->call('openItemDetail', 1)
        ->call('closeItemDetail')
        ->set('itemFilter', 'problem')
        ->assertDontSee('Butter')
        ->assertSee('Cream')
        ->set('itemFilter', 'ok')
        ->assertSee('Butter')
        ->assertDontSee('Cream')
        ->set('itemFilter', 'all')
        ->assertSee('Butter')
        ->assertSee('Cream');
});

test('a quick row-level ED shows a colored remaining-days badge, available on Sesuai items too', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    $this->travelTo(now()->setDate(2026, 10, 1));
    mockMultiItemPurchaseOrder('PO-ED');

    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-ED')
        ->assertSet('items.0.condition', 'ok')
        ->set('items.0.expiryDate', '2026-12-30')
        ->assertSee('90 hari lagi')
        ->set('items.1.expiryDate', '2026-09-28')
        ->assertSee('Lewat 3 hari');
});

test('a quick ED on a Sesuai item without explicit batches still saves as an expiry record', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    $this->actingAs($user);
    $this->travelTo(now()->setDate(2026, 10, 1));

    $order = [
        'purchaseNum' => 'PO-QUICK-ED',
        'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10,
        'purchaseDetails' => [['ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'qty' => 2]],
    ];
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with('PO-QUICK-ED')->twice()->andReturn($order);
    $service->shouldReceive('locations')->with(10)->once()->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    $service->shouldReceive('create')->once()->andReturn(['result' => ['goodsReceiptNum' => 'GR-QUICK-ED'], 'response' => ['code' => 'OK', 'message' => 'OK']]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-QUICK-ED')
        ->set('documentType', 'delivery_note')
        ->set('documentNumber', 'DO-QUICK-ED')
        ->set('documentDate', now()->toDateString())
        ->set('items.0.expiryDate', '2026-12-30')
        ->call('submit')
        ->assertHasNoErrors();

    $receipt = GoodsReceipt::where('reference_number', 'PO-QUICK-ED')->sole();
    $expiry = $receipt->items()->sole()->expiries()->sole();
    expect($receipt->items)->toHaveCount(1)
        ->and($expiry->expired_date->toDateString())->toBe('2026-12-30')
        ->and($expiry->batch_number)->toBeNull()
        ->and((float) $expiry->accepted_quantity)->toBe(2.0);
});

test('inventory stays expanded on receiving index and detail', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SUPERADMIN');
    $this->actingAs($user);
    $receipt = GoodsReceipt::factory()->create();

    foreach ([GoodsReceiptResource::getUrl('index'), GoodsReceiptResource::getUrl('view', ['record' => $receipt])] as $url) {
        $response = $this->get($url)->assertSuccessful();
        preg_match('/openGroups:\s*(\[[^\]]*\])/', $response->getContent(), $matches);
        expect(json_decode($matches[1] ?? '[]', true))->toBe(['inventory']);
    }
});
