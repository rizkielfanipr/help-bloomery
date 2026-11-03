<?php

use App\Actions\Purchasing\MaterialSourcingRequest\ConvertMaterialSourcingRequestAction;
use App\Enums\MaterialSourcingRequestStatus;
use App\Filament\Casual\Pages\LauncherPage;
use App\Filament\Casual\Pages\MaterialSourcingRequestHistoryPage;
use App\Filament\Casual\Pages\MaterialSourcingRequestPage;
use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\MaterialSourcingRequestResource;
use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\Pages\ListMaterialSourcingRequests;
use App\Filament\Helpdesk\Resources\MaterialSourcingRequests\Pages\ViewMaterialSourcingRequest;
use App\Filament\Helpdesk\Resources\MaterialSourcings\Pages\ListMaterialSourcings;
use App\Models\Branch;
use App\Models\MaterialSourcingRequest;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\EsbItemJournalService;
use App\Services\EsbService;
use App\Services\EsbStockMovementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function msrUiProduct(array $overrides = []): array
{
    return array_merge([
        'productDetailID' => 9001, 'productID' => 501, 'productCode' => 'BB-MATCHA', 'productName' => 'Matcha Uji Jepang',
        'categoryName' => 'Bahan Baku', 'subCategoryName' => 'Minuman', 'unit' => 'KG', 'baseUnit' => 'KG', 'conversionFactor' => 1.0,
    ], $overrides);
}

/** ESB as a read-only boundary for the picker and the server-side product check; stock is never read. */
function fakeMsrUiEsb(): void
{
    $journal = Mockery::mock(EsbItemJournalService::class);
    $journal->shouldReceive('products')->andReturnUsing(fn (string $company, array $filters): array => [
        'page' => $filters['page'], 'limit' => 20, 'count' => 1, 'prev' => null, 'next' => null,
        'data' => [['productCode' => 'BB-MATCHA']],
    ]);
    app()->instance(EsbItemJournalService::class, $journal);

    $esb = Mockery::mock(EsbService::class);
    $esb->shouldReceive('getActiveProductDetailsByCodes')->andReturn([9001 => msrUiProduct()]);
    app()->instance(EsbService::class, $esb);

    $stock = Mockery::mock(EsbStockMovementService::class);
    $stock->shouldNotReceive('availableBalancesForItemJournal');
    app()->instance(EsbStockMovementService::class, $stock);

    Cache::put('esb.master_product.active_details.501', [9001], now()->addHour());
    Http::preventStrayRequests();
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    fakeMsrUiEsb();

    $this->branch = Branch::factory()->create(['name' => 'Outlet Senopati']);
    $this->requester = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $this->requester->givePermissionTo('access employee app sourcing request');
    $this->processor = User::factory()->create(['is_active' => true]);
    $this->processor->givePermissionTo(['access backoffice', 'view material sourcing requests', 'process material sourcing requests', 'submit material sourcing', 'view material sourcings']);
});

