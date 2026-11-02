<?php

use App\Actions\Rnd\CreateStandaloneMaterialSourcingAction;
use App\Actions\Rnd\Material\AddMaterialSupplierCandidatesAction;
use App\Actions\Rnd\Material\ConfirmMaterialSampleReceiptAction;
use App\Actions\Rnd\Material\HandOverMaterialSampleAction;
use App\Actions\Rnd\Material\ReopenMaterialSampleApprovalAction;
use App\Actions\Rnd\Material\ReopenRejectedMaterialSourcingAction;
use App\Actions\Rnd\Material\ReviewLegacyMaterialSourcingAction;
use App\Actions\Rnd\Material\ReviewLegacyMaterialSourcingFinanceAction;
use App\Actions\Rnd\Material\ReviewMaterialSampleAction;
use App\Actions\Rnd\Material\UpdateMaterialSupplierCandidateAction;
use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use App\Filament\Helpdesk\Resources\MaterialSourcings\Pages\ListMaterialSourcings;
use App\Filament\Helpdesk\Resources\ReviewSourcings\Pages\ListReviewSourcings;
use App\Filament\Helpdesk\Resources\ReviewSourcings\ReviewSourcingResource;
use App\Models\RndMaterialAuditLog;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialPostSyncRouter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    Http::preventStrayRequests();
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->travelTo(Carbon::parse('2026-11-01 08:00:00'));
    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');
    $this->rnd = User::factory()->create(['is_active' => true]);
    $this->rnd->assignRole('RND_STAFF');
    $this->rnd->givePermissionTo('reopen material sample approval');
    $this->finance = User::factory()->create(['is_active' => true]);
    $this->finance->assignRole('FINANCE_STAFF');
    $this->finance->givePermissionTo('review material sourcing as finance');
});

function durationSupplierRow(): array
{
    return [
        'supplier_name' => 'PT Tepung Jaya', 'brand' => null, 'price' => 12500, 'moq' => '50 kg', 'lead_time_days' => 5,
        'contact_name' => 'Andi', 'contact_phone' => '081200000000', 'notes' => null, 'attachment_path' => null,
    ];
}

function durationWorkflowMaterial(User $rnd): RndProductEsbMaterial
{
    return RndProductEsbMaterial::factory()->synced(8100, 9100)->create([
        'created_by' => $rnd->id,
        'sourcing_status' => MaterialSourcingStatus::WaitingPurchasing,
        'sourcing_requested_at' => now(),
    ]);
}

function durationSampleReceived(RndProductEsbMaterial $material, User $purchasing, User $rnd): void
{
    if (! $material->sourcings()->exists()) {
        app(AddMaterialSupplierCandidatesAction::class)->execute($material, $purchasing, [durationSupplierRow()]);
    }
    app(HandOverMaterialSampleAction::class)->execute($material->refresh(), $purchasing, [
        'material_sourcing_id' => $material->sourcings()->value('id'), 'quantity' => 2, 'uom_name' => 'KG',
        'photos' => [UploadedFile::fake()->image('handover.jpg')],
    ]);
    app(ConfirmMaterialSampleReceiptAction::class)->execute($material->refresh(), $rnd, [
        'received_quantity' => 2, 'received_condition' => 'good', 'photos' => [UploadedFile::fake()->image('receipt.png')],
    ]);
}

function latestDurationAudit(RndProductEsbMaterial $material, string $event): RndMaterialAuditLog
{
    return RndMaterialAuditLog::query()->where('rnd_product_esb_material_id', $material->id)->where('event', $event)->latest('id')->firstOrFail();
}

