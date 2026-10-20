<?php

use App\Enums\MonitoringTransactionStatus;
use App\Filament\Casual\Pages\GoodsReceiptPage;
use App\Filament\Casual\Pages\ItemJournalPage;
use App\Filament\Helpdesk\Pages\BulkDataPromotionPage;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\GoodsReceipt;
use App\Models\MonitoringTransaction;
use App\Models\QualityControlItemJournal;
use App\Models\User;
use App\Services\EsbCoreClient;
use App\Services\EsbGoodsReceiptService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    Storage::fake('b2');
    config()->set([
        'esb.core.base_url' => 'https://esb.test/core',
        'esb.core.username' => 'core-user',
        'esb.core.password' => 'core-secret',
        'esb.core.companies.BLSS' => ['username' => 'qc-user', 'password' => 'secret'],
    ]);
});

function itemJournalFakes(mixed $createResponse, mixed $attachmentResponse = null): void
{
    Http::fake(array_filter([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [['branchID' => 373, 'branchCode' => 'BLA', 'branchName' => 'Bloomery Test']]]),
        'https://esb.test/core/location*' => Http::response(['status' => 'ok', 'result' => [['locationID' => 964, 'locationName' => 'Kitchen']]]),
        'https://esb.test/core/purpose*' => Http::response(['status' => 'ok', 'result' => ['data' => [['purposeID' => 10, 'purposeName' => 'Sampling QC', 'purposeAppliedTo' => ['ITEM JOURNAL'], 'flagActive' => true]], 'next' => '']]),
        'https://esb.test/core/inventory/item-journal/*/attachment' => $attachmentResponse,
        'https://esb.test/core/inventory/item-journal' => $createResponse,
    ]));
}

function submitItemJournal(array $attachments = [])
{
    return Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('companyCode', 'BLSS')
        ->set('branchId', '373')
        ->set('productOptions', [['productDetailID' => 2112, 'productCode' => 'BB001', 'productName' => 'Whipping Cream', 'unit' => 'GR']])
        ->set('items.0.productDetailID', 2112)
        ->set('items.0.purposeID', 10)
        ->set('items.0.adjustmentMode', 'decrease')
        ->set('items.0.qty', 2)
        ->set('attachments', $attachments)
        ->call('submit');
}

function actAsQualityControl(): void
{
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->assignRole('QUALITY_CONTROL');
    test()->actingAs($user);
}

it('records an Item Journal create once even when submit is clicked twice', function () {
    actAsQualityControl();
    itemJournalFakes(Http::response(['status' => 'ok', 'result' => ['itemJournalNum' => 'IU202610200001']]));

    submitItemJournal()->call('submit');

    $journal = QualityControlItemJournal::query()->sole();
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/item-journal')))->toHaveCount(1);
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->source_module)->toBe('item_journal')
        ->and($parent->company_code)->toBe('BLSS')
        ->and($parent->external_reference)->toBe('IU202610200001')
        ->and($parent->idempotency_key)->toBe('item-journal:'.$journal->submission_key)
        ->and($parent->local_model_id)->toBe($journal->id);
});

it('keeps the domain unknown status and records unknown for a connection reset on Item Journal create', function () {
    actAsQualityControl();
    itemJournalFakes(Http::failedConnection('connection reset after send'));

    submitItemJournal();

    expect(QualityControlItemJournal::query()->sole()->status)->toBe('unknown')
        ->and(MonitoringTransaction::query()->parents()->sole()->status)->toBe(MonitoringTransactionStatus::Unknown);
});

