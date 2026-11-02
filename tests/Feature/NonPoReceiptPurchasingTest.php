<?php

use App\Actions\Purchasing\NonPoReceipt\CancelNonPoReceiptAction;
use App\Actions\Purchasing\NonPoReceipt\ComparePurchaseOrderAction;
use App\Actions\Purchasing\NonPoReceipt\RequestNonPoReceiptRevisionAction;
use App\Actions\Purchasing\NonPoReceipt\ResolveNonPoGoodsReceiptAction;
use App\Actions\Purchasing\NonPoReceipt\StartNonPoReceiptProcessingAction;
use App\Actions\Purchasing\NonPoReceipt\VerifyPurchaseOrderAndProcessGoodsReceiptAction;
use App\Enums\MonitoringTransactionStatus;
use App\Enums\NonPoReceiptStatus;
use App\Filament\Helpdesk\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Helpdesk\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Helpdesk\Resources\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Filament\Helpdesk\Resources\NonPoReceipts\NonPoReceiptResource;
use App\Filament\Helpdesk\Resources\NonPoReceipts\Pages\ListNonPoReceipts;
use App\Filament\Helpdesk\Resources\NonPoReceipts\Pages\ViewNonPoReceipt;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\MonitoringTransaction;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptBatch;
use App\Models\NonPoReceiptItem;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use App\Services\EsbGoodsReceiptService;
use App\Services\Monitoring\MonitoringLocalRecordLinker;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;

/** @return array<string, mixed> */
function purchaseOrder(array $overrides = [], array $lineOverrides = []): array
{
    return array_replace([
        'purchaseNum' => 'PO-1', 'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED, 'statusName' => 'Authorized',
        'branchID' => 10, 'branchName' => 'Central Kitchen', 'supplierID' => 77, 'supplierName' => 'PT Sumber Dairy', 'purchaseDate' => '2026-11-01',
        'purchaseDetails' => [array_replace([
            'ID' => 901, 'productID' => 1501, 'productDetailID' => 501, 'productCode' => 'PRD-501', 'productName' => 'Butter ESB',
            'uomID' => 3, 'uomName' => 'KG', 'qty' => 10, 'deviationVal' => 0,
        ], $lineOverrides)],
    ], $overrides);
}

/** @param array<string, mixed>|Throwable $order */
function fakePurchaseOrderEsb(array|Throwable $order): MockInterface
{
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $expectation = $service->shouldReceive('purchaseOrder')->byDefault();
    $order instanceof Throwable ? $expectation->andThrow($order) : $expectation->andReturn($order);
    app()->instance(EsbGoodsReceiptService::class, $service);

    return $service;
}

/**
 * ESB over faked HTTP with the real service, the only way the monitoring recorder observes an attempt.
 *
 * @param  Closure(): mixed  $goodsReceipt
 */
function fakeEsbHttp(Closure $goodsReceipt): void
{
    Cache::flush();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'receiving-user', 'password' => 'secret']);
    config()->set('monitoring.esb_transactions.enabled', true);
    Http::fake(function (Request $request) use ($goodsReceipt) {
        return match (true) {
            str_ends_with($request->url(), '/auth/login') => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
            str_contains($request->url(), '/purchase/purchase-order/') => Http::response(['status' => 'ok', 'result' => purchaseOrder()]),
            default => $goodsReceipt(),
        };
    });
}

function goodsReceiptResponse(string $number = 'GR-1'): array
{
    return ['result' => ['goodsReceiptNum' => $number], 'response' => ['code' => 200, 'message' => 'OK']];
}

function receiptInProgress(array $attributes = [], array $itemAttributes = []): NonPoReceipt
{
    $receipt = NonPoReceipt::factory()->poInProgress()->create(array_replace(['supplier_text' => 'PT Sumber Dairy'], $attributes));
    NonPoReceiptItem::factory()->for($receipt)->create(array_replace([
        'product_id' => 1501, 'product_detail_id' => 501, 'product_code' => 'PRD-501', 'product_name' => 'Butter', 'uom_name' => 'KG',
    ], $itemAttributes));

    return $receipt;
}

