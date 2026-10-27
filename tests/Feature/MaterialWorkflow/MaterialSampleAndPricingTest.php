<?php

use App\Actions\Rnd\Material\AddMaterialSupplierCandidatesAction;
use App\Actions\Rnd\Material\ConfirmMaterialPricelistAction;
use App\Actions\Rnd\Material\ConfirmMaterialSampleReceiptAction;
use App\Actions\Rnd\Material\HandOverMaterialSampleAction;
use App\Actions\Rnd\Material\ReopenMaterialSampleApprovalAction;
use App\Actions\Rnd\Material\ReturnMaterialPricingToItAction;
use App\Actions\Rnd\Material\ReviewMaterialSampleAction;
use App\Actions\Rnd\Material\SaveMaterialPricingDraftAction;
use App\Actions\Rnd\Material\StartMaterialPriceRevisionAction;
use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\MaterialSourcingApproval;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialPricingEligibility;
use App\Services\Rnd\Material\MaterialWorkflowCompletion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');
    $this->rnd = User::factory()->create(['is_active' => true]);
    $this->rnd->assignRole('RND_STAFF');
    $this->finance = User::factory()->create(['is_active' => true]);
    $this->finance->assignRole('FINANCE_STAFF');
    $this->material = RndProductEsbMaterial::factory()->synced(8100, 9100)->create([
        'created_by' => $this->rnd->id,
        'sourcing_status' => MaterialSourcingStatus::WaitingPurchasing,
        'sourcing_requested_at' => now(),
    ]);
});

function supplierCandidate(RndProductEsbMaterial $material, User $purchasing, string $name = 'PT Tepung Jaya', float $price = 12500): int
{
    app(AddMaterialSupplierCandidatesAction::class)->execute($material, $purchasing, [[
        'supplier_name' => $name, 'brand' => 'Jaya', 'price' => $price, 'moq' => '50 kg', 'lead_time_days' => 5,
        'contact_name' => 'Andi', 'contact_phone' => '081200000000', 'notes' => null, 'attachment_path' => null,
    ]]);

    return $material->sourcings()->where('supplier_name', $name)->value('id');
}

function handOver(RndProductEsbMaterial $material, User $purchasing, int $candidateId): RndMaterialSampleCycle
{
    return app(HandOverMaterialSampleAction::class)->execute($material, $purchasing, [
        'material_sourcing_id' => $candidateId, 'quantity' => 2, 'uom_name' => 'KG', 'notes' => 'Sampel batch 1',
        'photos' => [UploadedFile::fake()->image('handover.jpg')],
    ]);
}

function receive(RndProductEsbMaterial $material, User $rnd): RndMaterialSampleCycle
{
    return app(ConfirmMaterialSampleReceiptAction::class)->execute($material, $rnd, [
        'received_quantity' => 2, 'received_condition' => 'good', 'photos' => [UploadedFile::fake()->image('receipt.png')],
    ]);
}

it('runs supplier → hand-over → receipt → approval and creates exactly one Pricing Request', function () {
    $candidate = supplierCandidate($this->material, $this->purchasing);
    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::SourcingInProgress);

    $cycle = handOver($this->material, $this->purchasing, $candidate);
    expect($cycle->cycle_number)->toBe(1)
        ->and($cycle->handed_over_by)->toBe($this->purchasing->id)
        ->and($cycle->attachments()->where('kind', 'handover')->count())->toBe(1)
        ->and($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::SampleHandedOver);

    receive($this->material, $this->rnd);
    expect($cycle->refresh()->attachments()->where('kind', 'receipt')->count())->toBe(1)
        ->and($cycle->received_by)->toBe($this->rnd->id);

    app(ReviewMaterialSampleAction::class)->execute($this->material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE);
    app(MaterialPricingEligibility::class)->ensure($this->material->refresh(), $this->rnd);

    $request = RndMaterialPricingRequest::query()->sole();
    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::ApprovedByRnd)
        ->and($this->material->sourcing_selected_id)->toBe($candidate)
        ->and($this->material->pricing_status)->toBe(MaterialPricingStatus::ReadyForPricing)
        ->and($request->origin)->toBe(RndMaterialPricingRequest::ORIGIN_SOURCING)
        ->and($request->product_detail_id)->toBe(9100)
        ->and((float) $request->offer_price)->toBe(12500.0)
        ->and($request->price)->toBeNull()
        ->and($request->supplier_name)->toBe('PT Tepung Jaya');
});

