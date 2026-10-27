<?php

use App\Actions\Rnd\CreateStandaloneMaterialSourcingAction;
use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Resources\MaterialSourcings\Pages\ListMaterialSourcings;
use App\Filament\Helpdesk\Resources\ReviewSourcings\Pages\ListReviewSourcings;
use App\Filament\Helpdesk\Resources\ReviewSourcings\ReviewSourcingResource;
use App\Livewire\MaterialSourcingSupplierCard;
use App\Models\MaterialSourcingApproval;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProject;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $project = RndProject::create(['name' => 'Project A', 'start_date' => now(), 'end_date' => now()->addMonths(3)]);
    $product = RndProjectProduct::create(['rnd_project_id' => $project->id, 'name' => 'Produk A']);

    $this->material = RndProductEsbMaterial::create([
        'rnd_project_product_id' => $product->id,
        'category_id' => 1,
        'sub_category_id' => 1,
        'uom_id' => 1,
        'uom_name' => 'KG',
        'product_code' => 'MAT-001',
        'product_name' => 'Tepung Terigu',
        'sku' => 'SKU-MAT-001',
        'status' => 'draft',
    ]);

    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');

    $this->rnd = User::factory()->create(['is_active' => true]);
    $this->rnd->assignRole('RND_STAFF');

    $this->finance = User::factory()->create(['is_active' => true]);
    $this->finance->assignRole('FINANCE_STAFF');
});

function supplierRow(string $name, float $price): array
{
    return [
        'supplier_name' => $name,
        'brand' => null,
        'price' => $price,
        'moq' => '100 kg',
        'lead_time_days' => 7,
        'contact_name' => 'Budi',
        'contact_phone' => '08123456789',
        'notes' => null,
        'attachment_path' => null,
    ];
}

it('shows the supplier brand and expandable edit button on each supplier card', function () {
    $supplier = $this->material->sourcings()->create([
        ...supplierRow('Supplier A', 10000),
        'brand' => 'Cap Bunga',
    ]);
    $this->actingAs($this->purchasing);

    Livewire::test(MaterialSourcingSupplierCard::class, ['sourcing' => $supplier])
        ->assertSee('Cap Bunga')
        ->assertSee('Edit')
        ->assertDontSee('Simpan')
        ->call('toggleEdit')
        ->assertSee('Simpan');
});

it('excludes Barang WIP materials from the sourcing list', function () {
    $wipMaterial = RndProductEsbMaterial::create([
        'rnd_project_product_id' => $this->material->rnd_project_product_id,
        'category_id' => 20,
        'category_name' => 'Barang WIP',
        'sub_category_id' => 1,
        'uom_id' => 1,
        'uom_name' => 'KG',
        'product_code' => 'WIP-001',
        'product_name' => 'Produk WIP',
        'sku' => 'SKU-WIP-001',
        'status' => 'draft',
    ]);
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->assertCanSeeTableRecords([$this->material])
        ->assertCanNotSeeTableRecords([$wipMaterial]);
});

it('filters the sourcing index like the erp request index', function () {
    $otherProject = RndProject::create(['name' => 'Project Lain', 'start_date' => now(), 'end_date' => now()->addMonths(3)]);
    $otherProduct = RndProjectProduct::create(['rnd_project_id' => $otherProject->id, 'name' => 'Produk Lain']);
    $otherMaterial = RndProductEsbMaterial::create([
        'rnd_project_product_id' => $otherProduct->id,
        'category_id' => 1,
        'sub_category_id' => 1,
        'uom_id' => 1,
        'uom_name' => 'KG',
        'product_code' => 'MAT-OTHER',
        'product_name' => 'Gula Pasir',
        'sku' => 'SKU-MAT-OTHER',
        'status' => 'draft',
        'sourcing_status' => MaterialSourcingStatus::Approved,
    ]);
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->filterTable('project_name', ['value' => 'Project A'])
        ->assertCanSeeTableRecords([$this->material])
        ->assertCanNotSeeTableRecords([$otherMaterial])
        ->resetTableFilters()
        ->filterTable('sourcing_status', MaterialSourcingStatus::Approved->value)
        ->assertCanSeeTableRecords([$otherMaterial])
        ->assertCanNotSeeTableRecords([$this->material]);
});

