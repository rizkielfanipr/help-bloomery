<?php

use App\Actions\Inventory\Receiving\CreateGoodsReceiptAction;
use App\Enums\DeliveryAssessmentStatus;
use App\Enums\VendorComplianceLiabilityStatus;
use App\Filament\Casual\Pages\GoodsReceiptPage;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use App\Services\EsbGoodsReceiptService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    $branch = Branch::factory()->create();
    BranchEsbCode::query()->create(['branch_id' => $branch->id, 'esb_branch_id' => 10, 'esb_branch_code' => 'BLS', 'esb_comcode' => 'BLSS', 'label' => 'Gudang', 'is_active' => true]);
    $this->reviewer = User::factory()->create(['is_active' => true]);
    $this->reviewer->givePermissionTo(['view vendor compliance incidents', 'edit vendor compliance incidents']);
});

function ldOrder(string $purchaseNumber, mixed $requiredDate, array $overrides = []): array
{
    return array_replace([
        'purchaseNum' => $purchaseNumber, 'statusID' => EsbGoodsReceiptService::PURCHASE_ORDER_STATUS_AUTHORIZED,
        'branchID' => 10, 'branchCode' => 'BLS', 'branchName' => 'ESB BLS', 'supplierID' => 77, 'supplierName' => 'Supplier Terlambat',
        'purchaseDate' => '2026-10-01', 'requiredDate' => $requiredDate,
        'purchaseDetails' => [[
            'ID' => 11, 'productID' => 12, 'productDetailID' => 13, 'productCode' => 'BB001',
            'productName' => 'Bahan Test', 'uomID' => 1, 'uomName' => 'GR', 'qty' => 2,
        ]],
    ], $overrides);
}

/** @param  list<array<string, mixed>>  $refetched  orders returned on later purchaseOrder() calls */
function ldMockEsb(array $order, array $refetched = []): MockInterface
{
    $service = Mockery::mock(EsbGoodsReceiptService::class);
    $service->shouldReceive('purchaseOrders')->andReturn([]);
    $service->shouldReceive('purchaseOrder')->with('BLSS', $order['purchaseNum'])->andReturn($order, ...($refetched ?: [$order]));
    $service->shouldReceive('locations')->andReturn([['locationID' => 9, 'locationName' => 'Warehouse']]);
    app()->instance(EsbGoodsReceiptService::class, $service);

    return $service;
}

function ldReceivingUser(): User
{
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app goods receipt');
    test()->actingAs($user);

    return $user;
}

function ldOpenForm(string $purchaseNumber, ?string $receiptDate = null): Testable
{
    return Livewire::test(GoodsReceiptPage::class)
        ->call('selectPurchaseOrder', $purchaseNumber, 'BLSS')
        ->set('goodsReceiptDate', $receiptDate ?? now()->toDateString())
        ->set('documentType', 'delivery_note')
        ->set('documentNumber', "DO-{$purchaseNumber}")
        ->set('documentDate', now()->toDateString());
}

/** @return array<string, mixed> */
function ldItem(array $overrides = []): array
{
    return array_replace([
        'purchaseDetailID' => 11, 'productID' => 12, 'productDetailID' => 13, 'productCode' => 'BB001', 'productName' => 'Bahan Test',
        'uomID' => 1, 'uomName' => 'GR', 'orderedQty' => 2, 'outstandingQty' => 2, 'physicalQty' => 2, 'acceptedQty' => 2, 'holdQty' => 0, 'rejectedQty' => 0,
        'deviationVal' => 0, 'measurementMethod' => 'count', 'variancePercentage' => 0, 'tolerancePercentage' => 0, 'quantityResult' => 'pass',
        'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass', 'temperatureCategory' => 'ambient',
        'actualTemperature' => null, 'minTemperature' => null, 'maxTemperature' => null, 'coldChainResult' => 'not_applicable', 'shelfLifeRequired' => false,
        'minimumShelfLifePercentage' => 80, 'shelfLifeResult' => 'not_applicable', 'samplingRequired' => false, 'samplingMethod' => '', 'samplingResult' => 'pass',
        'samplingNotes' => '', 'quarantineLocation' => '', 'rejectionCategory' => '', 'rejectionReason' => '', 'storedEvidencePhotos' => [], 'notes' => '',
        'batches' => [], 'expiryDate' => '',
    ], $overrides);
}