function verifyPurchaseOrder(User $actor, NonPoReceipt $receipt, string $po = 'PO-1', bool $confirmed = false): NonPoReceipt
{
    return app(VerifyPurchaseOrderAndProcessGoodsReceiptAction::class)->execute($actor, $receipt, $po, $confirmed);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->purchasing = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');
    $this->actingAs($this->purchasing);
});

it('shows the PO Susulan queue with title and pending badge, excluding drafts', function () {
    $pending = NonPoReceipt::factory()->awaitingPurchasing()->create(['supplier_text' => 'Supplier Antre']);
    NonPoReceipt::factory()->poInProgress()->create();
    $draft = NonPoReceipt::factory()->create(['supplier_text' => 'Supplier Draft']);
    NonPoReceipt::factory()->awaitingPurchasing()->create(['status' => NonPoReceiptStatus::Completed]);

    Livewire::test(ListNonPoReceipts::class)
        ->assertSee('Permintaan PO Susulan dari Receiving')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$draft]);

    expect(NonPoReceiptResource::getNavigationLabel())->toBe('PO Susulan')
        ->and(NonPoReceiptResource::getNavigationBadge())->toBe('2');

    $this->get(NonPoReceiptResource::getUrl('index'))->assertOk()->assertSee('PO Susulan');
});

it('hides PO Susulan from users without the view permission', function () {
    $receipt = NonPoReceipt::factory()->awaitingPurchasing()->create();
    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->givePermissionTo('access backoffice');
    $this->actingAs($outsider);

    $this->get(NonPoReceiptResource::getUrl('index'))->assertForbidden();
    $this->get(NonPoReceiptResource::getUrl('view', ['record' => $receipt]))->assertForbidden();
});

it('walks start, revision, and cancel through the detail page actions', function () {
    $receipt = NonPoReceipt::factory()->awaitingPurchasing()->create();
    NonPoReceiptItem::factory()->for($receipt)->create();

    Livewire::test(ViewNonPoReceipt::class, ['record' => $receipt->getRouteKey()])
        ->assertActionHidden('verifyAndProcess')
        ->assertActionHidden('cancelProcess')
        ->callAction('startProcessing');

    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoInProgress)
        ->and($receipt->processed_by)->toBe($this->purchasing->id);

    $openPage = fn () => Livewire::test(ViewNonPoReceipt::class, ['record' => $receipt->getRouteKey()]);
    $openPage()->assertActionVisible('verifyAndProcess')->callAction('requestRevision', data: ['reason' => ''])->assertHasFormErrors(['reason' => 'required']);
    $openPage()->callAction('requestRevision', data: ['reason' => 'Foto dokumen buram'])->assertHasNoFormErrors();

    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::RevisionRequested)
        ->and($receipt->statusHistories()->latest('id')->first()->notes)->toBe('Foto dokumen buram');
});

it('requires a reason and the right status for every Purchasing transition', function () {
    $receipt = NonPoReceipt::factory()->awaitingPurchasing()->create();

    expect(fn () => app(RequestNonPoReceiptRevisionAction::class)->execute($this->purchasing, $receipt, ' '))->toThrow(ValidationException::class);

    app(StartNonPoReceiptProcessingAction::class)->execute($this->purchasing, $receipt);
    expect(fn () => app(StartNonPoReceiptProcessingAction::class)->execute($this->purchasing, $receipt))->toThrow(AuthorizationException::class);

    expect($receipt->statusHistories()->where('to_status', 'po_in_progress')->count())->toBe(1);
});

it('notifies Inventory about a revision request', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $receipt = NonPoReceipt::factory()->awaitingPurchasing()->create(['created_by' => $owner->id]);

    app(RequestNonPoReceiptRevisionAction::class)->execute($this->purchasing, $receipt, 'Perbaiki supplier');

    expect($owner->notifications()->count())->toBe(1);
});

it('keeps cancellation behind its own permission on the action', function () {
    $receipt = NonPoReceipt::factory()->awaitingPurchasing()->create();

    expect(fn () => app(CancelNonPoReceiptAction::class)->execute($this->purchasing, $receipt, 'Batal'))->toThrow(AuthorizationException::class);

    $this->purchasing->givePermissionTo('cancel non po purchase order requests');
    app(CancelNonPoReceiptAction::class)->execute($this->purchasing->fresh(), $receipt, 'Retur ke vendor, BA-12');

    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::Cancelled);
    $this->assertModelExists($receipt);
});

