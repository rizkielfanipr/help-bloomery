<?php

use App\Actions\Rnd\Material\AddMaterialSupplierCandidatesAction;
use App\Actions\Rnd\Material\ConfirmMaterialSampleReceiptAction;
use App\Actions\Rnd\Material\HandOverMaterialSampleAction;
use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use App\Filament\Helpdesk\Resources\MaterialSourcings\Pages\ListMaterialSourcings;
use App\Filament\Helpdesk\Resources\ReviewSourcings\Pages\ListReviewSourcings;
use App\Filament\Helpdesk\Resources\ReviewSourcings\ReviewSourcingResource;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Notifications\MaterialWorkflowNotification;
use App\Services\Rnd\Material\MaterialWorkflowNotifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');
    $this->rnd = User::factory()->create(['is_active' => true]);
    $this->rnd->assignRole('RND_STAFF');
    $this->finance = User::factory()->create(['is_active' => true]);
    $this->finance->assignRole('FINANCE_STAFF');
    $this->material = reviewSourcingMaterial($this->rnd);
});

function reviewSourcingMaterial(User $rnd): RndProductEsbMaterial
{
    return RndProductEsbMaterial::factory()->synced(8100, 9100)->create([
        'created_by' => $rnd->id,
        'sourcing_status' => MaterialSourcingStatus::WaitingPurchasing,
        'sourcing_requested_at' => now(),
    ]);
}

function reviewSourcingHandOver(RndProductEsbMaterial $material, User $purchasing): void
{
    app(AddMaterialSupplierCandidatesAction::class)->execute($material, $purchasing, [[
        'supplier_name' => 'PT Tepung Jaya', 'brand' => null, 'price' => 12500, 'moq' => '50 kg', 'lead_time_days' => 5,
        'contact_name' => 'Andi', 'contact_phone' => '081200000000', 'notes' => null, 'attachment_path' => null,
    ]]);
    app(HandOverMaterialSampleAction::class)->execute($material->refresh(), $purchasing, [
        'material_sourcing_id' => $material->sourcings()->value('id'), 'quantity' => 2, 'uom_name' => 'KG',
        'photos' => [UploadedFile::fake()->image('handover.jpg')],
    ]);
}

it('shows sourcing waiting for R&D under Needs Action and everything that reached R&D under All', function () {
    reviewSourcingHandOver($this->material, $this->purchasing);
    $waitingPurchasing = reviewSourcingMaterial($this->rnd);
    $legacyPending = RndProductEsbMaterial::factory()->legacy()->create(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);
    $legacyApproved = RndProductEsbMaterial::factory()->legacy()->create(['sourcing_status' => MaterialSourcingStatus::Approved]);

    $this->actingAs($this->rnd);
    Livewire::test(ListReviewSourcings::class)
        ->assertCanSeeTableRecords([$this->material, $legacyPending])
        ->assertCanNotSeeTableRecords([$waitingPurchasing, $legacyApproved])
        ->assertTableActionVisible('receive_sample', $this->material)
        ->assertTableActionHidden('evaluate_sample', $this->material)
        ->assertTableActionVisible('approve_rnd', $legacyPending)
        ->assertSeeHtml('wire:model.live="tableFilters.sourcing_status.value"')
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$this->material, $legacyPending, $legacyApproved])
        ->assertCanNotSeeTableRecords([$waitingPurchasing]);
});

it('lets R&D evaluate a received sample in Review Sourcing, sending the supplier to Finance', function () {
    reviewSourcingHandOver($this->material, $this->purchasing);
    app(ConfirmMaterialSampleReceiptAction::class)->execute($this->material->refresh(), $this->rnd, [
        'received_quantity' => 2, 'received_condition' => 'good', 'photos' => [UploadedFile::fake()->image('receipt.png')],
    ]);

    $this->actingAs($this->rnd);
    Livewire::test(ListReviewSourcings::class)
        ->set('activeTab', 'to_evaluate')
        ->assertTableActionHidden('receive_sample', $this->material)
        ->callTableAction('evaluate_sample', $this->material, data: ['decision' => RndMaterialSampleCycle::EVALUATION_APPROVE])
        ->assertHasNoTableActionErrors();

    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::ApprovedByRnd)
        ->and(RndMaterialPricingRequest::query()->sole()->supplier_name)->toBe('PT Tepung Jaya');
});

it('keeps Sourcing Bahan for Purchasing work only', function () {
    reviewSourcingHandOver($this->material, $this->purchasing);
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionExists('hand_over_sample')
        ->assertTableActionDoesNotExist('receive_sample')
        ->assertTableActionDoesNotExist('evaluate_sample')
        ->assertTableActionDoesNotExist('reopen_sample_approval')
        ->assertTableActionDoesNotExist('approve_rnd')
        ->assertTableActionDoesNotExist('reject_rnd');

    $this->get(ReviewSourcingResource::getUrl('index'))->assertForbidden();
    $this->actingAs($this->finance)->get(ReviewSourcingResource::getUrl('index'))->assertForbidden();
});

it('shows Review Sourcing in the R&D sidebar only to R&D', function () {
    $this->actingAs($this->rnd)->get(ReviewSourcingResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Review Sourcing');

    $this->actingAs($this->purchasing)->get(MaterialSourcingResource::getUrl('index'))
        ->assertSuccessful()
        ->assertDontSee('Review Sourcing');
});

it('links each notification to the work queue of its recipient', function () {
    Notification::fake();

    reviewSourcingHandOver($this->material, $this->purchasing);

    Notification::assertSentTo($this->rnd, MaterialWorkflowNotification::class, fn (MaterialWorkflowNotification $notification): bool => $notification->event === 'sample_handed_over'
        && $notification->url === ReviewSourcingResource::getUrl('index', panel: 'helpdesk'));
    $notifier = app(MaterialWorkflowNotifier::class);
    expect($notifier->queueUrl($this->material, 'sourcing_requested'))->toBe(MaterialSourcingResource::getUrl('index', panel: 'helpdesk'))
        ->and($notifier->queueUrl($this->material, 'pricing_ready'))->toContain('/pricelist-bahan')
        ->and($notifier->queueUrl($this->material, 'review_submitted'))->toContain('/material-reviews/')
        ->and($notifier->queueUrl($this->material, 'revision_required'))
        ->toBe(ViewProjectProductPage::getUrl(['project' => $this->material->product->rnd_project_id, 'product' => $this->material->rnd_project_product_id], panel: 'helpdesk'));
});