/** Records a receipt through the shared write boundary, the way both callers do. */
function ldRecord(string $purchaseNumber, mixed $requiredDate, string $receiptDate, array $overrides = [], array $items = []): GoodsReceipt
{
    return app(CreateGoodsReceiptAction::class)->record(array_replace([
        'source' => GoodsReceipt::SOURCE_PURCHASE_ORDER, 'poRequiredDate' => $requiredDate,
        'submissionKey' => (string) Str::uuid(), 'companyCode' => 'BLSS', 'purchaseNumber' => $purchaseNumber, 'purchaseDate' => '2026-10-01',
        'goodsReceiptDate' => $receiptDate, 'esbBranchId' => 10, 'localBranchId' => null, 'branchName' => 'ESB BLS',
        'supplierId' => 77, 'supplierName' => 'Supplier Terlambat', 'location' => ['locationID' => 9, 'locationName' => 'Warehouse'],
        'documentType' => 'delivery_note', 'documentNumber' => 'DO-1', 'documentDate' => $receiptDate, 'documentPhotos' => [], 'goodsPhotos' => [],
        'poDocumentMatch' => true, 'documentMatch' => true, 'priceMatch' => true, 'documentNotes' => null, 'additionalInfo' => null,
    ], $overrides), collect($items ?: [ldItem()]), User::factory()->create()->id);
}

it('assesses a late PO receipt from the re-fetched required date and creates one pending candidate with 0 points', function () {
    ldReceivingUser();
    $requiredDate = now()->subDays(3)->toDateString();
    $service = ldMockEsb(ldOrder('PO-LATE', now()->addDays(5)->toDateString()), [ldOrder('PO-LATE', $requiredDate.'T00:00:00+07:00')]);
    $service->shouldReceive('create')->once()->with('BLSS', 'PO-LATE', Mockery::on(
        fn (array $payload): bool => array_keys($payload) === ['goodsReceiptDate', 'locationID', 'deliveryNum', 'additionalInfo', 'selectedAssetID', 'autoClosePO', 'goodsReceiptDetail'],
    ))->andReturn(['result' => ['goodsReceiptNum' => 'GR-LATE'], 'response' => ['code' => 'OK', 'message' => 'OK']]);

    ldOpenForm('PO-LATE')->call('submit')->assertHasNoErrors()->assertNotified('Penerimaan berhasil');

    $receipt = GoodsReceipt::query()->where('reference_number', 'PO-LATE')->sole();
    $incident = VendorComplianceIncident::query()->sole();
    expect($receipt->status)->toBe(GoodsReceipt::STATUS_SUCCEEDED)
        ->and($receipt->delivery_assessment_status)->toBe(DeliveryAssessmentStatus::Late)
        ->and($receipt->po_required_date->toDateString())->toBe($requiredDate)
        ->and($receipt->delivery_delay_days)->toBe(3)
        ->and($incident->category)->toBe('late_delivery')
        ->and($incident->goods_receipt_id)->toBe($receipt->id)
        ->and($incident->goods_receipt_item_id)->toBeNull()
        ->and($incident->affected_quantity)->toBeNull()
        ->and($incident->liability_status)->toBe(VendorComplianceLiabilityStatus::Pending)
        ->and($incident->demerit_points)->toBe(0)
        ->and($incident->delay_days)->toBe(3)
        ->and($incident->source_key)->toBe('late_delivery:BLSS:PO-LATE')
        ->and($incident->events()->sole()->event)->toBe('candidate_created')
        ->and($this->reviewer->notifications()->sole()->data['title'])->toBe('Keterlambatan delivery perlu diverifikasi');
});

it('does not create a candidate for an on-time receipt', function () {
    ldReceivingUser();
    ldMockEsb(ldOrder('PO-ONTIME', now()->toDateString()))->shouldReceive('create')->andReturn(['result' => ['goodsReceiptNum' => 'GR-1'], 'response' => ['code' => 'OK', 'message' => 'OK']]);

    ldOpenForm('PO-ONTIME')->call('submit')->assertHasNoErrors();

    $receipt = GoodsReceipt::query()->sole();
    expect($receipt->delivery_assessment_status)->toBe(DeliveryAssessmentStatus::OnTime)
        ->and($receipt->delivery_delay_days)->toBe(0)
        ->and(VendorComplianceIncident::query()->count())->toBe(0)
        ->and($this->reviewer->notifications()->count())->toBe(0);
});