it('records attachment upload failure as partial and appends the retry without creating a new journal', function () {
    actAsQualityControl();
    itemJournalFakes(
        Http::response(['status' => 'ok', 'result' => ['itemJournalNum' => 'IU202610200002']]),
        Http::sequence()
            ->push(['status' => 'fail', 'message' => 'File terlalu besar'], 422)
            ->push(['status' => 'ok', 'result' => ['urls' => ['https://files.test/qc.jpg?X-Amz-Signature=secret']]])
            ->push(['status' => 'ok', 'result' => null]),
    );

    $page = submitItemJournal([UploadedFile::fake()->image('qc.jpg')]);
    $journal = QualityControlItemJournal::query()->sole();
    expect($journal->status)->toBe('attachment_failed');
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Partial);

    $page->call('retryAttachments', $journal->id);
    $page->call('deleteAllAttachments', $journal->id);

    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/item-journal')))->toHaveCount(1);
    $parent->refresh();
    expect(MonitoringTransaction::query()->parents()->count())->toBe(1)
        ->and($parent->attempts->pluck('operation_type')->all())->toBe(['create_item_journal', 'upload_attachments', 'upload_attachments', 'delete_attachments'])
        ->and($parent->attempts->firstWhere('operation_type', 'delete_attachments')->method)->toBe('DELETE')
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->attempts[1]->request_summary['files'][0])->toHaveKeys(['name', 'mime', 'size'])
        ->and(json_encode($parent->attempts->map->getAttributes()))->not->toContain('X-Amz-Signature');
});

