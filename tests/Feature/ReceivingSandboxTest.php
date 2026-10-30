<?php

use App\Filament\Casual\Pages\GoodsReceiptPage;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\EsbGoodsReceiptService;
use App\Services\SandboxEsbGoodsReceiptService;
use Database\Seeders\ReceivingSandboxSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
});

function enableLocalReceivingSandbox(): void
{
    app()->detectEnvironment(fn (): string => 'local');
    config(['esb.receiving_sandbox' => true]);
    (new AppServiceProvider(app()))->register();
}

it('never swaps the ESB Receiving service outside the local environment', function () {
    config(['esb.receiving_sandbox' => true]);
    (new AppServiceProvider(app()))->register();

    expect(app(EsbGoodsReceiptService::class))->not->toBeInstanceOf(SandboxEsbGoodsReceiptService::class)
        ->and(config('filesystems.disks.b2.driver'))->toBe('s3');
});

it('serves dummy ESB Receiving data and a local photo disk only when the local sandbox is enabled', function () {
    enableLocalReceivingSandbox();

    expect(app(EsbGoodsReceiptService::class))->toBeInstanceOf(SandboxEsbGoodsReceiptService::class)
        ->and(config('filesystems.disks.b2.driver'))->toBe('local')
        // Served local disks need a unique URL or the application fails to boot.
        ->and(parse_url(config('filesystems.disks.b2.url'), PHP_URL_PATH))->toBe('/receiving-sandbox-files')
        ->and(config('filesystems.disks.b2.url'))->not->toBe(config('filesystems.disks.local.url'));
});

it('lists receivable BLSS dummy purchase orders with unique products per order', function () {
    $sandbox = new SandboxEsbGoodsReceiptService;
    $authorized = $sandbox->purchaseOrders('BLSS', ['statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED]);
    $receiving = $sandbox->purchaseOrders('BLSS', ['statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_RECEIVING]);

    expect(array_merge($authorized, $receiving))->toHaveCount(7)
        ->and($sandbox->purchaseOrders('SPN', []))->toBe([])
        ->and($authorized[0])->not->toHaveKey('purchaseDetails');

    foreach (SandboxEsbGoodsReceiptService::purchaseOrderDefinitions() as $order) {
        $details = collect($order['purchaseDetails']);
        expect($details->pluck('productDetailID')->unique())->toHaveCount($details->count())
            ->and($sandbox->locations('BLSS', $order['branchID']))->not->toBeEmpty();
    }
});

it('simulates changed context and ESB failures for the scenario purchase orders', function () {
    $sandbox = new SandboxEsbGoodsReceiptService;
    $sandbox->purchaseOrders('BLSS', []);

    expect($sandbox->purchaseOrder('BLSS', 'PO-DUMMY-MOVED')['branchID'])->toBe(9101)
        ->and($sandbox->purchaseOrder('BLSS', 'PO-DUMMY-MOVED')['branchID'])->toBe(9102)
        ->and($sandbox->purchaseOrder('BLSS', 'PO-DUMMY-CLOSED')['statusID'])->toBe(EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_RECEIVING)
        ->and($sandbox->purchaseOrder('BLSS', 'PO-DUMMY-CLOSED')['statusID'])->toBe(5)
        ->and(fn () => $sandbox->create('BLSS', 'PO-DUMMY-FAIL', []))->toThrow(RuntimeException::class, 'ESB menolak')
        ->and(fn () => $sandbox->create('BLSS', 'PO-DUMMY-TIMEOUT', []))->toThrow(ConnectionException::class)
        ->and($sandbox->create('BLSS', 'PO-DUMMY-001', [])['result']['goodsReceiptNum'])->toStartWith('GR-DUMMY-');

    $sandbox->purchaseOrders('BLSS', []);

    expect($sandbox->purchaseOrder('BLSS', 'PO-DUMMY-MOVED')['branchID'])->toBe(9101);
});

it('skips the sandbox seeder outside the local environment', function () {
    $this->seed(ReceivingSandboxSeeder::class);

    expect(User::query()->where('username', 'receiving.pusat')->exists())->toBeFalse();
});

it('receives dummy purchase orders end to end without any ESB request and resets on reseed', function () {
    Http::preventStrayRequests();
    enableLocalReceivingSandbox();
    $this->seed(ReceivingSandboxSeeder::class);
    $this->seed(ReceivingSandboxSeeder::class);
    $receiver = User::query()->where('username', 'receiving.pusat')->sole();
    $mappedBranch = Branch::query()->where('name', 'Sandbox Cabang Termapping')->sole();
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($receiver);

    expect($receiver->canAccessBranch($mappedBranch->id))->toBeFalse()
        ->and(GoodsReceipt::query()->where('reference_number', 'like', 'PO-DUMMY-HIST-%')->count())->toBe(2);

    $component = Livewire::test(GoodsReceiptPage::class)->assertSee('PO-DUMMY-001')->assertSee('PO-DUMMY-CLOSED');
    foreach (['PO-DUMMY-001', 'PO-DUMMY-003'] as $purchaseNumber) {
        $component->call('selectPurchaseOrder', $purchaseNumber, 'BLSS')
            ->set('locationId', (string) data_get($component->get('locations'), '0.locationID'))
            ->set('documentNumber', "DO-{$purchaseNumber}")
            ->set('documentDate', now()->toDateString())
            ->call('submit')
            ->assertHasNoErrors();
    }

    $receipts = GoodsReceipt::query()->whereIn('reference_number', ['PO-DUMMY-001', 'PO-DUMMY-003'])->orderBy('reference_number')->get();
    expect($receipts->pluck('status')->unique()->all())->toBe([GoodsReceipt::STATUS_SUCCEEDED])
        ->and($receipts->pluck('local_branch_id')->all())->toBe([null, $mappedBranch->id])
        ->and($receipts->first()->esb_code)->toBe('SANDBOX');

    $this->seed(ReceivingSandboxSeeder::class);

    expect(GoodsReceipt::query()->whereIn('reference_number', ['PO-DUMMY-001', 'PO-DUMMY-003'])->exists())->toBeFalse();
});