it('keeps Receiving going and marks the receipt not assessable when the required date is empty or invalid', function (mixed $requiredDate, string $note) {
    ldReceivingUser();
    ldMockEsb(ldOrder('PO-NODATE', $requiredDate))->shouldReceive('create')->once()->andReturn(['result' => ['goodsReceiptNum' => 'GR-2'], 'response' => ['code' => 'OK', 'message' => 'OK']]);

    ldOpenForm('PO-NODATE')->call('submit')->assertHasNoErrors()->assertNotified('Penerimaan berhasil');

    $receipt = GoodsReceipt::query()->sole();
    expect($receipt->delivery_assessment_status)->toBe(DeliveryAssessmentStatus::NotAssessable)
        ->and($receipt->po_required_date)->toBeNull()
        ->and($receipt->delivery_delay_days)->toBeNull()
        ->and($receipt->delivery_assessment_note)->toBe($note)
        ->and(VendorComplianceIncident::query()->count())->toBe(0);
})->with([
    'missing' => [null, 'Tanggal dibutuhkan PO kosong.'],
    'invalid' => ['31/10/2026', 'Tanggal dibutuhkan PO tidak valid.'],
]);

it('keeps the candidate when the Goods Receipt fails or is unknown at ESB', function (string $status) {
    ldReceivingUser();
    ldMockEsb(ldOrder('PO-ESB', now()->subDays(2)->toDateString()))->shouldReceive('create')->once()
        ->andThrow($status === GoodsReceipt::STATUS_UNKNOWN ? new ConnectionException('timeout') : new RuntimeException('ESB menolak'));

    ldOpenForm('PO-ESB')->call('submit')->assertNotified('Penerimaan gagal dikirim');

    expect(GoodsReceipt::query()->sole()->status)->toBe($status)
        ->and(VendorComplianceIncident::query()->sole()->delay_days)->toBe(2);
})->with([
    'rejected by ESB' => [GoodsReceipt::STATUS_FAILED],
    'connection lost' => [GoodsReceipt::STATUS_UNKNOWN],
]);

it('lets a rejection incident and a late delivery candidate coexist on an all-rejected receipt', function () {
    ldReceivingUser();
    ldMockEsb(ldOrder('PO-REJ', now()->subDays(8)->toDateString()))->shouldNotReceive('create');

    ldOpenForm('PO-REJ')
        ->call('openItemDetail', 0)
        ->set('items.0.acceptedQty', 0)
        ->set('items.0.rejectedQty', 2)
        ->set('items.0.packagingResult', 'fail')
        ->set('items.0.quarantineLocation', 'Rak Karantina')
        ->set('items.0.rejectionCategory', 'packaging')
        ->set('items.0.rejectionReason', 'Kemasan sobek')
        ->set('items.0.evidencePhotos', [UploadedFile::fake()->image('kemasan.jpg')])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertNotified('QC tersimpan');

    $receipt = GoodsReceipt::query()->sole();
    $item = $receipt->items()->sole();
    expect($receipt->status)->toBe(GoodsReceipt::STATUS_QC_REJECTED)
        ->and($receipt->vendorComplianceIncidents()->pluck('category')->sort()->values()->all())->toBe(['late_delivery', 'packaging'])
        ->and($item->vendorComplianceIncidents()->sole()->category)->toBe('packaging')
        ->and($receipt->vendorComplianceIncidents()->where('category', 'late_delivery')->sole()->demerit_points)->toBe(0);
});

it('never assesses PO Susulan even when its dates would be late', function () {
    $receipt = ldRecord('PO-SUSULAN', now()->subDays(10)->toDateString(), now()->toDateString(), ['source' => GoodsReceipt::SOURCE_NON_PO]);

    expect($receipt->delivery_assessment_status)->toBe(DeliveryAssessmentStatus::NotApplicable)
        ->and($receipt->po_required_date)->toBeNull()
        ->and(VendorComplianceIncident::query()->count())->toBe(0);
});