describe('Employee App', function () {
    beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('casual')));

    it('shows the Request Sourcing tile only with its permission', function () {
        $this->actingAs($this->requester);
        expect(collect(Livewire::test(LauncherPage::class)->instance()->tiles())->pluck('label'))->toContain('Request Sourcing');

        $this->actingAs(User::factory()->create(['is_active' => true]));
        expect(collect(Livewire::test(LauncherPage::class)->instance()->tiles())->pluck('label'))->not->toContain('Request Sourcing');
    });

    it('refuses the form and history pages without the permission', function () {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        Livewire::test(MaterialSourcingRequestPage::class)->assertForbidden();
        Livewire::test(MaterialSourcingRequestHistoryPage::class)->assertForbidden();
    });

    it('picks one product without stock and submits the request', function () {
        $this->actingAs($this->requester);

        $page = Livewire::test(MaterialSourcingRequestPage::class)
            ->assertSee('Request Sourcing')
            ->assertSee('Outlet Senopati')
            ->assertSee('Tambah Produk')
            ->call('openProductPicker')
            ->assertSet('productPickerOpen', true)
            ->call('loadProducts')
            ->assertSee('Matcha Uji Jepang')
            ->assertDontSee('Stok saat ini')
            ->call('selectProduct', 9001)
            ->assertSet('productPickerOpen', false)
            ->assertSet('productCode', 'BB-MATCHA')
            ->assertSee('Ganti Produk')
            ->set('reason', 'Supplier lama berhenti produksi.')
            ->set('estimatedQuantity', '10')
            ->set('attachments', [UploadedFile::fake()->image('kemasan.jpg')])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSee('Request Sourcing terkirim')
            ->assertDontSee('WhatsApp');

        $request = MaterialSourcingRequest::query()->sole();
        expect($page->get('requestNumber'))->toBe($request->request_number)
            ->and($request->branch_id)->toBe($this->branch->id)
            ->and($request->attachmentList())->toHaveCount(1);
    });

    it('keeps the input and shows field errors when validation fails', function () {
        $this->actingAs($this->requester);

        Livewire::test(MaterialSourcingRequestPage::class)
            ->set('reason', 'Supplier lama berhenti produksi.')
            ->set('notes', 'Grade ceremonial')
            ->call('submit')
            ->assertHasErrors(['productCode'])
            ->assertSet('reason', 'Supplier lama berhenti produksi.')
            ->assertSet('notes', 'Grade ceremonial')
            ->assertSet('submitted', false);

        expect(MaterialSourcingRequest::query()->count())->toBe(0);
    });

    it('blocks a requester without a branch', function () {
        $this->requester->update(['branch_id' => null]);
        $this->actingAs($this->requester);

        Livewire::test(MaterialSourcingRequestPage::class)
            ->assertSee('Cabang belum diatur')
            ->call('openProductPicker')->call('loadProducts')->call('selectProduct', 9001)
            ->set('reason', 'Supplier lama berhenti produksi.')
            ->call('submit')
            ->assertHasErrors(['branch']);

        expect(MaterialSourcingRequest::query()->count())->toBe(0);
    });

    it('lists only the requester\'s own requests without supplier data', function () {
        $own = MaterialSourcingRequest::factory()->inReview($this->processor)->create(['requester_id' => $this->requester->id, 'product_name' => 'Matcha Milik Saya']);
        MaterialSourcingRequest::factory()->create(['product_name' => 'Gula Cabang Lain']);
        app(ConvertMaterialSourcingRequestAction::class)->execute($this->processor, $own, [
            'sourcings' => [['supplier_name' => 'PT Rahasia Harga', 'price' => 987654, 'brand' => null, 'moq' => null, 'lead_time_days' => null, 'contact_name' => null, 'contact_phone' => null, 'notes' => null, 'attachment_path' => null]],
        ]);
        $this->actingAs($this->requester);

        Livewire::test(MaterialSourcingRequestHistoryPage::class)
            ->assertSee('Matcha Milik Saya')
            ->assertSee('Menjadi Sourcing')
            ->assertDontSee('Gula Cabang Lain')
            ->assertDontSee('PT Rahasia Harga')
            ->assertDontSee('987');
    });

    it('lets the requester cancel a submitted request with a reason', function () {
        $request = MaterialSourcingRequest::factory()->create(['requester_id' => $this->requester->id]);
        $this->actingAs($this->requester);

        Livewire::test(MaterialSourcingRequestHistoryPage::class)
            ->call('startCancel', $request->id)
            ->set('cancelReason', 'x')
            ->call('cancelRequest')
            ->assertHasErrors(['cancelReason'])
            ->set('cancelReason', 'Salah pilih produk.')
            ->call('cancelRequest')
            ->assertHasNoErrors()
            ->assertSee('Dibatalkan');

        expect($request->refresh()->status)->toBe(MaterialSourcingRequestStatus::Cancelled);
    });

    it('opens a returned request for correction and resubmits it', function () {
        $request = MaterialSourcingRequest::factory()->status(MaterialSourcingRequestStatus::RevisionRequested)
            ->create(['requester_id' => $this->requester->id, 'last_decision_reason' => 'Lampirkan foto kemasan.']);
        $this->actingAs($this->requester);

        Livewire::withQueryParams(['request' => $request->id])->test(MaterialSourcingRequestPage::class)
            ->assertSee('Lampirkan foto kemasan.')
            ->assertSee('Kirim Ulang Request')
            ->assertSet('productCode', 'BB-MATCHA')
            ->set('attachments', [UploadedFile::fake()->image('kemasan.jpg')])
            ->call('submit')
            ->assertHasNoErrors();

        expect($request->refresh()->status)->toBe(MaterialSourcingRequestStatus::Submitted)
            ->and($request->attachmentList())->toHaveCount(1);
    });

    it('does not open another requester\'s request for correction', function () {
        $foreign = MaterialSourcingRequest::factory()->status(MaterialSourcingRequestStatus::RevisionRequested)->create();
        $this->actingAs($this->requester);

        Livewire::withQueryParams(['request' => $foreign->id])->test(MaterialSourcingRequestPage::class)->assertNotFound();
    });
});