it('keeps hand-over and receipt evidence separate and requires photos for both', function () {
    $candidate = supplierCandidate($this->material, $this->purchasing);

    expect(fn () => app(HandOverMaterialSampleAction::class)->execute($this->material, $this->purchasing, ['material_sourcing_id' => $candidate, 'quantity' => 1, 'uom_name' => 'KG', 'photos' => []]))
        ->toThrow(ValidationException::class);
    handOver($this->material, $this->purchasing, $candidate);
    expect(fn () => app(ConfirmMaterialSampleReceiptAction::class)->execute($this->material, $this->rnd, ['received_quantity' => 1, 'received_condition' => 'good', 'photos' => []]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(ReviewMaterialSampleAction::class)->execute($this->material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE))
        ->toThrow(ValidationException::class);
});

it('rejects a supplier candidate of another material, zero quantity, and non-image files', function (Closure $input) {
    $candidate = supplierCandidate($this->material, $this->purchasing);
    $other = RndProductEsbMaterial::factory()->create();
    $otherCandidate = $other->sourcings()->create(['supplier_name' => 'Lain', 'price' => 1, 'submitted_by' => $this->purchasing->id])->id;

    expect(fn () => app(HandOverMaterialSampleAction::class)->execute($this->material, $this->purchasing, $input($candidate, $otherCandidate)))->toThrow(ValidationException::class);
    expect(RndMaterialSampleCycle::query()->count())->toBe(0)
        ->and(Storage::disk('b2')->allFiles())->toBe([]);
})->with([
    'foreign candidate' => [fn ($own, $foreign) => ['material_sourcing_id' => $foreign, 'quantity' => 1, 'uom_name' => 'KG', 'photos' => [UploadedFile::fake()->image('a.jpg')]]],
    'zero quantity' => [fn ($own) => ['material_sourcing_id' => $own, 'quantity' => 0, 'uom_name' => 'KG', 'photos' => [UploadedFile::fake()->image('a.jpg')]]],
    'pdf disguised as photo' => [fn ($own) => ['material_sourcing_id' => $own, 'quantity' => 1, 'uom_name' => 'KG', 'photos' => [UploadedFile::fake()->create('a.jpg', 10, 'application/pdf')]]],
]);

it('opens a new cycle on resample and keeps the previous cycle history', function () {
    $candidate = supplierCandidate($this->material, $this->purchasing);
    handOver($this->material, $this->purchasing, $candidate);
    receive($this->material, $this->rnd);

    expect(fn () => app(ReviewMaterialSampleAction::class)->execute($this->material, $this->rnd, RndMaterialSampleCycle::EVALUATION_RESAMPLE, 'pendek'))->toThrow(ValidationException::class);
    app(ReviewMaterialSampleAction::class)->execute($this->material, $this->rnd, RndMaterialSampleCycle::EVALUATION_RESAMPLE, 'Tekstur terlalu kasar, minta batch baru');

    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::ResampleRequested)
        ->and($this->material->sampleCycles()->pluck('status', 'cycle_number')->map->value->all())->toBe([2 => 'prepared', 1 => 'resample_requested']);

    $second = handOver($this->material, $this->purchasing, $candidate);
    expect($second->cycle_number)->toBe(2)
        ->and(RndMaterialSampleCycle::query()->where('cycle_number', 1)->sole()->evaluation_reason)->toBe('Tekstur terlalu kasar, minta batch baru')
        ->and(RndMaterialPricingRequest::query()->count())->toBe(0);
});

it('creates no Pricing Request when R&D rejects the sample', function () {
    $candidate = supplierCandidate($this->material, $this->purchasing);
    handOver($this->material, $this->purchasing, $candidate);
    receive($this->material, $this->rnd);

    app(ReviewMaterialSampleAction::class)->execute($this->material, $this->rnd, RndMaterialSampleCycle::EVALUATION_REJECT, 'Aroma tidak sesuai spesifikasi');

    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::RejectedByRnd)
        ->and(RndMaterialPricingRequest::query()->count())->toBe(0)
        ->and(app(MaterialPricingEligibility::class)->isEligible($this->material))->toBeFalse();
});

