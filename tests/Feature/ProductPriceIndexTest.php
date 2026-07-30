<?php

use App\Filament\Helpdesk\Pages\ProductPriceIndexDetailPage;
use App\Filament\Helpdesk\Pages\ProductPriceIndexPage;
use App\Models\EsbPurchaseOrderItem;
use App\Models\User;
use App\Services\EsbCoreService;
use App\Services\PurchaseOrderPriceSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('synchronizes PO details and calculates normalized net prices', function () {
    $esb = Mockery::mock(EsbCoreService::class);
    $esb->shouldReceive('getPurchaseOrders')->once()->andReturn([
        'page' => 1,
        'limit' => 50,
        'count' => 1,
        'data' => [[
            'purchaseNum' => 'PO-TEST-001',
            'statusID' => 8,
            'editedDate' => '2026-07-30T10:00:00+07:00',
        ]],
        'prev' => null,
        'next' => null,
    ]);
    $esb->shouldReceive('getPurchaseOrder')->once()->with('PO-TEST-001')->andReturn([
        'purchaseNum' => 'PO-TEST-001',
        'purchaseDate' => '2026-07-30T00:00:00+07:00',
        'requiredDate' => '2026-07-31T00:00:00+07:00',
        'branchID' => 1,
        'branchName' => 'Atelier',
        'supplierID' => 2,
        'supplierName' => 'Supplier A',
        'currencyID' => 1,
        'currencyName' => 'Rupiah',
        'rate' => 1,
        'purchaseTotal' => 1000000,
        'statusID' => 8,
        'statusName' => 'Finished',
        'editedDate' => '2026-07-30T10:00:00+07:00',
        'purchaseDetails' => [[
            'ID' => 101,
            'productDetailID' => 501,
            'productID' => 50,
            'productCode' => 'BBMK001',
            'productName' => 'Tepung Premium',
            'uomID' => 1,
            'uomName' => 'KG',
            'qty' => 10,
            'convertionQty' => 10,
            'stockQty' => 100,
            'pricelistPrice' => 100000,
            'price' => 100000,
            'discount' => 0,
            'discountPercent' => 0,
            'vat' => 100000,
            'total' => 1000000,
            'lastPrice' => 95000,
            'lastPriceDate' => '2026-07-01T00:00:00+07:00',
        ]],
    ]);

    $result = (new PurchaseOrderPriceSyncService($esb))->sync('2026-07-01', '2026-07-31');

    expect($result['orders'])->toBe(1)
        ->and($result['items'])->toBe(1);

    $item = EsbPurchaseOrderItem::query()->with('purchaseOrder')->firstOrFail();
    expect($item->normalizedNetPrice())->toBe(9000.0);

    Livewire::test(ProductPriceIndexPage::class)
        ->assertSee('Tepung Premium')
        ->assertSee('Supplier A');

    Livewire::test(ProductPriceIndexDetailPage::class, ['productDetail' => 501])
        ->assertSee('PO-TEST-001')
        ->assertSee('Harga Normalisasi');
});