it('renders sourcing filters directly inside table headers like the erp request index', function () {
    $this->actingAs($this->purchasing);

    $response = $this->get(route('filament.helpdesk.resources.material-sourcings.index'));

    $response->assertOk()
        ->assertSee('Cari project...')
        ->assertSee('Cari produk...')
        ->assertSee('Cari bahan...')
        ->assertSee('Cari kode...')
        ->assertSee('- Semua Status -');
});

it('keeps sourcing filter state out of the url', function () {
    $property = new ReflectionProperty(ListMaterialSourcings::class, 'tableFilters');

    expect($property->getAttributes(Url::class))->toBeEmpty();
});

it('lets purchasing edit suppliers from the view supplier modal', function () {
    $supplier = $this->material->sourcings()->create([
        ...supplierRow('Supplier A', 10000),
        'brand' => 'Merk Lama',
    ]);
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::Approved,
        'sourcing_selected_id' => $supplier->id,
    ]);
    $this->actingAs($this->purchasing);

    Livewire::test(MaterialSourcingSupplierCard::class, ['sourcing' => $supplier])
        ->call('toggleEdit')
        ->set('brand', 'Merk Baru')
        ->set('price', '12500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $this->material->refresh();

    expect($supplier->fresh()->brand)->toBe('Merk Baru')
        ->and((float) $supplier->fresh()->price)->toBe(12500.0)
        ->and($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcing_selected_id)->toBeNull();
});

it('opens the manage sourcing modal with one empty supplier row', function () {
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material)
        ->assertTableActionDataSet(fn (array $data): bool => count($data['sourcings'] ?? []) === 1);
});

it('uses the scrollable modal layout for manage and view supplier actions', function () {
    $this->material->sourcings()->create(supplierRow('Supplier A', 10000));
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionExists('manage_sourcing', function ($action): bool {
            return str_contains((string) ($action->getExtraModalWindowAttributes()['class'] ?? ''), 'material-sourcing-modal');
        }, $this->material)
        ->assertTableActionExists('view_sourcing', function ($action): bool {
            return str_contains((string) ($action->getExtraModalWindowAttributes()['class'] ?? ''), 'material-sourcing-modal');
        }, $this->material);
});

it('shows complete supplier information inside the rnd approval modal', function () {
    $this->material->sourcings()->create([
        ...supplierRow('Supplier Lengkap', 12500),
        'brand' => 'Merk Premium',
        'contact_name' => 'Rizki',
        'contact_phone' => '081234567890',
        'notes' => 'Sudah termasuk ongkir.',
    ]);
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);
    $this->actingAs($this->rnd);

    $supplierSelectionView = file_get_contents(resource_path('views/filament/helpdesk/material-sourcings/supplier-selection-cards.blade.php'));

    expect($supplierSelectionView)
        ->toContain('$record->sourcings')
        ->toContain('x-model="state"')
        ->toContain('supplier_name')
        ->toContain('brand')
        ->toContain('contact_name')
        ->toContain('contact_phone')
        ->toContain('notes')
        ->not->toContain('Pilih Supplier Terbaik');

    Livewire::test(ListReviewSourcings::class)
        ->assertTableActionExists('approve_rnd', function ($action): bool {
            return str_contains((string) ($action->getExtraModalWindowAttributes()['class'] ?? ''), 'material-sourcing-modal');
        }, $this->material);
});

it('uses compact icon buttons for sourcing table actions', function () {
    $this->material->sourcings()->create(supplierRow('Supplier A', 10000));
    $this->actingAs($this->purchasing);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionExists('view_sourcing', fn ($action): bool => $action->isIconButton(), $this->material)
        ->assertTableActionExists('manage_sourcing', fn ($action): bool => $action->isIconButton(), $this->material);
});