describe('start date', function () {
    it('is written once when a project material enters the Purchasing queue', function () {
        $material = RndProductEsbMaterial::factory()->synced(8100, 9100)->create(['sourcing_status' => MaterialSourcingStatus::NotStarted]);

        app(MaterialPostSyncRouter::class)->route($material, $this->rnd);
        $this->travelTo(now()->addDay());
        app(MaterialPostSyncRouter::class)->route($material, $this->rnd);

        expect($material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::WaitingPurchasing)
            ->and($material->sourcing_requested_at->toDateTimeString())->toBe('2026-11-01 08:00:00')
            ->and($material->sourcing_completed_at)->toBeNull();
    });

    it('is written by the server when standalone sourcing is created, ignoring browser input', function () {
        config()->set('esb.master_product.base_url', 'https://master-product.test');
        config()->set('esb.master_product.token', 'master-token');
        Cache::put('esb.master_product.active_details.501', [9001], now()->addHour());
        Http::fake(['https://master-product.test/corev1/master/product*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 20, 'count' => 1, 'next' => '', 'prev' => '',
            'data' => [['productID' => 501, 'productCode' => 'BB-MATCHA', 'productName' => 'Matcha', 'categoryName' => 'Bahan Baku', 'subCategoryName' => '', 'productDetails' => [['productDetailID' => 9001, 'unit' => 'KG', 'conversionFactor' => 1, 'flagActive' => 'true']]]],
        ]])]);

        $material = app(CreateStandaloneMaterialSourcingAction::class)->execute([
            'product_code' => 'BB-MATCHA', 'product_detail_id' => 9001, 'sourcings' => [durationSupplierRow()],
            'sourcing_requested_at' => '2020-01-01 00:00:00', 'sourcing_completed_at' => '2020-01-02 00:00:00',
        ], $this->purchasing);

        expect($material->refresh()->sourcing_requested_at->toDateTimeString())->toBe('2026-11-01 08:00:00')
            ->and($material->sourcing_completed_at)->toBeNull();
    });

    it('gives a WIP no sourcing dates', function () {
        $wip = RndProductEsbMaterial::factory()->wip()->synced(8100, 9100)->create();

        app(MaterialPostSyncRouter::class)->route($wip, $this->rnd);

        expect($wip->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::NotRequired)
            ->and($wip->sourcing_requested_at)->toBeNull()
            ->and($wip->sourcing_completed_at)->toBeNull();
    });

    it('is written when a legacy material gets its first supplier but not when a decided one is resubmitted', function () {
        $notStarted = RndProductEsbMaterial::factory()->legacy()->create();
        $decided = RndProductEsbMaterial::factory()->legacy()->create(['sourcing_status' => MaterialSourcingStatus::Approved]);

        app(AddMaterialSupplierCandidatesAction::class)->execute($notStarted, $this->purchasing, [durationSupplierRow()]);
        app(AddMaterialSupplierCandidatesAction::class)->execute($decided, $this->purchasing, [durationSupplierRow()]);

        expect($notStarted->refresh()->sourcing_requested_at->toDateTimeString())->toBe('2026-11-01 08:00:00')
            ->and($decided->refresh()->sourcing_requested_at)->toBeNull();
    });
});