it('authorizes each sample step by its own permission', function () {
    $candidate = supplierCandidate($this->material, $this->purchasing);

    expect(fn () => app(HandOverMaterialSampleAction::class)->execute($this->material, $this->rnd, ['material_sourcing_id' => $candidate, 'quantity' => 1, 'uom_name' => 'KG', 'photos' => [UploadedFile::fake()->image('a.jpg')]]))
        ->toThrow(AuthorizationException::class);
    handOver($this->material, $this->purchasing, $candidate);
    expect(fn () => receive($this->material, $this->purchasing))->toThrow(AuthorizationException::class);
});

function approvedWithRequest(RndProductEsbMaterial $material, User $purchasing, User $rnd): RndMaterialPricingRequest
{
    $candidate = supplierCandidate($material, $purchasing);
    handOver($material, $purchasing, $candidate);
    receive($material, $rnd);
    app(ReviewMaterialSampleAction::class)->execute($material, $rnd, RndMaterialSampleCycle::EVALUATION_APPROVE);

    return RndMaterialPricingRequest::query()->sole();
}

it('needs a verified confirmation before priced and then completes the workflow once', function () {
    $request = approvedWithRequest($this->material, $this->purchasing, $this->rnd);

    app(SaveMaterialPricingDraftAction::class)->execute($request, $this->finance, ['price' => 13000, 'company_code' => 'BLSS', 'effective_date' => '2026-11-01']);
    expect($request->refresh()->status)->toBe(MaterialPricingStatus::DraftPricing)
        ->and($this->material->refresh()->workflow_completed_at)->toBeNull()
        ->and(app(MaterialWorkflowCompletion::class)->guardsMet($this->material))->toBeFalse();

    expect(fn () => app(ConfirmMaterialPricelistAction::class)->execute($request, $this->finance, ['external_reference' => 'PL-2026-001', 'confirmed' => false]))->toThrow(ValidationException::class);
    app(ConfirmMaterialPricelistAction::class)->execute($request, $this->finance, ['external_reference' => 'PL-2026-001', 'confirmed' => true, 'note' => 'Sudah diinput di ESB']);

    $this->material->refresh();
    expect($request->refresh()->status)->toBe(MaterialPricingStatus::Priced)
        ->and($request->verified_by)->toBe($this->finance->id)
        ->and((float) $request->offer_price)->toBe(12500.0)
        ->and((float) $request->price)->toBe(13000.0)
        ->and($this->material->pricing_status)->toBe(MaterialPricingStatus::Priced)
        ->and($this->material->workflow_completed_at)->not->toBeNull()
        ->and(MaterialSourcingApproval::query()->where('stage', 'finance')->pluck('action')->all())->toBe(['pricelist_confirmed'])
        ->and(app(MaterialWorkflowCompletion::class)->evaluate($this->material))->toBeFalse();
});

it('rejects pricing changes by users without the Finance permission and invalid prices', function () {
    $request = approvedWithRequest($this->material, $this->purchasing, $this->rnd);

    expect(fn () => app(SaveMaterialPricingDraftAction::class)->execute($request, $this->rnd, ['price' => 1000]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveMaterialPricingDraftAction::class)->execute($request, $this->finance, ['price' => 0]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveMaterialPricingDraftAction::class)->execute($request, $this->finance, ['price' => 10, 'company_code' => 'NOPE']))->toThrow(ValidationException::class);
});

it('sends a wrong mapping back to IT and reactivates the same request after reconciliation', function () {
    $request = approvedWithRequest($this->material, $this->purchasing, $this->rnd);

    app(ReturnMaterialPricingToItAction::class)->execute($request, $this->finance, 'UOM KG di ESB bukan unit pembelian');
    expect($request->refresh()->status)->toBe(MaterialPricingStatus::Failed)
        ->and($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::NeedsReconciliation)
        ->and(app(MaterialPricingEligibility::class)->isEligible($this->material))->toBeFalse();

    $this->material->forceFill(['esb_sync_status' => MaterialEsbSyncStatus::Synced, 'status' => 'synced'])->save();
    app(MaterialPricingEligibility::class)->ensure($this->material->refresh(), null);

    expect($request->refresh()->status)->toBe(MaterialPricingStatus::ReadyForPricing)
        ->and(RndMaterialPricingRequest::query()->count())->toBe(1);
});

