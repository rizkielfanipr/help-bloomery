<?php

use App\Actions\Purchasing\MaterialSourcingRequest\CancelMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\ClaimMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\ConvertMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\DecideMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\ResubmitMaterialSourcingRequestAction;
use App\Actions\Purchasing\MaterialSourcingRequest\SubmitMaterialSourcingRequestAction;
use App\Enums\MaterialSourcingRequestStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\Branch;
use App\Models\MaterialSourcingRequest;
use App\Models\MaterialSourcingRequestStatusHistory;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    config()->set('esb.master_product.base_url', 'https://master-product.test');
    config()->set('esb.master_product.token', 'master-token');
    Cache::put('esb.master_product.active_details.501', [9001], now()->addHour());
    Http::preventStrayRequests();
    fakeMsrMasterProduct();

    $this->branch = Branch::factory()->create(['name' => 'Outlet Senopati']);
    $this->requester = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $this->requester->givePermissionTo('access employee app sourcing request');
    $this->processor = User::factory()->create(['is_active' => true]);
    $this->processor->givePermissionTo(['view material sourcing requests', 'process material sourcing requests', 'submit material sourcing', 'view material sourcings']);
});

function fakeMsrMasterProduct(string $productName = 'Matcha Uji Jepang'): void
{
    Http::fake([
        'https://master-product.test/corev1/master/product*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 20, 'count' => 1, 'next' => '', 'prev' => '',
            'data' => [[
                'productID' => 501,
                'productCode' => 'BB-MATCHA',
                'productName' => $productName,
                'categoryName' => 'Bahan Baku',
                'subCategoryName' => 'Minuman',
                'productDetails' => [['productDetailID' => 9001, 'unit' => 'KG', 'conversionFactor' => 1, 'flagActive' => 'true']],
            ]],
        ]]),
    ]);
}

function msrInput(array $overrides = []): array
{
    return array_merge([
        'submissionKey' => (string) Str::uuid(),
        'productCode' => 'BB-MATCHA',
        'productDetailId' => 9001,
        'reason' => 'Supplier lama berhenti produksi matcha.',
        'estimatedQuantity' => '12.5',
        'targetDate' => now()->addWeek()->toDateString(),
        'notes' => 'Butuh grade ceremonial.',
        'attachments' => [],
    ], $overrides);
}

function msrSupplier(string $name = 'PT Matcha Nusantara', float $price = 450000): array
{
    return ['supplier_name' => $name, 'brand' => null, 'price' => $price, 'moq' => '5 kg', 'lead_time_days' => 7, 'contact_name' => 'Rina', 'contact_phone' => '0812', 'notes' => null, 'attachment_path' => null];
}

function msrSubmit(User $actor, array $overrides = []): MaterialSourcingRequest
{
    return app(SubmitMaterialSourcingRequestAction::class)->execute($actor, msrInput($overrides));
}