describe('sample workflow', function () {
    it('completes on approve and on reject with the time of the decision, auditing it', function (string $decision, MaterialSourcingStatus $status, string $outcome) {
        $material = durationWorkflowMaterial($this->rnd);
        durationSampleReceived($material, $this->purchasing, $this->rnd);
        $this->travelTo(Carbon::parse('2026-11-07 14:00:00'));

        $cycle = app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, $decision, 'Alasan evaluasi sampel');

        expect($material->refresh()->sourcing_status)->toBe($status)
            ->and($material->sourcing_completed_at->toDateTimeString())->toBe('2026-11-07 14:00:00')
            ->and($material->sourcing_completed_at->equalTo($cycle->evaluated_at))->toBeTrue()
            ->and($material->sourcing_requested_at->toDateTimeString())->toBe('2026-11-01 08:00:00');

        $metadata = latestDurationAudit($material, 'sample_'.$decision)->metadata;
        expect(Carbon::parse($metadata['sourcing_completed_at'])->equalTo($material->sourcing_completed_at))->toBeTrue()
            ->and($metadata['sourcing_outcome'])->toBe($outcome);
    })->with([
        'approve' => [RndMaterialSampleCycle::EVALUATION_APPROVE, MaterialSourcingStatus::ApprovedByRnd, 'approved'],
        'reject' => [RndMaterialSampleCycle::EVALUATION_REJECT, MaterialSourcingStatus::RejectedByRnd, 'rejected'],
    ]);

    it('keeps resample running', function () {
        $material = durationWorkflowMaterial($this->rnd);
        durationSampleReceived($material, $this->purchasing, $this->rnd);

        app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, RndMaterialSampleCycle::EVALUATION_RESAMPLE, 'Tekstur sampel kurang sesuai');

        expect($material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::ResampleRequested)
            ->and($material->sourcing_completed_at)->toBeNull();
    });

    it('does not move a final decision when a double submit is refused', function () {
        $material = durationWorkflowMaterial($this->rnd);
        durationSampleReceived($material, $this->purchasing, $this->rnd);
        app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE);
        $this->travelTo(now()->addHour());

        expect(fn () => app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE))
            ->toThrow(ValidationException::class);

        expect($material->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-11-01 08:00:00');
    });

    it('reopens an approval, keeps the start, audits the old completion, and re-approves with a new one', function () {
        $material = durationWorkflowMaterial($this->rnd);
        durationSampleReceived($material, $this->purchasing, $this->rnd);
        $this->travelTo(Carbon::parse('2026-11-03 08:00:00'));
        app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE);
        $this->travelTo(Carbon::parse('2026-11-04 08:00:00'));

        app(ReopenMaterialSampleApprovalAction::class)->execute($material, $this->rnd, 'Sampel perlu dievaluasi ulang');

        expect($material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::SampleReceived)
            ->and($material->sourcing_completed_at)->toBeNull()
            ->and($material->sourcing_requested_at->toDateTimeString())->toBe('2026-11-01 08:00:00');
        expect(Carbon::parse(latestDurationAudit($material, 'sample_approval_reopened')->metadata['previous_sourcing_completed_at'])->toDateTimeString())->toBe('2026-11-03 08:00:00');

        $this->travelTo(Carbon::parse('2026-11-05 08:00:00'));
        app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE);

        expect($material->refresh()->sourcing_completed_at->toDateTimeString())->toBe('2026-11-05 08:00:00');
    });

    it('leaves the completion in place when reopening is refused because Finance started pricing', function () {
        $material = durationWorkflowMaterial($this->rnd);
        durationSampleReceived($material, $this->purchasing, $this->rnd);
        app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, RndMaterialSampleCycle::EVALUATION_APPROVE);
        $material->pricingRequests()->update(['status' => 'draft_pricing']);

        expect(fn () => app(ReopenMaterialSampleApprovalAction::class)->execute($material, $this->rnd, 'Sampel perlu dievaluasi ulang'))
            ->toThrow(ValidationException::class);

        expect($material->refresh()->sourcing_completed_at)->not->toBeNull();
    });

    it('reopens a rejection, keeps the start, and audits the old completion', function () {
        $material = durationWorkflowMaterial($this->rnd);
        durationSampleReceived($material, $this->purchasing, $this->rnd);
        $this->travelTo(Carbon::parse('2026-11-03 08:00:00'));
        app(ReviewMaterialSampleAction::class)->execute($material, $this->rnd, RndMaterialSampleCycle::EVALUATION_REJECT, 'Kualitas sampel tidak sesuai');

        app(ReopenRejectedMaterialSourcingAction::class)->execute($material, $this->rnd, 'Supplier mengirim sampel baru');

        expect($material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::SourcingInProgress)
            ->and($material->sourcing_completed_at)->toBeNull()
            ->and($material->sourcing_requested_at->toDateTimeString())->toBe('2026-11-01 08:00:00');
        expect(Carbon::parse(latestDurationAudit($material, 'sourcing_reopened')->metadata['previous_sourcing_completed_at'])->toDateTimeString())->toBe('2026-11-03 08:00:00');
    });
});