it('lets purchasing submit multiple supplier options and sends the material for rnd review', function () {
    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier A', 10000);
    $data['sourcings'][(string) Str::uuid()] = supplierRow('Supplier B', 9500);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcings)->toHaveCount(2)
        ->and($this->material->sourcings->pluck('supplier_name')->sort()->values()->all())
        ->toBe(['Supplier A', 'Supplier B']);
});

it('lets rnd select the winning supplier and approve, moving the material to finance review', function () {
    $this->material->sourcings()->createMany([
        supplierRow('Supplier A', 10000),
        supplierRow('Supplier B', 9500),
    ]);
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);
    $winner = $this->material->sourcings()->where('supplier_name', 'Supplier B')->sole();

    $this->actingAs($this->rnd);

    Livewire::test(ListReviewSourcings::class)
        ->callTableAction('approve_rnd', $this->material, data: [
            'sourcing_selected_id' => $winner->id,
            'rnd_note' => 'Harga paling kompetitif',
        ])
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingFinanceReview)
        ->and($this->material->sourcing_selected_id)->toBe($winner->id)
        ->and($this->material->rnd_reviewed_by)->toBe($this->rnd->id)
        ->and(MaterialSourcingApproval::where('stage', 'rnd')->where('action', 'approved')->exists())->toBeTrue();
});

it('lets finance approve the rnd-selected supplier, completing the sourcing', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $winner = $this->material->sourcings()->sole();
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::PendingFinanceReview,
        'sourcing_selected_id' => $winner->id,
    ]);

    $this->actingAs($this->finance);

    Livewire::test(ListMaterialSourcings::class)
        ->callTableAction('approve_finance', $this->material, data: ['finance_note' => 'Sesuai budget'])
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::Approved)
        ->and($this->material->finance_reviewed_by)->toBe($this->finance->id)
        ->and(MaterialSourcingApproval::where('stage', 'finance')->where('action', 'approved')->exists())->toBeTrue();
});

it('bounces the material back to rnd review when finance rejects', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $winner = $this->material->sourcings()->sole();
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::PendingFinanceReview,
        'sourcing_selected_id' => $winner->id,
    ]);

    $this->actingAs($this->finance);

    Livewire::test(ListMaterialSourcings::class)
        ->callTableAction('reject_finance', $this->material, data: ['finance_note' => 'Harga terlalu tinggi'])
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and(MaterialSourcingApproval::where('stage', 'finance')->where('action', 'rejected')->exists())->toBeTrue();
});

it('lets rnd reject a submission back to purchasing, who can resubmit', function () {
    // Start from a material with no prior suppliers so the "Kelola Sourcing"
    // modal's pre-fill (existing suppliers) is empty — this keeps the test
    // independent of Filament's Repeater pre-fill/merge behavior and focuses
    // purely on the reject -> resubmit status transition.
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);

    $this->actingAs($this->rnd);

    Livewire::test(ListReviewSourcings::class)
        ->callTableAction('reject_rnd', $this->material, data: ['rnd_note' => 'Perlu supplier lain'])
        ->assertHasNoTableActionErrors();

    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::Rejected);

    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier C', 8000);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcings)->toHaveCount(1)
        ->and($this->material->sourcings->first()->supplier_name)->toBe('Supplier C');
});

it('lets purchasing add another supplier after a submission is pending review', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);

    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionVisible('manage_sourcing', $this->material)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier B', 9000);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($this->material->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcings()->pluck('supplier_name')->sort()->values()->all())
        ->toBe(['Supplier A', 'Supplier B']);
});