it('blocks verification for a mismatching PO without any mutation', function (array $order, array $receiptAttributes, array $itemAttributes, string $message) {
    $service = fakePurchaseOrderEsb(purchaseOrder(...$order));
    $service->shouldNotReceive('create');
    $receipt = receiptInProgress($receiptAttributes, $itemAttributes);

    try {
        verifyPurchaseOrder($this->purchasing, $receipt);
        $this->fail('Verification should have been blocked.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain($message);
    }

    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoInProgress)
        ->and($receipt->po_number)->toBeNull()
        ->and(GoodsReceipt::query()->count())->toBe(0);
})->with([
    'status belum authorized' => [[['statusID' => 1]], [], [], 'Authorized atau Receiving'],
    'company lain' => [[['companyCode' => 'OTHER']], [], [], 'bukan BLSS'],
    'branch lain' => [[['branchID' => 99]], [], [], 'Cabang PO berbeda'],
    'produk lain' => [[[], ['productDetailID' => 999]], [], [], 'tidak ada pada PO'],
    'uom lain' => [[[], ['uomName' => 'GR']], [], [], 'tidak ada pada PO'],
    'melebihi outstanding' => [[[], ['qty' => 9]], [], [], 'melebihi outstanding'],
    'tidak ada accepted' => [[], [], ['accepted_qty' => 0, 'hold_qty' => 10], 'Tidak ada qty Accepted'],
]);

it('rejects a receipt outside BLSS through the policy', function () {
    fakePurchaseOrderEsb(purchaseOrder());
    $receipt = receiptInProgress(['company_code' => 'OTHER']);

    expect(fn () => verifyPurchaseOrder($this->purchasing, $receipt))->toThrow(AuthorizationException::class);
});

it('reports an unreadable PO as a blocker instead of mutating', function () {
    $service = fakePurchaseOrderEsb(new RuntimeException('PO tidak ditemukan'));
    $service->shouldNotReceive('create');
    $receipt = receiptInProgress();

    expect(fn () => verifyPurchaseOrder($this->purchasing, $receipt))->toThrow(ValidationException::class);
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoInProgress);
});

it('refuses a PO already linked to another receipt', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldNotReceive('create');
    NonPoReceipt::factory()->poInProgress()->create(['status' => NonPoReceiptStatus::Completed, 'po_number' => 'PO-1']);
    $receipt = receiptInProgress();

    expect(fn () => verifyPurchaseOrder($this->purchasing, $receipt))->toThrow(ValidationException::class);
});

it('counts in-flight and unknown receipts against the PO outstanding', function (string $status) {
    fakePurchaseOrderEsb(purchaseOrder());
    $receipt = receiptInProgress();
    $other = GoodsReceipt::factory()->create(['reference_number' => 'PO-1', 'status' => $status]);
    GoodsReceiptItem::factory()->for($other)->create(['product_detail_id' => 501, 'accepted_qty' => 5]);

    $comparison = app(ComparePurchaseOrderAction::class)->execute($receipt, 'PO-1');

    expect($comparison['items'][0]['outstandingQty'])->toEqual(5)
        ->and(implode(' ', $comparison['blockers']))->toContain('melebihi outstanding');
})->with([GoodsReceipt::STATUS_SUCCEEDED, GoodsReceipt::STATUS_PARTIAL_SUCCEEDED, GoodsReceipt::STATUS_PROCESSING, GoodsReceipt::STATUS_UNKNOWN]);

it('ignores failed receipts when calculating outstanding', function () {
    fakePurchaseOrderEsb(purchaseOrder());
    $other = GoodsReceipt::factory()->create(['reference_number' => 'PO-1', 'status' => GoodsReceipt::STATUS_FAILED]);
    GoodsReceiptItem::factory()->for($other)->create(['product_detail_id' => 501, 'accepted_qty' => 5]);

    expect(app(ComparePurchaseOrderAction::class)->execute(receiptInProgress(), 'PO-1')['blockers'])->toBe([]);
});

