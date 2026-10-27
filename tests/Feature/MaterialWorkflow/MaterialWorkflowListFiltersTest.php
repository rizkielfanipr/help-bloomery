<?php

use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialReviewStatus;
use App\Filament\Helpdesk\Resources\MaterialPricelists\Pages\ListMaterialPricelists;
use App\Filament\Helpdesk\Resources\MaterialReviews\Pages\ListMaterialReviews;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

it('renders the material review filters inline in the column headers', function () {
    $it = User::factory()->create(['is_active' => true]);
    $it->assignRole('IT_STAFF');
    $waiting = RndProductEsbMaterial::factory()->waitingItReview()->create(['product_code' => 'BBMK-ALPHA', 'product_name' => 'Tepung Alpha']);
    $otherWaiting = RndProductEsbMaterial::factory()->waitingItReview()->create(['product_code' => 'BBMK-BETA', 'product_name' => 'Gula Beta']);
    $draft = RndProductEsbMaterial::factory()->create(['review_status' => MaterialReviewStatus::Draft]);

    $this->actingAs($it);
    Livewire::test(ListMaterialReviews::class)
        ->assertSeeHtml('wire:model.live.debounce.500ms="tableFilters.search.value"')
        ->assertDontSeeHtml('tableFilters.needs_action')
        ->assertSeeHtml('wire:model.live="tableFilters.review_status.value"')
        ->assertSeeHtml("entangle('tableFilters.submitted_at.from')")
        ->assertSee('Review Data')
        // The page opens on the unfiltered "All" tab; "Needs Action" holds the IT queue.
        ->assertSet('activeTab', 'all')
        ->assertCanSeeTableRecords([$waiting, $otherWaiting, $draft])
        ->set('activeTab', 'needs_action')
        ->assertCanSeeTableRecords([$waiting, $otherWaiting])
        ->assertCanNotSeeTableRecords([$draft])
        ->set('tableFilters.search.value', 'alpha')
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$otherWaiting]);
});

it('renders the Product Pricelist filters inline with All, Needs Action and Priced tabs', function () {
    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $ready = RndMaterialPricingRequest::factory()->create(['status' => MaterialPricingStatus::ReadyForPricing]);
    $draft = RndMaterialPricingRequest::factory()->create(['status' => MaterialPricingStatus::DraftPricing]);
    $priced = RndMaterialPricingRequest::factory()->create(['status' => MaterialPricingStatus::Priced]);

    $this->actingAs($finance);
    Livewire::test(ListMaterialPricelists::class)
        ->assertSeeHtml('wire:model.live="tableFilters.status.value"')
        ->assertSeeHtml('wire:model.live="tableFilters.supplier_name.value"')
        ->assertSeeHtml('wire:model.live="tableFilters.category.value"')
        ->assertTableColumnExists('material.category_name')
        ->assertTableColumnStateSet('material.category_name', $ready->material->category_name, $ready)
        ->assertSeeHtml("entangle('tableFilters.queued_at.from')")
        ->assertSee('Product Pricelist')
        // The page opens on the unfiltered "All" tab.
        ->assertSet('activeTab', 'all')
        ->assertSet('tableFilters.status.value', null)
        ->assertCanSeeTableRecords([$ready, $draft, $priced])
        ->set('activeTab', 'needs_action')
        ->assertCanSeeTableRecords([$ready, $draft])
        ->assertCanNotSeeTableRecords([$priced])
        ->set('activeTab', 'priced')
        ->assertCanSeeTableRecords([$priced])
        ->assertCanNotSeeTableRecords([$ready, $draft])
        ->set('activeTab', 'all')
        ->set('tableFilters.status.value', MaterialPricingStatus::Priced->value)
        ->assertCanSeeTableRecords([$priced])
        ->assertCanNotSeeTableRecords([$ready, $draft])
        ->set('tableFilters.status.value', null)
        ->set('tableFilters.search.value', $draft->material->product_code)
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$ready, $priced]);
});