it('refuses a receipt without an explicit source', function () {
    expect(fn () => ldRecord('PO-NOSRC', null, now()->toDateString(), ['source' => null]))->toThrow(InvalidArgumentException::class);
    expect(GoodsReceipt::query()->count())->toBe(0);
});

it('keeps one candidate per company and PO across partial receipts and updates the maximum delay', function () {
    $required = '2026-10-10';
    ldRecord('po-partial ', $required, '2026-10-12');
    ldRecord('PO-PARTIAL', $required, '2026-10-15');
    ldRecord('PO-PARTIAL', $required, '2026-10-11');

    $incident = VendorComplianceIncident::query()->sole();
    expect($incident->source_key)->toBe('late_delivery:BLSS:PO-PARTIAL')
        ->and($incident->delay_days)->toBe(5)
        ->and($incident->severity)->toBe('medium')
        ->and($incident->first_late_receipt_date->toDateString())->toBe('2026-10-12')
        ->and($incident->latest_late_receipt_date->toDateString())->toBe('2026-10-15')
        ->and($incident->demerit_points)->toBe(0)
        ->and($incident->events()->pluck('event')->all())->toBe(['candidate_created', 'delay_updated', 'delay_updated']);
});

it('does not duplicate the candidate for a repeated submission key', function () {
    $key = (string) Str::uuid();
    ldRecord('PO-DUP', '2026-10-10', '2026-10-12', ['submissionKey' => $key]);
    ldRecord('PO-DUP', '2026-10-10', '2026-10-12', ['submissionKey' => $key]);

    expect(GoodsReceipt::query()->count())->toBe(1)
        ->and(VendorComplianceIncident::query()->count())->toBe(1)
        ->and(VendorComplianceIncident::query()->sole()->events()->count())->toBe(1);
});

it('does not overwrite a decided candidate when a later partial receipt is later still', function () {
    ldRecord('PO-DECIDED', '2026-10-10', '2026-10-12');
    VendorComplianceIncident::query()->sole()->forceFill(['liability_status' => VendorComplianceLiabilityStatus::Confirmed, 'demerit_points' => 5, 'severity' => 'low'])->save();

    ldRecord('PO-DECIDED', '2026-10-10', '2026-10-25');

    $incident = VendorComplianceIncident::query()->sole();
    expect($incident->delay_days)->toBe(2)
        ->and($incident->demerit_points)->toBe(5)
        ->and($incident->severity)->toBe('low')
        ->and($incident->latest_late_receipt_date->toDateString())->toBe('2026-10-25')
        ->and($incident->events()->reorder('id', 'desc')->first()->metadata['delay_days'])->toBe(15);
});

it('reuses an incident another process created first for the same PO', function () {
    VendorComplianceIncident::factory()->lateDelivery(1, 'PO-RACE')->create(['delay_days' => 1]);

    ldRecord('PO-RACE', '2026-10-10', '2026-10-14');

    expect(VendorComplianceIncident::query()->count())->toBe(1)
        ->and(VendorComplianceIncident::query()->sole()->delay_days)->toBe(4);
});

it('shows a non-blocking late warning on the Receiving form and a neutral state without a date', function () {
    ldReceivingUser();
    ldMockEsb(ldOrder('PO-WARN', now()->subDays(4)->toDateString()))->shouldReceive('purchaseOrder')->with('BLSS', 'PO-BAD')->andReturn(ldOrder('PO-BAD', 'bukan tanggal'));

    ldOpenForm('PO-WARN')
        ->assertSee('Terdeteksi terlambat 4 hari')
        ->assertSee('akan diverifikasi Purchasing')
        ->assertDontSee('vendor bersalah')
        ->set('goodsReceiptDate', now()->subDays(4)->toDateString())
        ->assertDontSee('Terdeteksi terlambat');

    ldOpenForm('PO-BAD')
        ->assertSee('Tidak Dapat Dinilai')
        ->assertSee('ketepatan waktu pengiriman tidak dapat dinilai')
        ->assertDontSee('Terdeteksi terlambat');
});