it('requires explicit confirmation for a supplier mismatch and keeps both supplier values', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->andReturn(goodsReceiptResponse());
    $receipt = receiptInProgress(['supplier_text' => 'Toko Dairy']);

    expect(fn () => verifyPurchaseOrder($this->purchasing, $receipt))->toThrow(ValidationException::class);
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoInProgress);

    $updated = verifyPurchaseOrder($this->purchasing, $receipt, confirmed: true);

    expect($updated->status)->toBe(NonPoReceiptStatus::Completed)
        ->and($updated->supplier_text)->toBe('Toko Dairy')
        ->and($updated->po_supplier_name)->toBe('PT Sumber Dairy')
        ->and($updated->supplier_mismatch_confirmed_by)->toBe($this->purchasing->id)
        ->and($updated->goodsReceipt->supplier_name)->toBe('PT Sumber Dairy');
});

it('ignores case and spacing differences in the supplier name', function () {
    fakePurchaseOrderEsb(purchaseOrder());

    expect(app(ComparePurchaseOrderAction::class)->execute(receiptInProgress(['supplier_text' => ' pt  sumber DAIRY '], []), 'PO-1')['supplierMismatch'])->toBeFalse();
});

it('creates exactly one Goods Receipt from Accepted qty and links everything', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->with('BLSS', 'PO-1', Mockery::on(function (array $payload): bool {
        expect($payload['goodsReceiptDetail'])->toHaveCount(1)
            ->and($payload['goodsReceiptDetail'][0]['productDetailID'])->toBe(501)
            ->and($payload['goodsReceiptDetail'][0]['qty'])->toBe(10.0)
            ->and($payload['goodsReceiptDetail'][0]['expiredDates'][0]['qty'])->toBe(10.0)
            ->and($payload['locationID'])->toBe(9);

        return true;
    }))->andReturn(goodsReceiptResponse());
    $receipt = receiptInProgress();
    NonPoReceiptBatch::factory()->for($receipt->items()->first(), 'item')->create();

    $updated = verifyPurchaseOrder($this->purchasing, $receipt);

    $goodsReceipt = GoodsReceipt::query()->sole();
    expect($updated->status)->toBe(NonPoReceiptStatus::Completed)
        ->and($updated->goods_receipt_id)->toBe($goodsReceipt->id)
        ->and($updated->po_number)->toBe('PO-1')
        ->and($updated->po_snapshot['supplierID'])->toBe(77)
        ->and($goodsReceipt->status)->toBe(GoodsReceipt::STATUS_SUCCEEDED)
        ->and($goodsReceipt->submission_key)->toBe($receipt->submission_key)
        ->and($goodsReceipt->esb_goods_receipt_number)->toBe('GR-1')
        ->and($goodsReceipt->reference_number)->toBe('PO-1')
        ->and($goodsReceipt->items()->sole()->purchase_detail_id)->toBe(901)
        ->and($goodsReceipt->items()->sole()->uom_id)->toBe(3)
        ->and($goodsReceipt->items()->sole()->expiries()->count())->toBe(1)
        ->and($receipt->items()->sole()->product_name)->toBe('Butter')
        ->and($receipt->statusHistories()->pluck('to_status')->map->value->all())->toBe(['po_linked', 'gr_processing', 'completed']);
});

it('does not send a second Goods Receipt on a repeated click', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->andReturn(goodsReceiptResponse());
    $receipt = receiptInProgress();

    verifyPurchaseOrder($this->purchasing, $receipt);
    $again = verifyPurchaseOrder($this->purchasing, $receipt);

    expect($again->status)->toBe(NonPoReceiptStatus::Completed)
        ->and(GoodsReceipt::query()->count())->toBe(1);
});