it('reopens rnd review and clears the previous selection when adding a supplier after approval', function () {
    $existingSupplier = $this->material->sourcings()->create(supplierRow('Supplier A', 10000));
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::Approved,
        'sourcing_selected_id' => $existingSupplier->id,
        'rnd_reviewed_by' => $this->rnd->id,
        'rnd_reviewed_at' => now(),
        'finance_reviewed_by' => $this->finance->id,
        'finance_reviewed_at' => now(),
    ]);

    $this->actingAs($this->purchasing);

    $page = Livewire::test(ListMaterialSourcings::class)
        ->mountTableAction('manage_sourcing', $this->material);
    $data = $page->get('mountedActions.0.data');
    $firstRowKey = array_key_first($data['sourcings']);
    $data['sourcings'][$firstRowKey] = supplierRow('Supplier B', 9000);

    $page->set('mountedActions.0.data', $data)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $this->material->refresh();

    expect($this->material->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
        ->and($this->material->sourcing_selected_id)->toBeNull()
        ->and($this->material->rnd_reviewed_by)->toBeNull()
        ->and($this->material->finance_reviewed_by)->toBeNull()
        ->and($this->material->sourcings)->toHaveCount(2);
});

it('blocks a user without the purchasing permission from submitting sourcing', function () {
    $this->actingAs($this->rnd);

    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionHidden('manage_sourcing', $this->material);
});

it('blocks review actions for users without the matching approval permission', function () {
    $this->material->sourcings()->createMany([supplierRow('Supplier A', 10000)]);
    $this->material->update(['sourcing_status' => MaterialSourcingStatus::PendingRndReview]);

    $this->actingAs($this->finance);

    // R&D supplier approval lives only in Review Sourcing, which Finance cannot open.
    expect(ReviewSourcingResource::canViewAny())->toBeFalse();
    $this->get(ReviewSourcingResource::getUrl('index'))->assertForbidden();
    Livewire::test(ListMaterialSourcings::class)
        ->assertTableActionDoesNotExist('approve_rnd')
        ->assertTableActionDoesNotExist('reject_rnd');
});

it('keeps the submitted supplier data visible after the sourcing moves past purchasing', function () {
    $this->material->sourcings()->createMany([
        supplierRow('Supplier A', 10000),
        supplierRow('Supplier B', 9500),
    ]);
    $winner = $this->material->sourcings()->where('supplier_name', 'Supplier B')->sole();
    $this->material->update([
        'sourcing_status' => MaterialSourcingStatus::Approved,
        'sourcing_selected_id' => $winner->id,
    ]);

    // Everyone can see the submitted data, while only Purchasing can add
    // another supplier and reopen the review flow.
    foreach ([$this->purchasing, $this->rnd, $this->finance] as $user) {
        $this->actingAs($user);

        $page = Livewire::test(ListMaterialSourcings::class)
            ->assertTableActionVisible('view_sourcing', $this->material);

        if ($user->is($this->purchasing)) {
            $page->assertTableActionVisible('manage_sourcing', $this->material);
        } else {
            $page->assertTableActionHidden('manage_sourcing', $this->material);
        }
    }
});

it('renders the sourcing bahan menu link in the custom helpdesk sidebar for purchasing staff', function () {
    $this->actingAs($this->purchasing);

    $response = $this->get(route('filament.helpdesk.resources.material-sourcings.index'));

    $response->assertOk()
        ->assertSee('Sourcing Bahan')
        ->assertSee(route('filament.helpdesk.resources.material-sourcings.index'), false);
});