function receivingPage(mixed $createResponse): EsbGoodsReceiptService
{
    $branch = Branch::factory()->create(['name' => 'Receiving Monitoring Branch']);
    BranchEsbCode::query()->create(['branch_id' => $branch->id, 'esb_branch_id' => 10, 'esb_branch_code' => 'BLS', 'esb_comcode' => 'BLSS', 'label' => 'Receiving', 'is_active' => true]);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    test()->actingAs($user);
    test()->travelTo(now()->setDate(2026, 10, 20));

    $order = [
        'purchaseNum' => 'PO-MON', 'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED, 'branchID' => 10,
        'purchaseDetails' => [['ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'productCode' => 'BB001', 'productName' => 'Bahan Test', 'uomID' => 1, 'uomName' => 'GR', 'qty' => 2]],
    ];
    $service = Mockery::mock(EsbGoodsReceiptService::class, [app(EsbCoreClient::class)])->makePartial();
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->andReturn($order);
    $service->shouldReceive('locations')->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    app()->instance(EsbGoodsReceiptService::class, $service);
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/inventory/goods-receipt/*' => $createResponse,
    ]);

    return $service;
}

function submitReceiving(): void
{
    Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', 'PO-MON', 'BLSS')
        ->set('documentType', 'invoice')
        ->set('documentNumber', 'INV-MON')
        ->set('documentDate', '2026-10-20')
        ->call('submit')
        ->assertHasNoErrors();
}

it('maps Goods Receipt outcomes and links the receipt, Branch, and purchase number', function (mixed $response, string $domainStatus, MonitoringTransactionStatus $monitoringStatus) {
    receivingPage($response);

    submitReceiving();

    $receipt = GoodsReceipt::query()->sole();
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($receipt->status)->toBe($domainStatus)
        ->and($parent->status)->toBe($monitoringStatus)
        ->and($parent->source_module)->toBe('goods_receipt')
        ->and($parent->branch_id)->toBe(Branch::query()->where('name', 'Receiving Monitoring Branch')->value('id'))
        ->and($parent->local_model_id)->toBe($receipt->id);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/goods-receipt/')))->toHaveCount(1);
})->with([
    'success' => [fn () => Http::response(['status' => 'ok', 'result' => ['goodsReceiptNum' => 'GR-MON-1']]), GoodsReceipt::STATUS_SUCCEEDED, MonitoringTransactionStatus::Succeeded],
    'explicit rejection' => [fn () => Http::response(['status' => 'fail', 'message' => 'Qty melebihi PO'], 422), GoodsReceipt::STATUS_FAILED, MonitoringTransactionStatus::Failed],
    'connection reset' => [fn () => Http::failedConnection('connection reset after send'), GoodsReceipt::STATUS_UNKNOWN, MonitoringTransactionStatus::Unknown],
]);

function promotionResponder(Request $request, Closure $onPost)
{
    if ($request->method() === 'POST') {
        return $onPost($request);
    }
    if ($request->url() === 'https://promotion-esb.test/corev1/branch') {
        return Http::response(['code' => 200, 'data' => [['branchCode' => 'LR00', 'branchName' => 'Bloomery Pusat']]]);
    }

    return Http::response(['code' => 200, 'data' => []]);
}

function promotionPage()
{
    config(['esb.base_url' => 'https://promotion-esb.test', 'esb.tokens.BLSS' => 'blss-static-token', 'esb.tokens.BLO7' => 'blo7-static-token']);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('IT_STAFF');
    test()->actingAs($user);

    return Livewire::test(BulkDataPromotionPage::class)->fillForm([
        'target_comcodes' => ['ALL'], 'branch_ids' => ['BLSS|LR00', 'BLO7|LR00'], 'promotionMasterCode' => 'F0001', 'promotionType' => 4,
        'notes' => 'Promo Free Item', 'authorizationNeeded' => false, 'promotionDaysID' => [1, 2],
        'startDate' => '2026-10-20 07:00:00', 'endDate' => '2026-10-20 23:00:00', 'selectPromotionTime' => 'specific_time',
        'applyToAllApplication' => false, 'allCategories' => true, 'usedForLoyalty' => true, 'applyTo' => 'Staff Only',
        'applyToApplicationID' => ['pos', 'eso'], 'selfOrderPaymentMethodCode' => ['cc88'], 'maxUsage' => 1, 'maxUsageTotal' => 11,
        'promotionTime' => [['startTime' => '07:00:00', 'endTime' => '10:00:00']], 'promotionCode' => '', 'promotionDesc' => 'Free item',
        'voucherSourceName' => 'Giftee', 'minSalesPrice' => 10000, 'settingBinRequired' => true, 'bankIdentificationNumbers' => ['123456'],
        'prefixPromotion' => '12345',
    ]);
}

it('records each promotion comcode as its own attempt and keeps partial results', function () {
    Http::fake(fn (Request $request) => promotionResponder($request, fn (Request $request) => $request->hasHeader('Authorization', 'Bearer blss-static-token')
        ? Http::response(['code' => 200, 'message' => 'Save Data Successfully', 'data' => ['promotionID' => 117]])
        : Http::response(['code' => 422, 'message' => 'Promotion code exists'], 422)));

    promotionPage()->call('submit')->assertHasNoFormErrors();

    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST'))->toHaveCount(2);
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->source_module)->toBe('bulk_promotion')
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Partial)
        ->and($parent->external_reference)->toBe('F0001')
        ->and($parent->attempts->mapWithKeys(fn ($attempt) => [$attempt->company_code => [$attempt->status->value, $attempt->external_reference]])->all())
        ->toEqualCanonicalizing(['BLSS' => ['succeeded', '117'], 'BLO7' => ['failed', null]]);
});

it('stops a concurrent duplicate promotion submit before any ESB request', function () {
    Http::fake(fn (Request $request) => promotionResponder($request, fn () => Http::response(['code' => 200, 'data' => ['promotionID' => 1]])));
    $page = promotionPage();
    $instance = $page->instance();
    $data = $instance->form->getState();
    $call = fn (string $method, mixed ...$arguments): mixed => (new ReflectionMethod($instance, $method))->invoke($instance, ...$arguments);
    $key = 'bulk-promotion:'.auth()->id().':'.hash('sha256', json_encode([$call('targetsForBranches', $data['branch_ids']), $call('payload', $data)]));
    $inFlight = Cache::lock($key, 300);
    expect($inFlight->get())->toBeTrue();

    $page->call('submit')->assertNotified('Promotion sedang dikirim ke ESB');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    expect(MonitoringTransaction::query()->count())->toBe(0);
});