it('cancels an untouched Pricing Request on reopen and refuses reopen once Finance started', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('reopen material sample approval');
    $request = approvedWithRequest($this->material, $this->purchasing, $this->rnd);

    expect(fn () => app(ReopenMaterialSampleApprovalAction::class)->execute($this->material, $this->rnd, 'Supplier ganti formula'))->toThrow(AuthorizationException::class);
    app(ReopenMaterialSampleApprovalAction::class)->execute($this->material, $admin, 'Supplier mengganti formula produk');

    expect($request->refresh()->status)->toBe(MaterialPricingStatus::Cancelled)
        ->and($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::SampleReceived);

    app(ReviewMaterialSampleAction::class)->execute($this->material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE);
    $second = RndMaterialPricingRequest::query()->where('status', MaterialPricingStatus::ReadyForPricing->value)->sole();
    app(SaveMaterialPricingDraftAction::class)->execute($second, $this->finance, ['price' => 9000]);

    expect(fn () => app(ReopenMaterialSampleApprovalAction::class)->execute($this->material, $admin, 'Supplier mengganti formula lagi'))->toThrow(ValidationException::class);
    expect($second->refresh()->status)->toBe(MaterialPricingStatus::DraftPricing)
        ->and($second->conflict_reason)->toContain('buka ulang');
});

it('starts a price revision as a new request and keeps the priced history', function () {
    $request = approvedWithRequest($this->material, $this->purchasing, $this->rnd);
    app(SaveMaterialPricingDraftAction::class)->execute($request, $this->finance, ['price' => 13000]);
    app(ConfirmMaterialPricelistAction::class)->execute($request, $this->finance, ['external_reference' => 'PL-1', 'confirmed' => true]);

    $revision = app(StartMaterialPriceRevisionAction::class)->execute($request->refresh(), $this->finance, 'Harga supplier naik per November');

    expect($revision->revision)->toBe(2)
        ->and($revision->status)->toBe(MaterialPricingStatus::ReadyForPricing)
        ->and($request->refresh()->status)->toBe(MaterialPricingStatus::Priced)
        ->and($this->material->refresh()->workflow_completed_at)->toBeNull();
});

it('blocks adding suppliers to a WIP or before the Sourcing Request exists', function () {
    $wip = RndProductEsbMaterial::factory()->wip()->synced()->create(['sourcing_status' => MaterialSourcingStatus::NotRequired]);
    $notSynced = RndProductEsbMaterial::factory()->create();

    expect(fn () => supplierCandidate($wip, $this->purchasing))->toThrow(ValidationException::class)
        ->and(fn () => supplierCandidate($notSynced, $this->purchasing))->toThrow(ValidationException::class)
        ->and($wip->sourcings()->count())->toBe(0);
});

it('never completes a draft or failed pricing, and never completes legacy rows', function () {
    $legacy = RndProductEsbMaterial::factory()->legacy()->create(['status' => 'synced', 'sourcing_status' => MaterialSourcingStatus::Approved, 'pricing_status' => MaterialPricingStatus::Priced]);
    $draft = RndProductEsbMaterial::factory()->sampleApproved()->create(['pricing_status' => MaterialPricingStatus::DraftPricing]);
    $wipReady = RndProductEsbMaterial::factory()->wip()->synced()->create(['sourcing_status' => MaterialSourcingStatus::NotRequired, 'pricing_status' => MaterialPricingStatus::Priced]);

    $completion = app(MaterialWorkflowCompletion::class);
    expect($completion->evaluate($legacy))->toBeFalse()
        ->and($completion->evaluate($draft))->toBeFalse()
        ->and($completion->evaluate($wipReady))->toBeTrue();
});

it('serves sample photos only to authorized users', function () {
    $candidate = supplierCandidate($this->material, $this->purchasing);
    $attachment = handOver($this->material, $this->purchasing, $candidate)->attachments()->sole();
    $outsider = User::factory()->create(['is_active' => true]);

    $this->actingAs($outsider)->get(route('helpdesk.rnd-material-sample-attachments.show', $attachment))->assertForbidden();
    $this->actingAs($this->rnd)->get(route('helpdesk.rnd-material-sample-attachments.show', $attachment))->assertOk();
    expect(Storage::disk('b2')->exists($attachment->path))->toBeTrue();
});