describe('standalone sourcing (without a Project)', function () {
    beforeEach(function () {
        config()->set('esb.master_product.base_url', 'https://master-product.test');
        config()->set('esb.master_product.token', 'master-token');
        // The picker's active-detail check is cached per product; seeding it keeps tests offline.
        Cache::put('esb.master_product.active_details.501', [9001], now()->addHour());
        Http::preventStrayRequests();
        Http::fake([
            'https://master-product.test/corev1/master/product*' => Http::response(['status' => 'ok', 'result' => [
                'page' => 1, 'limit' => 20, 'count' => 1, 'next' => '', 'prev' => '',
                'data' => [[
                    'productID' => 501,
                    'productCode' => 'BB-MATCHA',
                    'productName' => 'Matcha Uji Jepang',
                    'categoryName' => 'Bahan Baku',
                    'subCategoryName' => 'Minuman',
                    'productDetails' => [
                        ['productDetailID' => 9001, 'unit' => 'KG', 'conversionFactor' => 1, 'flagActive' => 'true'],
                    ],
                ]],
            ]]),
        ]);
    });

    function standaloneInput(array $overrides = []): array
    {
        return array_merge([
            'product_code' => 'BB-MATCHA',
            'product_detail_id' => 9001,
            'material_notes' => 'Sample dikirim langsung tanpa project.',
            'sourcings' => [supplierRow('Supplier Sample', 175000)],
        ], $overrides);
    }

    // The picker is re-rendered as a Filament partial next to the action modal, so its current
    // HTML is read from the response's partials rather than the (skipped) full page render.
    Testable::macro('sourcingPickerPartial', fn (): ?string => data_get($this->lastState->getEffects(), 'partials.sourcing-product-picker'));

    function pickerRow(array $overrides = []): array
    {
        return array_merge([
            'productDetailID' => 9001, 'productID' => 501, 'productCode' => 'BB-MATCHA', 'productName' => 'Matcha Uji Jepang',
            'categoryName' => 'Bahan Baku', 'subCategoryName' => 'Minuman', 'unit' => 'KG', 'baseUnit' => 'KG', 'conversionFactor' => 1.0,
        ], $overrides);
    }

    it('opens the same Master Product picker as BOM "Tambah Komponen" first, then the form with the picked material', function () {
        $this->actingAs($this->purchasing);

        $page = Livewire::test(ListMaterialSourcings::class)
            ->assertActionVisible('create_standalone_sourcing')
            ->callAction('create_standalone_sourcing')
            ->assertSet('inlineProductModalOpen', true)
            ->tap(fn ($page) => expect($page->sourcingPickerPartial())
                ->toContain('Pilih Bahan Sourcing')
                ->toContain('Pilih product aktif dari Master Product ESB.')
                ->toContain('wire:model.live.debounce.700ms="inlineProductCodeSearch"')
                ->toContain('wire:init="loadInlineProducts"'))
            ->set('inlineProductOptions', [9001 => pickerRow()])
            ->call('selectSourcingProduct', 9001)
            ->assertSet('inlineProductModalOpen', false)
            ->assertActionMounted('standaloneSourcingForm')
            ->assertMountedActionModalSee(['Kode Bahan', 'Nama Bahan', 'UOM Utama', 'Daftar Supplier / Pengirim Sample'])
            ->assertSchemaStateSet(['product_code' => 'BB-MATCHA', 'product_name' => 'Matcha Uji Jepang', 'uom_name' => 'KG'], 'mountedActionSchema0');

        expect($page->sourcingPickerPartial())->toContain('wire:partial="sourcing-product-picker"')
            ->not->toContain('Pilih product aktif dari Master Product ESB.');

        $data = $page->get('mountedActions.0.data');
        $firstRowKey = array_key_first($data['sourcings']);
        $data['material_notes'] = 'Sample dikirim langsung tanpa project.';
        $data['sourcings'] = [
            $firstRowKey => supplierRow('Supplier Sample', 175000),
            (string) Str::uuid() => supplierRow('Supplier Kedua', 160000),
        ];

        $page->set('mountedActions.0.data', $data)
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Sourcing mandiri dikirim untuk review RnD');

        $standalone = RndProductEsbMaterial::query()->where('sourcing_origin', RndProductEsbMaterial::SOURCING_ORIGIN_STANDALONE)->sole();

        expect($standalone->isStandalone())->toBeTrue()
            ->and($standalone->rnd_project_product_id)->toBeNull()
            ->and($standalone->product_name)->toBe('Matcha Uji Jepang')
            ->and($standalone->category_name)->toBe('Bahan Baku')
            ->and($standalone->sub_category_name)->toBe('Minuman')
            ->and($standalone->uom_name)->toBe('KG')
            ->and($standalone->esb_product_id)->toBe(501)
            ->and($standalone->esb_product_detail_id)->toBe(9001)
            ->and($standalone->esb_product_code)->toBe('BB-MATCHA')
            ->and($standalone->status)->toBe('draft')
            ->and($standalone->product_code)->toStartWith('SRC-')
            ->and($standalone->sku)->toBe($standalone->product_code)
            ->and($standalone->notes)->toBe('Sample dikirim langsung tanpa project.')
            ->and($standalone->created_by)->toBe($this->purchasing->id)
            ->and($standalone->sourcing_status)->toBe(MaterialSourcingStatus::PendingRndReview)
            ->and($standalone->sourcings->pluck('supplier_name')->sort()->values()->all())->toBe(['Supplier Kedua', 'Supplier Sample']);

        Livewire::test(ListMaterialSourcings::class)
            ->assertCanSeeTableRecords([$standalone, $this->material])
            ->assertSee('Mandiri')
            ->assertSee('Tanpa Project')
            ->assertSee('BB-MATCHA');
    });

    it('lets the user go back to the picker with "Ganti Bahan"', function () {
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)
            ->callAction([
                TestAction::make('standaloneSourcingForm')->arguments(['product_code' => 'BB-MATCHA', 'product_detail_id' => 9001]),
                TestAction::make('change_sourcing_product'),
            ])
            ->assertSet('inlineProductModalOpen', true)
            ->assertSet('mountedActions', [])
            ->tap(fn ($page) => expect($page->sourcingPickerPartial())->toContain('Pilih product aktif dari Master Product ESB.'));
    });

    it('stores what ESB returns, never the picker row the browser sent', function () {
        $this->actingAs($this->purchasing);

        $page = Livewire::test(ListMaterialSourcings::class)
            ->callAction('create_standalone_sourcing')
            ->set('inlineProductOptions', [9001 => pickerRow(['productName' => 'Nama Palsu', 'categoryName' => 'Palsu', 'unit' => 'TON', 'baseUnit' => 'TON'])])
            ->call('selectSourcingProduct', 9001);
        $data = $page->get('mountedActions.0.data');
        $data['sourcings'] = [array_key_first($data['sourcings']) => supplierRow('Supplier Sample', 175000)];
        $page->set('mountedActions.0.data', $data)->callMountedAction()->assertHasNoActionErrors();

        $standalone = RndProductEsbMaterial::query()->where('sourcing_origin', 'standalone')->sole();

        expect($standalone->product_name)->toBe('Matcha Uji Jepang')
            ->and($standalone->category_name)->toBe('Bahan Baku')
            ->and($standalone->uom_name)->toBe('KG');
    });

    it('refuses a material that ESB does not return as active for that code', function (array $overrides) {
        expect(fn () => app(CreateStandaloneMaterialSourcingAction::class)->execute(standaloneInput($overrides), $this->purchasing))
            ->toThrow(ValidationException::class, 'tidak ditemukan atau sudah tidak aktif')
            ->and(RndProductEsbMaterial::query()->where('sourcing_origin', 'standalone')->exists())->toBeFalse();
    })->with([
        'forged detail ID' => [['product_detail_id' => 12345]],
        'detail of another code' => [['product_code' => 'BB-LAIN']],
        'inactive detail' => [function () {
            Cache::put('esb.master_product.active_details.501', [], now()->addHour());

            return [];
        }],
    ]);

    it('keeps project materials marked as project sourcing', function () {
        expect($this->material->refresh()->sourcing_origin)->toBe(RndProductEsbMaterial::SOURCING_ORIGIN_PROJECT)
            ->and($this->material->isStandalone())->toBeFalse();
    });

    it('requires a picked material and at least one complete supplier', function (array $overrides, string $field) {
        try {
            app(CreateStandaloneMaterialSourcingAction::class)->execute(standaloneInput($overrides), $this->purchasing);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($field);
        }

        expect(RndProductEsbMaterial::query()->where('sourcing_origin', 'standalone')->exists())->toBeFalse();
    })->with([
        'no material' => [['product_code' => '', 'product_detail_id' => null], 'product_code'],
        'no supplier' => [['sourcings' => []], 'sourcings'],
        'supplier without price' => [['sourcings' => [[...supplierRow('Supplier X', 1), 'price' => null]]], 'sourcings.0.price'],
        'negative price' => [['sourcings' => [supplierRow('Supplier X', -5)]], 'sourcings.0.price'],
    ]);

    it('refuses users without the submit permission, in the UI and on the server', function () {
        $this->actingAs($this->rnd);

        Livewire::test(ListMaterialSourcings::class)
            ->assertActionHidden('create_standalone_sourcing')
            ->call('openSourcingProductPicker')
            ->assertForbidden();

        expect(fn () => app(CreateStandaloneMaterialSourcingAction::class)->execute(standaloneInput(), $this->rnd))
            ->toThrow(AuthorizationException::class)
            ->and(RndProductEsbMaterial::query()->where('sourcing_origin', 'standalone')->exists())->toBeFalse();
    });

    it('goes through the same rnd and finance approval as project sourcing', function () {
        $standalone = app(CreateStandaloneMaterialSourcingAction::class)->execute(standaloneInput(), $this->purchasing);
        $winner = $standalone->sourcings->sole();

        $this->actingAs($this->rnd);
        Livewire::test(ListReviewSourcings::class)
            ->callTableAction('approve_rnd', $standalone, data: ['sourcing_selected_id' => $winner->id, 'rnd_note' => 'Sample sesuai'])
            ->assertHasNoTableActionErrors();

        $this->actingAs($this->finance);
        Livewire::test(ListMaterialSourcings::class)
            ->callTableAction('approve_finance', $standalone, data: ['finance_note' => 'OK'])
            ->assertHasNoTableActionErrors();

        expect($standalone->refresh()->sourcing_status)->toBe(MaterialSourcingStatus::Approved)
            ->and($standalone->sourcing_selected_id)->toBe($winner->id)
            // The relation lists newest first; the approval order is asserted chronologically by key
            // so the result never depends on both approvals landing in the same second.
            ->and($standalone->sourcingApprovals()->reorder()->orderBy('id')->pluck('stage')->all())->toBe(['rnd', 'finance']);
    });

    it('can be sourced twice for the same ESB material, and is found by "mandiri" and its ESB code', function () {
        $first = app(CreateStandaloneMaterialSourcingAction::class)->execute(standaloneInput(), $this->purchasing);
        $second = app(CreateStandaloneMaterialSourcingAction::class)->execute(standaloneInput(), $this->purchasing);
        $this->actingAs($this->purchasing);

        expect($first->product_code)->not->toBe($second->product_code);

        Livewire::test(ListMaterialSourcings::class)
            ->filterTable('project_name', ['value' => 'Mandiri'])
            ->assertCanSeeTableRecords([$first, $second])
            ->assertCanNotSeeTableRecords([$this->material])
            ->filterTable('project_name', ['value' => ''])
            ->filterTable('product_code_filter', ['value' => 'BB-MATCHA'])
            ->assertCanSeeTableRecords([$first, $second])
            ->assertCanNotSeeTableRecords([$this->material]);
    });

    it('never hides a standalone material whose ESB category is Barang WIP', function () {
        Http::fake([
            'https://master-product.test/corev1/master/product*' => Http::response(['status' => 'ok', 'result' => [
                'page' => 1, 'limit' => 20, 'count' => 1, 'next' => '', 'prev' => '',
                'data' => [['productID' => 501, 'productCode' => 'BB-MATCHA', 'productName' => 'Adonan WIP', 'categoryName' => 'Barang WIP', 'subCategoryName' => '', 'productDetails' => [['productDetailID' => 9001, 'unit' => 'GR']]]],
            ]]),
        ]);
        $standalone = app(CreateStandaloneMaterialSourcingAction::class)->execute(standaloneInput(), $this->purchasing);
        $this->actingAs($this->purchasing);

        Livewire::test(ListMaterialSourcings::class)->assertCanSeeTableRecords([$standalone]);
    });
});