it('serves attachments only to the requester and Purchasing viewers', function () {
    $request = MaterialSourcingRequest::factory()->create(['requester_id' => $this->requester->id]);
    $request->forceFill(['attachments' => [['path' => "purchasing/material-sourcing-requests/{$request->id}/file.pdf", 'name' => 'penawaran.pdf', 'mime' => 'application/pdf', 'size' => 9]]])->save();
    Storage::disk('b2')->put("purchasing/material-sourcing-requests/{$request->id}/file.pdf", 'pdf-bytes');
    $url = route('material-sourcing-request-attachments.show', ['sourcingRequest' => $request->id, 'index' => 0]);

    $this->actingAs($this->requester)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->processor)->get($url)->assertOk();

    $other = User::factory()->create(['is_active' => true]);
    $other->givePermissionTo('access employee app sourcing request');
    $this->actingAs($other)->get($url)->assertForbidden();
    $this->actingAs($this->requester)->get(route('material-sourcing-request-attachments.show', ['sourcingRequest' => $request->id, 'index' => 3]))->assertNotFound();
});

describe('Back Office', function () {
    beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('helpdesk')));

    it('lists actionable requests by default without N+1 queries', function () {
        $converted = MaterialSourcingRequest::factory()->status(MaterialSourcingRequestStatus::Converted)->create(['product_name' => 'Sudah Dikonversi']);
        MaterialSourcingRequest::factory()->count(3)->inReview($this->processor)->create();
        $this->actingAs($this->processor);

        DB::enableQueryLog();
        Livewire::test(ListMaterialSourcingRequests::class)
            ->assertCanSeeTableRecords(MaterialSourcingRequest::query()->actionable()->get())
            ->assertCanNotSeeTableRecords([$converted]);
        $fewQueries = count(DB::getQueryLog());

        MaterialSourcingRequest::factory()->count(12)->inReview($this->processor)->create();
        DB::flushQueryLog();
        Livewire::test(ListMaterialSourcingRequests::class);
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($fewQueries + 2);
        DB::disableQueryLog();

        Livewire::test(ListMaterialSourcingRequests::class)
            ->filterTable('status', [MaterialSourcingRequestStatus::Converted->value])
            ->assertCanSeeTableRecords([$converted]);

        expect(MaterialSourcingRequestResource::getNavigationBadge())->toBe('15');
    });

    it('hides the queue from users without the view permission', function () {
        $this->actingAs($this->requester);

        Livewire::test(ListMaterialSourcingRequests::class)->assertForbidden();
    });

    it('runs claim and conversion from the detail page and links the sourcing back', function () {
        $request = MaterialSourcingRequest::factory()->create(['requester_id' => $this->requester->id]);
        $this->actingAs($this->processor);

        $page = Livewire::test(ViewMaterialSourcingRequest::class, ['record' => $request->getKey()])
            ->assertActionVisible('claim')
            ->assertActionHidden('convert')
            ->callAction('claim')
            ->assertActionVisible('convert')
            ->assertActionVisible('requestRevision')
            ->assertActionVisible('reject');

        $page->mountAction('convert')
            ->assertSchemaStateSet(['product_code' => 'BB-MATCHA', 'product_name' => 'Matcha Uji Jepang', 'uom_name' => 'KG'], 'mountedActionSchema0');
        $data = $page->get('mountedActions.0.data');
        $data['sourcings'] = [(string) array_key_first($data['sourcings']) => [
            'supplier_name' => 'PT Matcha Nusantara', 'brand' => null, 'price' => 450000, 'moq' => '5 kg', 'lead_time_days' => 7,
            'contact_name' => 'Rina', 'contact_phone' => '0812', 'notes' => null, 'attachment_path' => null,
        ]];
        $page->set('mountedActions.0.data', $data)->callMountedAction()->assertHasNoActionErrors();

        $material = RndProductEsbMaterial::query()->sole();
        expect($request->refresh()->status)->toBe(MaterialSourcingRequestStatus::Converted)
            ->and($request->rnd_product_esb_material_id)->toBe($material->id);

        Livewire::test(ListMaterialSourcings::class)
            ->assertCanSeeTableRecords([$material])
            ->assertSee('Request User')
            ->assertSee($request->request_number);
    });

    it('shows a processor without the PIC role only the start action', function () {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo(['access backoffice', 'view material sourcing requests']);
        $request = MaterialSourcingRequest::factory()->inReview($this->processor)->create();
        $this->actingAs($viewer);

        Livewire::test(ViewMaterialSourcingRequest::class, ['record' => $request->getKey()])
            ->assertSee($request->request_number)
            ->assertActionHidden('claim')
            ->assertActionHidden('convert')
            ->assertActionHidden('requestRevision')
            ->assertActionHidden('reject');
    });

    it('shows SUPERADMIN only the actions the request status allows', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('SUPERADMIN');
        $revision = MaterialSourcingRequest::factory()->status(MaterialSourcingRequestStatus::RevisionRequested)->create();
        $submitted = MaterialSourcingRequest::factory()->create();
        $this->actingAs($admin);

        Livewire::test(ViewMaterialSourcingRequest::class, ['record' => $revision->getKey()])
            ->assertActionHidden('claim')
            ->assertActionHidden('convert')
            ->assertActionHidden('requestRevision')
            ->assertActionHidden('reject');

        Livewire::test(ViewMaterialSourcingRequest::class, ['record' => $submitted->getKey()])
            ->assertActionVisible('claim')
            ->assertActionHidden('convert')
            ->assertActionHidden('reject');
    });

    it('requires a reason to request revision', function () {
        $request = MaterialSourcingRequest::factory()->inReview($this->processor)->create(['requester_id' => $this->requester->id]);
        $this->actingAs($this->processor);

        Livewire::test(ViewMaterialSourcingRequest::class, ['record' => $request->getKey()])
            ->callAction('requestRevision', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        expect($request->refresh()->status)->toBe(MaterialSourcingRequestStatus::InReview);

        Livewire::test(ViewMaterialSourcingRequest::class, ['record' => $request->getKey()])
            ->callAction('requestRevision', ['reason' => 'Lampirkan foto kemasan.'])
            ->assertHasNoActionErrors();

        expect($request->refresh()->status)->toBe(MaterialSourcingRequestStatus::RevisionRequested);
    });
});