describe('legacy workflow', function () {
    beforeEach(function () {
        $this->legacy = RndProductEsbMaterial::factory()->legacy()->create([
            'sourcing_status' => MaterialSourcingStatus::PendingRndReview,
            'sourcing_requested_at' => now(),
        ]);
        $this->candidate = $this->legacy->sourcings()->create([...durationSupplierRow(), 'submitted_by' => $this->purchasing->id]);
    });

    it('keeps R&D approval running and completes on Finance approval', function () {
        app(ReviewLegacyMaterialSourcingAction::class)->execute($this->legacy, $this->rnd, 'approve', $this->candidate->id, null);

        expect($this->legacy->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::PendingFinanceReview)
            ->and($this->legacy->sourcing_completed_at)->toBeNull();

        $this->travelTo(Carbon::parse('2026-11-02 08:00:00'));
        app(ReviewLegacyMaterialSourcingFinanceAction::class)->execute($this->legacy, $this->finance, 'approve', null);

        expect($this->legacy->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::Approved)
            ->and($this->legacy->sourcing_completed_at->toDateTimeString())->toBe('2026-11-02 08:00:00');
        expect(latestDurationAudit($this->legacy, 'legacy_sourcing_approved')->metadata['sourcing_outcome'])->toBe('approved');
    });

    it('stays running when Finance returns the record to R&D', function () {
        app(ReviewLegacyMaterialSourcingAction::class)->execute($this->legacy, $this->rnd, 'approve', $this->candidate->id, null);

        app(ReviewLegacyMaterialSourcingFinanceAction::class)->execute($this->legacy, $this->finance, 'reject', 'Harga terlalu tinggi');

        expect($this->legacy->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
            ->and($this->legacy->sourcing_completed_at)->toBeNull();
    });

    it('completes on R&D rejection', function () {
        $this->travelTo(Carbon::parse('2026-11-02 08:00:00'));

        app(ReviewLegacyMaterialSourcingAction::class)->execute($this->legacy, $this->rnd, 'reject', null, 'Perlu supplier lain');

        expect($this->legacy->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::Rejected)
            ->and($this->legacy->sourcing_completed_at->toDateTimeString())->toBe('2026-11-02 08:00:00');
        expect(latestDurationAudit($this->legacy, 'legacy_sourcing_rejected')->metadata['sourcing_outcome'])->toBe('rejected');
    });

    it('runs again when a final record is resubmitted by adding or editing a supplier', function (string $resubmission) {
        $this->legacy->forceFill(['sourcing_status' => MaterialSourcingStatus::Approved, 'sourcing_completed_at' => '2026-10-31 08:00:00'])->save();

        match ($resubmission) {
            'add' => app(AddMaterialSupplierCandidatesAction::class)->execute($this->legacy, $this->purchasing, [durationSupplierRow()]),
            'edit' => app(UpdateMaterialSupplierCandidateAction::class)->execute($this->candidate, $this->purchasing, durationSupplierRow()),
        };

        expect($this->legacy->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
            ->and($this->legacy->sourcing_completed_at)->toBeNull()
            ->and($this->legacy->sourcing_requested_at->toDateTimeString())->toBe('2026-11-01 08:00:00');
        expect(Carbon::parse(latestDurationAudit($this->legacy, 'legacy_sourcing_resubmitted')->metadata['previous_sourcing_completed_at'])->toDateTimeString())->toBe('2026-10-31 08:00:00');
    })->with(['add', 'edit']);
});

describe('index and detail', function () {
    beforeEach(function () {
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        $this->running = RndProductEsbMaterial::factory()->legacy()->create([
            'sourcing_status' => MaterialSourcingStatus::PendingRndReview,
            'sourcing_requested_at' => '2026-11-01 08:00:00',
        ]);
        $this->completed = RndProductEsbMaterial::factory()->legacy()->create([
            'sourcing_status' => MaterialSourcingStatus::Approved,
            'sourcing_requested_at' => '2026-10-20 08:00:00',
            'sourcing_completed_at' => '2026-10-26 14:00:00',
        ]);
        $this->ambiguous = RndProductEsbMaterial::factory()->legacy()->create([
            'sourcing_status' => MaterialSourcingStatus::Rejected,
            'product_name' => str_repeat('Bahan dengan nama sangat panjang ', 3),
        ]);
    });

    it('shows the same dates and duration in Purchasing and Review Sourcing', function (string $page, string $user) {
        $this->actingAs($this->{$user});

        Livewire::test($page)
            ->set('activeTab', $page === ListReviewSourcings::class ? 'all' : null)
            ->assertTableColumnStateSet('sourcing_requested_at', '01 Nov 2026 08:00', $this->running)
            ->assertTableColumnStateSet('sourcing_completed_at', '—', $this->running)
            ->assertTableColumnStateSet('sourcing_duration', '4 hari 2 jam berjalan', $this->running)
            ->assertTableColumnStateSet('sourcing_requested_at', '20 Oct 2026 08:00', $this->completed)
            ->assertTableColumnStateSet('sourcing_completed_at', '26 Oct 2026 14:00', $this->completed)
            ->assertTableColumnStateSet('sourcing_duration', '6 hari 6 jam', $this->completed)
            ->assertTableColumnStateSet('sourcing_requested_at', 'Tidak Tersedia', $this->ambiguous)
            ->assertTableColumnStateSet('sourcing_completed_at', 'Tidak Tersedia', $this->ambiguous)
            ->assertTableColumnStateSet('sourcing_duration', 'Tidak Tersedia', $this->ambiguous);
    })->with([
        'purchasing' => [ListMaterialSourcings::class, 'purchasing'],
        'rnd' => [ListReviewSourcings::class, 'rnd'],
    ]);

    it('does not extend a final duration through later changes to the record', function () {
        $this->completed->forceFill(['workflow_completed_at' => now(), 'pricing_status' => 'priced'])->save();
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)
            ->assertTableColumnStateSet('sourcing_completed_at', '26 Oct 2026 14:00', $this->completed)
            ->assertTableColumnStateSet('sourcing_duration', '6 hari 6 jam', $this->completed);
    });

    it('filters by running or completed state and by date range', function () {
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)
            ->filterTable('sourcing_duration_state', 'running')
            ->assertCanSeeTableRecords([$this->running])
            ->assertCanNotSeeTableRecords([$this->completed, $this->ambiguous])
            ->resetTableFilters()
            ->filterTable('sourcing_duration_state', 'completed')
            ->assertCanSeeTableRecords([$this->completed, $this->ambiguous])
            ->assertCanNotSeeTableRecords([$this->running])
            ->resetTableFilters()
            ->filterTable('sourcing_requested_at', ['from' => '2026-11-01', 'until' => '2026-11-02'])
            ->assertCanSeeTableRecords([$this->running])
            ->assertCanNotSeeTableRecords([$this->completed, $this->ambiguous])
            ->resetTableFilters()
            ->filterTable('sourcing_completed_at', ['from' => '2026-10-26', 'until' => '2026-10-26'])
            ->assertCanSeeTableRecords([$this->completed])
            ->assertCanNotSeeTableRecords([$this->running, $this->ambiguous]);
    });

    it('leaves a material that has not entered sourcing out of the running filter', function () {
        $notStarted = RndProductEsbMaterial::factory()->legacy()->create();
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)
            ->filterTable('sourcing_duration_state', 'running')
            ->assertCanNotSeeTableRecords([$notStarted])
            ->resetTableFilters()
            ->assertTableColumnStateSet('sourcing_duration', '—', $notStarted);
    });

    it('sorts by the start and completion dates and keeps the default sort', function () {
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)
            ->sortTable('sourcing_requested_at')
            ->assertCanSeeTableRecords([$this->ambiguous, $this->completed, $this->running], inOrder: true)
            ->sortTable('sourcing_completed_at', 'desc')
            ->assertCanSeeTableRecords([$this->completed], inOrder: true);

        expect(Livewire::test(ListMaterialSourcings::class)->instance()->getTable()->getDefaultSortColumn())->toBe('updated_at');
    });

    it('hides the technical updated column by default without removing it or its filter', function () {
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)
            ->assertTableColumnExists('updated_at', fn (TextColumn $column): bool => $column->isToggledHiddenByDefault())
            ->assertTableFilterExists('updated_at');
    });

    it('renders the new filters inside the table headers', function () {
        $this->actingAs($this->purchasing);

        $this->get(route('filament.helpdesk.resources.material-sourcings.index'))
            ->assertOk()
            ->assertSee('tableFilters.sourcing_requested_at.from', false)
            ->assertSee('tableFilters.sourcing_completed_at.until', false)
            ->assertSee('tableFilters.sourcing_duration_state.value', false)
            ->assertSee('Masih Berjalan');
    });

    it('summarizes the lifecycle before the suppliers in the detail modal', function (string $page, string $user) {
        $this->completed->sourcings()->create([...durationSupplierRow(), 'submitted_by' => $this->purchasing->id]);
        $this->actingAs($this->{$user});

        Livewire::test($page)
            ->set('activeTab', $page === ListReviewSourcings::class ? 'all' : null)
            ->mountTableAction('view_sourcing', $this->completed)
            ->assertSeeInOrder(['Status Sourcing', 'Approved', 'Asal Sourcing', 'Project', 'Masuk Sourcing', '20 Oct 2026 08:00', 'Selesai Sourcing', '26 Oct 2026 14:00', 'Durasi Sourcing', '6 hari 6 jam', 'PT Tepung Jaya']);
    })->with([
        'purchasing' => [ListMaterialSourcings::class, 'purchasing'],
        'rnd' => [ListReviewSourcings::class, 'rnd'],
    ]);

    it('offers no way to edit the dates and ignores them in the supplier form', function () {
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)
            ->assertTableActionDoesNotExist('edit_sourcing_dates');

        $material = RndProductEsbMaterial::factory()->legacy()->create(['sourcing_status' => MaterialSourcingStatus::Approved, 'sourcing_completed_at' => '2026-10-26 14:00:00']);
        app(AddMaterialSupplierCandidatesAction::class)->execute($material, $this->purchasing, [[...durationSupplierRow(), 'sourcing_completed_at' => '2020-01-01']]);

        expect($material->refresh()->sourcing_completed_at)->toBeNull()
            ->and((new RndProductEsbMaterial)->isFillable('sourcing_completed_at'))->toBeFalse();
    });

    it('keeps the queues closed to users without access', function () {
        $outsider = User::factory()->create(['is_active' => true]);
        $this->actingAs($outsider);

        $this->get(route('filament.helpdesk.resources.material-sourcings.index'))->assertForbidden();
        $this->get(route('filament.helpdesk.resources.review-sourcing.index'))->assertForbidden();
        expect(MaterialSourcingResource::canViewAny())->toBeFalse()
            ->and(ReviewSourcingResource::canViewAny())->toBeFalse();
    });
});
