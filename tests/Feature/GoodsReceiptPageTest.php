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
        ->assertSee('Hanya qty Accepted yang dikirim ke ESB')
        ->assertSee('minimum 80%')
        ->assertSee('2. Quantity Check')
        ->assertSee('3. Quality Check')
        ->assertSee('Feedback Loop ke Purchasing')
        ->assertSee('Informasi Pengisian')
        ->assertSee('Informasi Proses Otomatis')
        ->assertSee('6. Konfirmasi Penerimaan')
        ->assertSee('Informasi Tutup PO')
        ->assertSee('pengiriman terakhir')
        ->assertSee('Surat Jalan Sesuai')
        ->assertSee('Sampling Test Diperlukan')
        ->assertDontSee('Suhu aktual °C')
        ->assertDontSee('Tambah Foto / Screenshot')
        ->set('items.0.temperatureCategory', 'chilled')
        ->assertSee('Suhu aktual °C')
        ->set('items.0.shelfLifeRequired', true)
        ->assertSee('Minimum sisa shelf life %')
        ->call('addBatch', 0)
        ->assertSee('Hasil Perhitungan Shelf Life')
        ->assertSee('Lengkapi Tanggal Produksi dan Kedaluwarsa')
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
        ->set('poDocumentMatch', false)
        ->assertSee('Foto bukti dokumen')
        ->set('poDocumentMatch', true)
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
    $user = User::factory()->create(['is_active' => true]);
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
        ->set('deliveryNumber', 'DO-BATCH')
        ->set('invoiceNumber', 'INV-BATCH')
        ->set('invoiceDate', '2026-09-16')
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
        ->and((float) $expiry->shelf_life_remaining_percentage)->toBe(95.89)
        ->and($expiry->qc_result)->toBe('pass');
});