it('sends only Accepted qty on mixed QC and raises the vendor incident with the PO supplier', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->with('BLSS', 'PO-1', Mockery::on(fn (array $payload): bool => count($payload['goodsReceiptDetail']) === 1
        && $payload['goodsReceiptDetail'][0]['qty'] === 6.0))->andReturn(goodsReceiptResponse());
    $receipt = receiptInProgress([], [
        'physical_qty' => 10, 'accepted_qty' => 6, 'hold_qty' => 1, 'rejected_qty' => 3, 'evidence_photos' => ['non-po-receipts/items/a.jpg'],
        'qc_data' => array_replace(NonPoReceiptItem::defaultQcData(), ['condition' => 'problem', 'quarantineLocation' => 'Karantina', 'rejectionCategory' => 'quality', 'rejectionReason' => 'Rusak']),
    ]);
    NonPoReceiptItem::factory()->for($receipt)->exception('rejected_qty')->create(['product_detail_id' => 888, 'uom_name' => 'PCS', 'product_name' => 'Tidak Ada Di PO']);

    $updated = verifyPurchaseOrder($this->purchasing, $receipt);

    $goodsReceipt = GoodsReceipt::query()->sole();
    $incidents = VendorComplianceIncident::query()->get();
    expect($updated->status)->toBe(NonPoReceiptStatus::Completed)
        ->and($goodsReceipt->status)->toBe(GoodsReceipt::STATUS_PARTIAL_SUCCEEDED)
        ->and($goodsReceipt->items()->count())->toBe(2)
        ->and((float) $goodsReceipt->items()->where('product_detail_id', 501)->sole()->hold_qty)->toBe(1.0)
        ->and($goodsReceipt->items()->where('product_detail_id', 888)->sole()->purchase_detail_id)->toBeNull()
        ->and($incidents)->toHaveCount(2)
        ->and($incidents->first()->supplier_name)->toBe('PT Sumber Dairy')
        ->and($incidents->first()->supplier_id)->toBe(77);
});

it('records failed ESB rejection as gr_failed and monitors it as PO Susulan', function () {
    fakeEsbHttp(fn () => Http::response(['status' => 'error', 'message' => 'ESB menolak'], 422));
    $receipt = receiptInProgress();

    $updated = verifyPurchaseOrder($this->purchasing, $receipt);

    expect($updated->status)->toBe(NonPoReceiptStatus::GrFailed)
        ->and($updated->goodsReceipt->status)->toBe(GoodsReceipt::STATUS_FAILED)
        ->and($updated->goodsReceipt->sync_error)->toContain('ESB menolak');

    $monitoring = MonitoringTransaction::query()->whereNull('parent_id')->sole();
    expect($monitoring->source_action)->toBe('submit_non_po')
        ->and($monitoring->idempotency_key)->toBe('non-po-goods-receipt:'.$receipt->submission_key)
        ->and($monitoring->local_model_id)->toBe($updated->goods_receipt_id);
});

it('never retries a connection failure by itself', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->andThrow(new ConnectionException('timeout'));
    $owner = User::factory()->create(['is_active' => true]);
    $receipt = receiptInProgress(['created_by' => $owner->id]);

    $updated = verifyPurchaseOrder($this->purchasing, $receipt);
    $again = verifyPurchaseOrder($this->purchasing, $receipt);

    expect($updated->status)->toBe(NonPoReceiptStatus::GrUnknown)
        ->and($again->status)->toBe(NonPoReceiptStatus::GrUnknown)
        ->and($updated->goodsReceipt->status)->toBe(GoodsReceipt::STATUS_UNKNOWN)
        ->and($owner->notifications()->count())->toBe(1);
});

it('rolls back the link-to-GR step when the local Goods Receipt cannot be written', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->andReturn(goodsReceiptResponse());
    $receipt = receiptInProgress();
    GoodsReceipt::creating(function (): void {
        throw new RuntimeException('database down');
    });

    expect(fn () => verifyPurchaseOrder($this->purchasing, $receipt))->toThrow(RuntimeException::class);
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoLinked)
        ->and($receipt->goods_receipt_id)->toBeNull();

    GoodsReceipt::flushEventListeners();

    expect(verifyPurchaseOrder($this->purchasing, $receipt)->status)->toBe(NonPoReceiptStatus::Completed);
});

it('sends a linked receipt back to Purchasing when the PO stops verifying', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder(['statusID' => 5]));
    $service->shouldNotReceive('create');
    $receipt = receiptInProgress(['status' => NonPoReceiptStatus::PoLinked, 'po_number' => 'PO-1']);

    expect(fn () => verifyPurchaseOrder($this->purchasing, $receipt))->toThrow(ValidationException::class);
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoInProgress)->and($receipt->po_number)->toBeNull();
});