it('submits one product with a server-side ESB snapshot, request number, history, and private attachments', function () {
    Notification::fake();
    $file = UploadedFile::fake()->create('penawaran.pdf', 200, 'application/pdf');

    $request = msrSubmit($this->requester, ['attachments' => [$file]]);

    expect($request->status)->toBe(MaterialSourcingRequestStatus::Submitted)
        ->and($request->request_number)->toStartWith('MSR-'.now()->format('Ymd').'-')
        ->and($request->requester_id)->toBe($this->requester->id)
        ->and($request->branch_id)->toBe($this->branch->id)
        ->and($request->branch_name)->toBe('Outlet Senopati')
        ->and($request->company_code)->toBe('BLSS')
        ->and($request->esb_product_id)->toBe(501)
        ->and($request->product_name)->toBe('Matcha Uji Jepang')
        ->and($request->category_name)->toBe('Bahan Baku')
        ->and($request->uom_name)->toBe('KG')
        ->and((float) $request->estimated_quantity)->toBe(12.5)
        ->and($request->rnd_product_esb_material_id)->toBeNull();

    $attachment = $request->attachmentList()[0];
    expect($attachment['path'])->toStartWith('purchasing/material-sourcing-requests/'.$request->id.'/')
        ->and($attachment['name'])->toBe('penawaran.pdf');
    Storage::disk('b2')->assertExists($attachment['path']);

    $history = $request->statusHistories()->sole();
    expect($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe(MaterialSourcingRequestStatus::Submitted)
        ->and($history->actor_id)->toBe($this->requester->id);

    expect(RndProductEsbMaterial::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

it('notifies active Purchasing processors after a submit', function () {
    msrSubmit($this->requester);

    expect($this->processor->notifications()->count())->toBe(1)
        ->and($this->processor->notifications()->first()->data['title'])->toBe('Request Sourcing baru')
        ->and($this->requester->notifications()->count())->toBe(0);
});

it('ignores browser product details and stores the ESB product read on the server', function () {
    $request = msrSubmit($this->requester, ['productName' => 'Produk Palsu', 'uomName' => 'PCS', 'esb_product_id' => 1]);

    expect($request->product_name)->toBe('Matcha Uji Jepang')
        ->and($request->uom_name)->toBe('KG')
        ->and($request->esb_product_id)->toBe(501);
});

it('refuses a product detail that does not belong to the code or is no longer active', function (array $overrides) {
    expect(fn () => msrSubmit($this->requester, $overrides))->toThrow(ValidationException::class);
    expect(MaterialSourcingRequest::query()->count())->toBe(0);
})->with([
    'mismatched detail ID' => [['productDetailId' => 9999]],
    'different product code' => [['productCode' => 'BB-OTHER']],
]);

it('keeps no partial request when ESB cannot be reached', function () {
    Http::fake(['https://master-product.test/*' => Http::failedConnection('cURL error 28: Operation timed out')]);

    try {
        msrSubmit($this->requester);
        $this->fail('Submit should fail while ESB is unreachable.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('productCode');
    }

    expect(MaterialSourcingRequest::query()->count())->toBe(0)
        ->and(Storage::disk('b2')->allFiles())->toBe([]);
});

it('validates the reason, quantity, target date, and attachments', function (array $overrides, string $field) {
    try {
        msrSubmit($this->requester, $overrides);
        $this->fail('Validation should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect(MaterialSourcingRequest::query()->count())->toBe(0);
})->with([
    'missing reason' => [['reason' => ''], 'reason'],
    'zero quantity' => [['estimatedQuantity' => '0'], 'estimatedQuantity'],
    'past target date' => [['targetDate' => now()->subDay()->toDateString()], 'targetDate'],
    'disallowed file type' => [fn () => ['attachments' => [UploadedFile::fake()->create('macro.xlsm', 10)]], 'attachments.0'],
    'file over 10 MB' => [fn () => ['attachments' => [UploadedFile::fake()->create('scan.pdf', 10241, 'application/pdf')]], 'attachments.0'],
    'more than 5 files' => [fn () => ['attachments' => array_map(fn (int $i) => UploadedFile::fake()->image("foto-{$i}.jpg"), range(1, 6))], 'attachments'],
]);

it('lets a requester without a branch submit nothing', function () {
    $this->requester->update(['branch_id' => null]);

    expect(fn () => msrSubmit($this->requester))->toThrow(ValidationException::class);
    expect(MaterialSourcingRequest::query()->count())->toBe(0);
});

it('refuses a submit from a user without the tile permission', function () {
    $outsider = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);

    expect(fn () => msrSubmit($outsider))->toThrow(AuthorizationException::class);
});

it('returns the same request for a repeated submission key', function () {
    $input = msrInput();
    $first = app(SubmitMaterialSourcingRequestAction::class)->execute($this->requester, $input);
    $second = app(SubmitMaterialSourcingRequestAction::class)->execute($this->requester, $input);

    expect($second->id)->toBe($first->id)
        ->and(MaterialSourcingRequest::query()->count())->toBe(1)
        ->and(MaterialSourcingRequestStatusHistory::query()->count())->toBe(1);
});

it('allows the same product on separate requests', function () {
    msrSubmit($this->requester);
    msrSubmit($this->requester);

    expect(MaterialSourcingRequest::query()->where('esb_product_detail_id', 9001)->count())->toBe(2);
});

it('runs claim, revision, resubmit, and conversion into exactly one standalone sourcing', function () {
    $request = msrSubmit($this->requester, ['attachments' => [UploadedFile::fake()->image('lama.jpg')]]);
    $oldPath = $request->attachmentList()[0]['path'];

    $claimed = app(ClaimMaterialSourcingRequestAction::class)->execute($this->processor, $request);
    expect($claimed->status)->toBe(MaterialSourcingRequestStatus::InReview)
        ->and($claimed->assigned_to)->toBe($this->processor->id)
        ->and($claimed->claimed_at)->not->toBeNull();

    $returned = app(DecideMaterialSourcingRequestAction::class)->requestRevision($this->processor, $claimed, 'Mohon lampirkan foto kemasan.');
    expect($returned->status)->toBe(MaterialSourcingRequestStatus::RevisionRequested)
        ->and($returned->last_decision_reason)->toBe('Mohon lampirkan foto kemasan.')
        ->and($returned->last_decision_by)->toBe($this->processor->id)
        ->and($this->requester->notifications()->first()->data['title'])->toBe('Request Sourcing perlu diperbaiki');

    $resubmitted = app(ResubmitMaterialSourcingRequestAction::class)->execute($this->requester, $returned, msrInput([
        'reason' => 'Supplier lama berhenti, foto kemasan terlampir.',
        'attachments' => [UploadedFile::fake()->image('kemasan.jpg')],
        'removedAttachmentPaths' => [$oldPath],
    ]));
    expect($resubmitted->status)->toBe(MaterialSourcingRequestStatus::Submitted)
        ->and($resubmitted->assigned_to)->toBeNull()
        ->and($resubmitted->reason)->toBe('Supplier lama berhenti, foto kemasan terlampir.')
        ->and($resubmitted->attachmentList())->toHaveCount(1)
        ->and($resubmitted->attachmentList()[0]['name'])->toBe('kemasan.jpg');
    Storage::disk('b2')->assertMissing($oldPath);

    $claimedAgain = app(ClaimMaterialSourcingRequestAction::class)->execute($this->processor, $resubmitted);
    $material = app(ConvertMaterialSourcingRequestAction::class)->execute($this->processor, $claimedAgain, [
        'material_notes' => 'Dari request user.',
        'sourcings' => [msrSupplier()],
    ]);
    $again = app(ConvertMaterialSourcingRequestAction::class)->execute($this->processor, $claimedAgain->refresh(), ['sourcings' => [msrSupplier('Supplier Lain')]]);

    $request->refresh();
    expect($again->id)->toBe($material->id)
        ->and(RndProductEsbMaterial::query()->count())->toBe(1)
        ->and($material->sourcing_origin)->toBe(RndProductEsbMaterial::SOURCING_ORIGIN_STANDALONE)
        ->and($material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($material->esb_product_detail_id)->toBe(9001)
        ->and($material->sourcings)->toHaveCount(1)
        ->and($material->sourcingRequest->is($request))->toBeTrue()
        ->and($request->status)->toBe(MaterialSourcingRequestStatus::Converted)
        ->and($request->rnd_product_esb_material_id)->toBe($material->id)
        ->and($request->converted_at)->not->toBeNull()
        ->and($request->closed_at)->not->toBeNull()
        ->and($request->submitted_at->lte($material->sourcing_requested_at))->toBeTrue();

    expect($request->statusHistories()->pluck('to_status')->map->value->all())
        ->toBe(['submitted', 'in_review', 'revision_requested', 'submitted', 'in_review', 'converted']);
});

it('refuses conversion without a valid supplier and leaves the request in review', function (array $sourcings) {
    $request = MaterialSourcingRequest::factory()->inReview($this->processor)->create(['requester_id' => $this->requester->id]);

    expect(fn () => app(ConvertMaterialSourcingRequestAction::class)->execute($this->processor, $request, ['sourcings' => $sourcings]))
        ->toThrow(ValidationException::class);

    expect($request->refresh()->status)->toBe(MaterialSourcingRequestStatus::InReview)
        ->and($request->rnd_product_esb_material_id)->toBeNull()
        ->and(RndProductEsbMaterial::query()->count())->toBe(0);
})->with([
    'no supplier' => [[]],
    'supplier without price' => [[['supplier_name' => 'PT Tanpa Harga', 'price' => null]]],
]);

it('rolls back conversion when ESB no longer has the product active', function () {
    Cache::put('esb.master_product.active_details.501', [], now()->addHour());
    $request = MaterialSourcingRequest::factory()->inReview($this->processor)->create(['requester_id' => $this->requester->id]);

    expect(fn () => app(ConvertMaterialSourcingRequestAction::class)->execute($this->processor, $request, ['sourcings' => [msrSupplier()]]))
        ->toThrow(ValidationException::class);

    expect($request->refresh()->status)->toBe(MaterialSourcingRequestStatus::InReview)
        ->and(RndProductEsbMaterial::query()->count())->toBe(0)
        ->and($request->statusHistories()->count())->toBe(0);
});

it('keeps decisions with the PIC and requires reasons', function () {
    $otherProcessor = User::factory()->create(['is_active' => true]);
    $otherProcessor->givePermissionTo(['view material sourcing requests', 'process material sourcing requests', 'submit material sourcing']);
    $request = msrSubmit($this->requester);
    app(ClaimMaterialSourcingRequestAction::class)->execute($this->processor, $request);

    expect(fn () => app(ClaimMaterialSourcingRequestAction::class)->execute($otherProcessor, $request))->toThrow(ValidationException::class)
        ->and(fn () => app(DecideMaterialSourcingRequestAction::class)->reject($otherProcessor, $request, 'Bukan kebutuhan cabang.'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ConvertMaterialSourcingRequestAction::class)->execute($otherProcessor, $request, ['sourcings' => [msrSupplier()]]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DecideMaterialSourcingRequestAction::class)->reject($this->processor, $request, ''))->toThrow(ValidationException::class);

    $rejected = app(DecideMaterialSourcingRequestAction::class)->reject($this->processor, $request, 'Produk sudah punya supplier kontrak.');
    expect($rejected->status)->toBe(MaterialSourcingRequestStatus::Rejected)
        ->and($rejected->closed_at)->not->toBeNull()
        ->and(fn () => app(DecideMaterialSourcingRequestAction::class)->requestRevision($this->processor, $rejected, 'Coba lagi nanti.'))->toThrow(ValidationException::class);
});

it('requires submit material sourcing for conversion', function () {
    $processorOnly = User::factory()->create(['is_active' => true]);
    $processorOnly->givePermissionTo(['view material sourcing requests', 'process material sourcing requests']);
    $request = MaterialSourcingRequest::factory()->inReview($processorOnly)->create(['requester_id' => $this->requester->id]);

    expect(fn () => app(ConvertMaterialSourcingRequestAction::class)->execute($processorOnly, $request, ['sourcings' => [msrSupplier()]]))
        ->toThrow(AuthorizationException::class);
    expect(RndProductEsbMaterial::query()->count())->toBe(0);
});

it('lets only the owner cancel a request Purchasing has not started, with a reason', function () {
    $other = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $other->givePermissionTo('access employee app sourcing request');
    $request = msrSubmit($this->requester);

    expect(fn () => app(CancelMaterialSourcingRequestAction::class)->execute($other, $request, 'Salah input produk.'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CancelMaterialSourcingRequestAction::class)->execute($this->requester, $request, ''))->toThrow(ValidationException::class);

    $cancelled = app(CancelMaterialSourcingRequestAction::class)->execute($this->requester, $request, 'Salah input produk.');
    expect($cancelled->status)->toBe(MaterialSourcingRequestStatus::Cancelled)
        ->and($cancelled->last_decision_reason)->toBe('Salah input produk.');

    $claimed = msrSubmit($this->requester);
    app(ClaimMaterialSourcingRequestAction::class)->execute($this->processor, $claimed);
    expect(fn () => app(CancelMaterialSourcingRequestAction::class)->execute($this->requester, $claimed, 'Tidak jadi dibutuhkan.'))->toThrow(ValidationException::class);
});

it('blocks resubmitting a request that is not waiting for revision or belongs to someone else', function () {
    $request = msrSubmit($this->requester);
    app(ClaimMaterialSourcingRequestAction::class)->execute($this->processor, $request);

    expect(fn () => app(ResubmitMaterialSourcingRequestAction::class)->execute($this->requester, $request->refresh(), msrInput()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ResubmitMaterialSourcingRequestAction::class)->execute($this->processor, $request, msrInput()))->toThrow(AuthorizationException::class);
});

it('keeps requests and history append-only', function () {
    $request = msrSubmit($this->requester);
    $history = $request->statusHistories()->sole();

    expect(fn () => $request->delete())->toThrow(LogicException::class)
        ->and(fn () => $history->update(['reason' => 'ubah']))->toThrow(LogicException::class)
        ->and(fn () => $history->delete())->toThrow(LogicException::class);
});

it('applies the authorization matrix', function () {
    $other = User::factory()->create(['is_active' => true]);
    $other->givePermissionTo('access employee app sourcing request');
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view material sourcing requests');
    $submitterOnly = User::factory()->create(['is_active' => true]);
    $submitterOnly->givePermissionTo('submit material sourcing');
    $unauthorized = User::factory()->create(['is_active' => true]);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');

    $submitted = MaterialSourcingRequest::factory()->create(['requester_id' => $this->requester->id]);
    $inReview = MaterialSourcingRequest::factory()->inReview($this->processor)->create(['requester_id' => $this->requester->id]);
    $revision = MaterialSourcingRequest::factory()->status(MaterialSourcingRequestStatus::RevisionRequested)->create(['requester_id' => $this->requester->id]);

    expect($this->requester->can('viewOwn', $submitted))->toBeTrue()
        ->and($this->requester->can('cancel', $submitted))->toBeTrue()
        ->and($this->requester->can('resubmit', $revision))->toBeTrue()
        ->and($this->requester->can('resubmit', $submitted))->toBeFalse()
        ->and($this->requester->can('view', $submitted))->toBeFalse()
        ->and($this->requester->can('downloadAttachment', $submitted))->toBeTrue()
        ->and($other->can('viewOwn', $submitted))->toBeFalse()
        ->and($other->can('cancel', $submitted))->toBeFalse()
        ->and($other->can('downloadAttachment', $submitted))->toBeFalse()
        ->and($viewer->can('view', $submitted))->toBeTrue()
        ->and($viewer->can('downloadAttachment', $submitted))->toBeTrue()
        ->and($viewer->can('claim', $submitted))->toBeFalse()
        ->and($this->processor->can('claim', $submitted))->toBeTrue()
        ->and($this->processor->can('convert', $inReview))->toBeTrue()
        ->and($this->processor->can('reject', $submitted))->toBeFalse()
        ->and($submitterOnly->can('convert', $inReview))->toBeFalse()
        ->and($submitterOnly->can('view', $submitted))->toBeFalse()
        ->and($unauthorized->can('create', MaterialSourcingRequest::class))->toBeFalse()
        ->and($unauthorized->can('view', $submitted))->toBeFalse()
        ->and($admin->can('view', $submitted))->toBeTrue()
        ->and($admin->can('convert', $inReview))->toBeTrue();
});

it('still guards status invariants for SUPERADMIN', function () {
    $admin = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $admin->assignRole('SUPERADMIN');
    $rejected = MaterialSourcingRequest::factory()->status(MaterialSourcingRequestStatus::Rejected)->create(['requester_id' => $this->requester->id]);

    expect(fn () => app(ConvertMaterialSourcingRequestAction::class)->execute($admin, $rejected, ['sourcings' => [msrSupplier()]]))->toThrow(ValidationException::class)
        ->and(fn () => app(ClaimMaterialSourcingRequestAction::class)->execute($admin, $rejected))->toThrow(ValidationException::class);
    expect(RndProductEsbMaterial::query()->count())->toBe(0);
});