it('does not let a linked receipt switch to a different PO', function () {
    fakePurchaseOrderEsb(purchaseOrder());
    $receipt = receiptInProgress(['status' => NonPoReceiptStatus::PoLinked, 'po_number' => 'PO-1']);

    expect(fn () => verifyPurchaseOrder($this->purchasing, $receipt, 'PO-2'))->toThrow(ValidationException::class);
});

it('reconciles an unknown Goods Receipt by linking the number found in ESB', function () {
    fakeEsbHttp(fn () => Http::failedConnection('timeout'));
    $receipt = receiptInProgress();
    expect(verifyPurchaseOrder($this->purchasing, $receipt)->status)->toBe(NonPoReceiptStatus::GrUnknown);

    $updated = app(ResolveNonPoGoodsReceiptAction::class)->linkExisting($this->purchasing, $receipt, 'GR-99', 'Ditemukan di ESB');

    expect($updated->status)->toBe(NonPoReceiptStatus::Completed)
        ->and($updated->goodsReceipt->esb_goods_receipt_number)->toBe('GR-99')
        ->and($updated->goodsReceipt->status)->toBe(GoodsReceipt::STATUS_SUCCEEDED)
        ->and(MonitoringTransaction::query()->whereNull('parent_id')->sole()->status)->toBe(MonitoringTransactionStatus::Reconciled);
});

it('re-sends only on explicit request with a reason and reuses the same Goods Receipt', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->andThrow(new RuntimeException('ESB menolak'));
    $receipt = receiptInProgress();
    verifyPurchaseOrder($this->purchasing, $receipt);

    expect(fn () => app(ResolveNonPoGoodsReceiptAction::class)->resend($this->purchasing, $receipt, ''))->toThrow(ValidationException::class);

    $service->shouldReceive('create')->once()->andReturn(goodsReceiptResponse('GR-2'));
    $updated = app(ResolveNonPoGoodsReceiptAction::class)->resend($this->purchasing, $receipt, 'Data ESB sudah diperbaiki');

    expect($updated->status)->toBe(NonPoReceiptStatus::Completed)
        ->and(GoodsReceipt::query()->sole()->esb_goods_receipt_number)->toBe('GR-2')
        ->and($receipt->statusHistories()->where('notes', 'Data ESB sudah diperbaiki')->exists())->toBeTrue();
});

it('re-checks the PO before an explicit re-send', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->andThrow(new RuntimeException('ESB menolak'));
    $receipt = receiptInProgress();
    verifyPurchaseOrder($this->purchasing, $receipt);

    $service->shouldReceive('purchaseOrder')->andReturn(purchaseOrder(['statusID' => 5]));

    expect(fn () => app(ResolveNonPoGoodsReceiptAction::class)->resend($this->purchasing, $receipt, 'Coba lagi'))->toThrow(ValidationException::class);
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::GrFailed);
});

it('frees the PO when an unknown Goods Receipt is cancelled as confirmed absent', function () {
    fakeEsbHttp(fn () => Http::failedConnection('timeout'));
    $this->purchasing->givePermissionTo('cancel non po purchase order requests');
    $receipt = receiptInProgress();
    expect(verifyPurchaseOrder($this->purchasing->fresh(), $receipt)->status)->toBe(NonPoReceiptStatus::GrUnknown);

    $updated = app(CancelNonPoReceiptAction::class)->execute($this->purchasing->fresh(), $receipt, 'GR tidak ada di ESB');

    expect($updated->status)->toBe(NonPoReceiptStatus::Cancelled)
        ->and($updated->po_number)->toBeNull()
        ->and($updated->goodsReceipt->status)->toBe(GoodsReceipt::STATUS_FAILED)
        ->and(MonitoringTransaction::query()->whereNull('parent_id')->sole()->reconciliation_outcome)->toBe('confirmed_not_applied');

    $next = receiptInProgress();
    expect(app(ComparePurchaseOrderAction::class)->execute($next, 'PO-1')['blockers'])->toBe([]);
});

it('compares from the page without any mutation, then verifies through the modal', function () {
    $service = fakePurchaseOrderEsb(purchaseOrder());
    $service->shouldReceive('create')->once()->andReturn(goodsReceiptResponse());
    $receipt = receiptInProgress(['supplier_text' => 'Toko Dairy']);

    $page = Livewire::test(ViewNonPoReceipt::class, ['record' => $receipt->getRouteKey()])
        ->callAction('checkPurchaseOrder', data: ['po_number' => 'PO-1'])
        ->assertSee('Perbandingan Dengan PO PO-1')
        ->assertSee('Supplier berbeda');

    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoInProgress)
        ->and($page->get('poComparison'))->not->toHaveKey('order');

    $openPage = fn () => Livewire::test(ViewNonPoReceipt::class, ['record' => $receipt->getRouteKey()]);
    $openPage()->callAction('verifyAndProcess', data: ['po_number' => 'PO-1', 'supplier_mismatch_confirmed' => false])
        ->assertNotified('Verifikasi tidak dapat dilanjutkan');
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::PoInProgress);

    $openPage()->callAction('verifyAndProcess', data: ['po_number' => 'PO-1', 'supplier_mismatch_confirmed' => true])->assertHasNoFormErrors();
    expect($receipt->refresh()->status)->toBe(NonPoReceiptStatus::Completed);
});

it('shows source and link on the final Goods Receipt, and PO Normal for everything else', function () {
    $this->purchasing->givePermissionTo('view goods receipts');
    $this->actingAs($this->purchasing->fresh());
    $normal = GoodsReceipt::factory()->create(['local_branch_id' => null]);
    $sourced = GoodsReceipt::factory()->create(['local_branch_id' => null]);
    $receipt = NonPoReceipt::factory()->awaitingPurchasing()->create(['status' => NonPoReceiptStatus::Completed, 'goods_receipt_id' => $sourced->id, 'po_number' => 'PO-9']);

    expect($normal->sourceLabel())->toBe('PO Normal')->and($sourced->refresh()->sourceLabel())->toBe('PO Susulan');

    Livewire::test(ListGoodsReceipts::class)
        ->assertSee('PO Susulan')
        ->assertSee('PO Normal')
        ->filterTable('source', 'non_po')
        ->assertCanSeeTableRecords([$sourced])
        ->assertCanNotSeeTableRecords([$normal]);

    Livewire::test(ViewGoodsReceipt::class, ['record' => $sourced->getRouteKey()])
        ->assertSee($receipt->receipt_number)
        ->assertSeeHtml(NonPoReceiptResource::getUrl('view', ['record' => $receipt]));
});

it('keeps awaiting-PO receipts out of the final Goods Receipt list', function () {
    $this->purchasing->givePermissionTo('view goods receipts');
    $this->actingAs($this->purchasing->fresh());
    NonPoReceipt::factory()->awaitingPurchasing()->create(['receipt_number' => 'NPR-ANTRE']);

    Livewire::test(ListGoodsReceipts::class)->assertDontSee('NPR-ANTRE');
    expect(GoodsReceiptResource::getEloquentQuery()->count())->toBe(0);
});

it('labels the monitoring record of a PO Susulan Goods Receipt', function () {
    $goodsReceipt = GoodsReceipt::factory()->create(['reference_number' => 'PO-1']);
    NonPoReceipt::factory()->create(['goods_receipt_id' => $goodsReceipt->id, 'receipt_number' => 'NPR-7']);
    $transaction = new MonitoringTransaction;
    $transaction->local_model_type = $goodsReceipt->getMorphClass();
    $transaction->local_model_id = $goodsReceipt->id;

    expect(app(MonitoringLocalRecordLinker::class)->resolve($transaction)['label'])->toBe('Penerimaan PO PO-1 (PO Susulan NPR-7)');
});

it('grants default roles exactly the PRD permissions', function () {
    $inventory = User::factory()->create(['is_active' => true]);
    $inventory->assignRole('INVENTORY_STAFF');

    expect($inventory->can('create non po receipts'))->toBeTrue()
        ->and($inventory->can('submit non po receipts'))->toBeTrue()
        ->and($inventory->can('process non po purchase order requests'))->toBeFalse()
        ->and($this->purchasing->can('view non po purchase order requests'))->toBeTrue()
        ->and($this->purchasing->can('process non po purchase order requests'))->toBeTrue()
        ->and($this->purchasing->can('cancel non po purchase order requests'))->toBeFalse()
        ->and($this->purchasing->can('create non po receipts'))->toBeFalse();
});
