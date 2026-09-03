<?php

use App\Enums\MarketingMaterialFulfillmentStatus;
use App\Enums\MaterialSourcingStatus;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\Projects\Pages\CreateProject;
use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\PrefixCategory;
use App\Models\RndBomInstruction;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\RndProjectProduct;
use App\Models\SalesRegion;
use App\Models\User;
use App\Services\EsbCoreService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('renders the R&D project list and create pages', function () {
    Livewire::test(ListProjects::class)
        ->assertSee('Project')
        ->assertSee('Buat Project');

    Livewire::test(CreateProject::class)
        ->assertSee('Informasi Project')
        ->assertDontSee('Timeline Project');
});

it('creates a project', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'name' => 'Seasonal Product Development',
            'description' => 'Project pengembangan menu seasonal.',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = RndProject::query()->where('name', 'Seasonal Product Development')->firstOrFail();

    expect($project->description)->toBe('Project pengembangan menu seasonal.')
        ->and($project->start_date->toDateString())->toBe('2026-08-01')
        ->and($project->end_date->toDateString())->toBe('2026-08-31');
});

it('creates and updates a product release with online and offline prices', function () {
    Storage::fake('b2');
    $project = RndProject::query()->create([
        'name' => 'New Beverage Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $regionalPrices = SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->get()
        ->map(fn (SalesRegion $region, int $index): array => [
            'region_id' => $region->id,
            'region_name' => $region->name,
            'region_code' => $region->code,
            'offline_price' => (string) (32000 + ($index * 1000)),
            'online_price' => (string) (36000 + ($index * 1000)),
        ])->all();
    $projectionRegion = SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

    $page = Livewire::test(ViewProject::class, ['record' => $project->id])
        ->set('productName', 'Matcha Strawberry')
        ->set('productCode', 'PRD-MTC-001')
        ->set('productDescription', 'Seasonal beverage.')
        ->set('priceEffectiveFrom', '2026-08-01')
        ->set('regionalPrices', $regionalPrices)
        ->set('releaseDate', '2026-09-01')
        ->set('productStatus', 'development')
        ->set('shelfLifeValue', '6')
        ->set('shelfLifeUnit', 'month')
        ->set('storageCondition', 'chiller')
        ->set('storageNotes', 'Simpan pada suhu 2–5°C.')
        ->set('targetOutlets', '50')
        ->set('salesProjections', [[
            'id' => null,
            'projection_month' => '2026-09',
            'sales_region_id' => $projectionRegion->id,
            'channel' => 'offline',
            'target_quantity' => '5000',
            'target_revenue' => '250000000',
            'target_outlets' => '50',
            'notes' => 'Projection peluncuran awal.',
        ]])
        ->set('productPhoto', UploadedFile::fake()->image('matcha-product.jpg', 800, 800))
        ->call('saveProduct')
        ->assertHasNoErrors()
        ->assertSee('Matcha Strawberry');

    $product = $project->products()->where('product_code', 'PRD-MTC-001')->firstOrFail();
    $originalImagePath = $product->image_path;
    Storage::disk('b2')->assertExists($originalImagePath);

    $page->call('editProduct', $product->id)
        ->set('priceEffectiveFrom', '2026-08-01')
        ->set('regionalPrices.0.online_price', '38000')
        ->set('productStatus', 'trial')
        ->set('productPhoto', UploadedFile::fake()->image('matcha-product-new.png', 900, 900))
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product->refresh();
    expect((float) $product->offline_price)->toBe(32000.0)
        ->and((float) $product->online_price)->toBe(37000.0)
        ->and($product->status)->toBe('trial')
        ->and($product->shelf_life_value)->toBe(6)
        ->and($product->shelf_life_unit)->toBe('month')
        ->and($product->storage_condition)->toBe('chiller')
        ->and($product->target_outlets)->toBe(50)
        ->and($product->image_path)->not->toBe($originalImagePath);
    $projection = $product->salesProjections()->firstOrFail();
    expect($projection->projection_month->toDateString())->toBe('2026-09-01')
        ->and((float) $projection->target_quantity)->toBe(5000.0)
        ->and((float) $projection->target_revenue)->toBe(250000000.0);
    Storage::disk('b2')->assertMissing($originalImagePath);
    Storage::disk('b2')->assertExists($product->image_path);
    $this->assertDatabaseHas('rnd_product_regional_prices', [
        'rnd_project_product_id' => $product->id,
        'sales_region_id' => $regionalPrices[0]['region_id'],
        'online_price' => 38000,
    ]);
    expect($product->regionalPrices()
        ->where('sales_region_id', $regionalPrices[0]['region_id'])
        ->firstOrFail()
        ->effective_from
        ->toDateString())->toBe('2026-08-01');
});

it('requires shelf life and a sales projection before a product is ready', function () {
    Storage::fake('b2');
    $project = RndProject::query()->create([
        'name' => 'Ready Validation Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $regionalPrices = SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->get()
        ->map(fn (SalesRegion $region): array => [
            'region_id' => $region->id,
            'region_name' => $region->name,
            'region_code' => $region->code,
            'offline_price' => '32000',
            'online_price' => '36000',
        ])->all();

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->set('productName', 'Ready Product')
        ->set('priceEffectiveFrom', '2026-08-01')
        ->set('regionalPrices', $regionalPrices)
        ->set('releaseDate', '2026-09-01')
        ->set('productStatus', 'ready')
        ->call('saveProduct')
        ->assertHasErrors(['shelfLifeValue', 'salesProjections']);

    expect($project->products()->count())->toBe(0);
});

it('rejects duplicate sales projection periods for the same region and channel', function () {
    $project = RndProject::query()->create([
        'name' => 'Projection Validation Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $regions = SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->get();
    $regionalPrices = $regions->map(fn (SalesRegion $region): array => [
        'region_id' => $region->id,
        'region_name' => $region->name,
        'region_code' => $region->code,
        'offline_price' => '32000',
        'online_price' => '36000',
    ])->all();
    $duplicateProjection = [
        'id' => null,
        'projection_month' => '2026-09',
        'sales_region_id' => $regions->firstOrFail()->id,
        'channel' => 'online',
        'target_quantity' => '100',
        'target_revenue' => '3600000',
        'target_outlets' => '5',
        'notes' => '',
    ];

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->set('productName', 'Duplicate Projection Product')
        ->set('priceEffectiveFrom', '2026-08-01')
        ->set('regionalPrices', $regionalPrices)
        ->set('productStatus', 'development')
        ->set('salesProjections', [$duplicateProjection, $duplicateProjection])
        ->call('saveProduct')
        ->assertHasErrors(['salesProjections']);

    expect($project->products()->count())->toBe(0);
});

it('uploads and deletes product marketing materials on Cloudflare storage', function () {
    Storage::fake('b2');
    $project = RndProject::query()->create([
        'name' => 'Packaging Development',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Premium Cookies',
        'offline_price' => 50000,
        'online_price' => 55000,
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);

    $page = Livewire::test(ViewProjectProductPage::class, ['project' => $project->id, 'product' => $product->id])
        ->call('openMaterialForm')
        ->set('materialType', 'packaging_design')
        ->set('materialTitle', 'Final Packaging Design')
        ->set('materialNotes', 'Versi cetak pertama.')
        ->set('materialFile', UploadedFile::fake()->create('packaging.pdf', 200, 'application/pdf'))
        ->call('saveMaterial')
        ->assertHasNoErrors()
        ->assertSee('Final Packaging Design')
        ->assertSee('Marketing Materials')
        ->assertSee('Download');

    $material = $product->marketingMaterials()->firstOrFail();
    Storage::disk('b2')->assertExists($material->file_path);
    $this->assertDatabaseHas('rnd_project_marketing_materials', [
        'rnd_project_product_id' => $product->id,
        'type' => 'packaging_design',
        'title' => 'Final Packaging Design',
    ]);

    $page->call('deleteMaterial', $material->id)->assertHasNoErrors();
    Storage::disk('b2')->assertMissing($material->file_path);
    $this->assertDatabaseMissing('rnd_project_marketing_materials', ['id' => $material->id]);
});

it('shows marketing fulfillment and approved supplier details on the product page', function () {
    Storage::fake('b2');
    $project = RndProject::query()->create([
        'name' => 'Product Monitoring Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Monitoring Product',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $material = $product->marketingMaterials()->create([
        'type' => 'packaging_design',
        'title' => 'Packaging Box',
        'file_path' => 'rnd/marketing-materials/packaging.pdf',
        'original_name' => 'packaging.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'created_by' => auth()->id(),
    ]);
    $material->fulfillment()->create([
        'status' => MarketingMaterialFulfillmentStatus::Ordered,
        'vendor_name' => 'CV Print Bloomery',
        'order_date' => '2026-09-01',
        'ordered_by' => auth()->id(),
        'ordered_at' => now(),
    ]);
    $esbMaterial = RndProductEsbMaterial::query()->create([
        'rnd_project_product_id' => $product->id,
        'category_id' => 1,
        'sub_category_id' => 1,
        'uom_id' => 1,
        'uom_name' => 'KG',
        'product_code' => 'MAT-APPROVED',
        'product_name' => 'Approved Flour',
        'sku' => 'MAT-APPROVED-KG',
        'status' => 'draft',
        'sourcing_status' => MaterialSourcingStatus::Approved,
    ]);
    $supplier = $esbMaterial->sourcings()->create([
        'supplier_name' => 'PT Supplier Terpilih',
        'price' => 12500,
        'moq' => '100 kg',
        'lead_time_days' => 7,
        'contact_name' => 'Budi',
        'contact_phone' => '08123456789',
        'notes' => 'Harga sudah termasuk pengiriman.',
        'submitted_by' => auth()->id(),
    ]);
    $esbMaterial->update(['sourcing_selected_id' => $supplier->id]);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])
        ->assertSee('Sudah Dipesan')
        ->assertSee('CV Print Bloomery')
        ->assertSee('Disetujui')
        ->assertSee('PT Supplier Terpilih')
        ->assertSee('100 kg')
        ->assertSee('08123456789');
});

it('stores a new material draft and creates its Master Product in ESB', function () {
    Cache::forget('esb_core.access_token');
    Http::fake([
        'https://services.esb.co.id/core/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token-test'],
        ]),
        'https://services.esb.co.id/core/product' => Http::response([
            'status' => 'ok',
            'code' => 'EC03100000',
            'message' => 'OK',
            'result' => ['productID' => 9150, 'isTemp' => false],
            'errors' => null,
        ]),
    ]);

    config()->set('esb.core.base_url', 'https://services.esb.co.id/core');
    config()->set('esb.core.username', 'rnd-test');
    config()->set('esb.core.password', 'secret');

    $project = RndProject::query()->create([
        'name' => 'New Material Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Matcha Product Release',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);

    $page = Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])->set('esbMaterialProductName', 'Matcha Powder Premium')
        ->set('esbMaterialProductCode', 'BBMK-MATCHA-01')
        ->set('esbMaterialCategoryId', 11)
        ->set('esbMaterialSubCategoryId', 21)
        ->set('esbMaterialUnits', [
            [
                'uom_id' => 5,
                'uom_name' => 'GR',
                'sku' => '',
                'conversion_factor' => '1',
                'base_price' => '125',
                'is_base' => true,
            ],
            [
                'uom_id' => 16,
                'uom_name' => 'Resep',
                'sku' => '',
                'conversion_factor' => '300',
                'base_price' => '37500',
                'is_base' => false,
            ],
        ])
        ->set('esbMaterialNotes', 'Bahan untuk menu seasonal.')
        ->call('saveEsbMaterial')
        ->assertHasNoErrors()
        ->assertSee('Matcha Powder Premium')
        ->assertSee('Create to ESB');

    $material = $product->esbMaterials()->firstOrFail();
    expect($material->status)->toBe('draft')
        ->and($material->sku)->toBe('BBMK-MATCHA-01-GR');
    expect($material->units()->count())->toBe(2)
        ->and($material->units()->where('is_base', true)->value('is_sales'))->toBeTrue();

    $page->call('syncEsbMaterial', $material->id)->assertHasNoErrors();

    $material->refresh();
    expect($material->status)->toBe('synced')
        ->and($material->esb_product_id)->toBe(9150)
        ->and($material->synced_at)->not->toBeNull();

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://services.esb.co.id/core/product') {
            return false;
        }

        return $request['productName'] === 'Matcha Powder Premium'
            && $request['categoryID'] === 11
            && data_get($request->data(), 'productDetails.0.uomID') === 5
            && data_get($request->data(), 'productDetails.0.sku') === 'BBMK-MATCHA-01-GR'
            && data_get($request->data(), 'productDetails.0.qty') === 1.0
            && data_get($request->data(), 'productDetails.0.isSales') === true
            && data_get($request->data(), 'productDetails.1.uomID') === 16
            && data_get($request->data(), 'productDetails.1.sku') === 'BBMK-MATCHA-01-RESEP'
            && data_get($request->data(), 'productDetails.1.qty') === 300.0;
    });

    $this->get(route('helpdesk.rnd-products.esb-materials-export', [
        'project' => $project->id,
        'product' => $product->id,
        'format' => 'xlsx',
    ]))->assertOk();
});

it('allows a WIP material to use a direct product name without a prefix', function () {
    $project = RndProject::query()->create([
        'name' => 'Non Prefix Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Direct WIP Product Release',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])->set('esbCategoryOptions', [77 => 'Barang WIP'])
        ->set('esbMaterialCategoryId', 77)
        ->set('esbMaterialSubCategoryId', 21)
        ->set('esbMaterialPrefixCategoryId', ViewProjectProductPage::NON_PREFIX_CATEGORY_ID)
        ->set('esbMaterialProductName', 'Adonan Croissant Khusus')
        ->set('esbMaterialProductCode', 'BBMK-WIP-01')
        ->set('esbMaterialUnits', [[
            'uom_id' => 5,
            'uom_name' => 'GR',
            'sku' => '',
            'conversion_factor' => '1',
            'base_price' => '100',
            'is_base' => true,
        ]])
        ->call('saveEsbMaterial')
        ->assertHasNoErrors();

    $material = $product->esbMaterials()->firstOrFail();
    expect($material->product_name)->toBe('Adonan Croissant Khusus')
        ->and($material->units()->where('is_base', true)->value('is_sales'))->toBeTrue();
});

it('combines prefix name, prefix category, and base name into the final ESB material product name for WIP items', function () {
    Cache::forget('esb_core.access_token');
    Http::fake([
        'https://services.esb.co.id/core/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token-test'],
        ]),
        'https://services.esb.co.id/core/product' => Http::response([
            'status' => 'ok',
            'code' => 'EC03100000',
            'message' => 'OK',
            'result' => ['productID' => 9200, 'isTemp' => false],
            'errors' => null,
        ]),
    ]);

    config()->set('esb.core.base_url', 'https://services.esb.co.id/core');
    config()->set('esb.core.username', 'rnd-test');
    config()->set('esb.core.password', 'secret');

    $prefixCategory = PrefixCategory::create(['name' => 'Whole Cake', 'sort_order' => 1, 'is_active' => true]);

    $project = RndProject::query()->create([
        'name' => 'Prefix Category Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'JYM Product Release',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])->set('esbCategoryOptions', [77 => 'Barang WIP'])
        ->set('prefixCategoryOptions', [$prefixCategory->id => 'Whole Cake'])
        ->set('prefixNameOptions', ['JYM |' => 'Joy-Mart - JYM |'])
        ->set('esbMaterialCategoryId', 77)
        ->set('esbMaterialSubCategoryId', 21)
        ->set('esbMaterialNamePrefix', 'JYM |')
        ->set('esbMaterialPrefixCategoryId', $prefixCategory->id)
        ->set('esbMaterialProductBaseName', 'Chocolate')
        ->set('esbMaterialProductCode', 'BBMK-JYM-01')
        ->set('esbMaterialUnits', [[
            'uom_id' => 5,
            'uom_name' => 'GR',
            'sku' => '',
            'conversion_factor' => '1',
            'base_price' => '100',
            'is_base' => true,
        ]])
        ->call('saveEsbMaterial')
        ->assertHasNoErrors();

    $material = $product->esbMaterials()->firstOrFail();
    expect($material->product_name)->toBe('JYM | Whole Cake Chocolate');
});

it('rehydrates prefix name, prefix category, and base name when editing an existing WIP material draft', function () {
    $prefixCategory = PrefixCategory::create(['name' => 'Whole Cake', 'sort_order' => 1, 'is_active' => true]);

    $project = RndProject::query()->create([
        'name' => 'Edit Prefix Category Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'JYM Edit Product Release',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $material = $product->esbMaterials()->create([
        'category_id' => 77,
        'category_name' => 'Barang WIP',
        'sub_category_id' => 21,
        'sub_category_name' => 'Adonan',
        'uom_id' => 5,
        'uom_name' => 'GR',
        'product_code' => 'BBMK-JYM-01',
        'product_name' => 'JYM | Whole Cake Chocolate',
        'sku' => 'BBMK-JYM-01-GR',
        'conversion_factor' => 1,
        'base_price' => 100,
        'status' => 'draft',
        'created_by' => auth()->id(),
    ]);

    $page = Livewire::test(ViewProjectProductPage::class, [
        'project' => $project->id,
        'product' => $product->id,
    ])->set('esbCategoryOptions', [77 => 'Barang WIP'])
        ->set('esbSubCategoryOptions', [21 => 'Adonan'])
        ->call('openEsbMaterialForm', $material->id);

    expect($page->instance()->esbMaterialNamePrefix)->toBe('JYM |')
        ->and($page->instance()->esbMaterialPrefixCategoryId)->toBe($prefixCategory->id)
        ->and($page->instance()->esbMaterialProductBaseName)->toBe('Chocolate');
});

it('renders the prefix category link in the custom helpdesk sidebar', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $response = $this->get(route('filament.helpdesk.resources.prefix-categories.index'));

    $response->assertOk()
        ->assertSee('Research & Development')
        ->assertSee(route('filament.helpdesk.resources.prefix-categories.index'), false);
});

it('suggests the next ESB product code from the highest code in its category', function () {
    Cache::forget('esb_core.access_token');
    config()->set('esb.core.base_url', 'https://services.esb.co.id/core');
    config()->set('esb.core.username', 'rnd-test');
    config()->set('esb.core.password', 'secret');

    Http::fake([
        'https://services.esb.co.id/core/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token-code-test'],
        ]),
        'https://services.esb.co.id/core/product/list*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 100,
                'count' => 5,
                'data' => [
                    ['productCode' => 'BBMK0098'],
                    ['productCode' => 'BBMK0100'],
                    ['productCode' => 'BBMK0101'],
                    ['productCode' => 'BBMK0102'],
                    ['productCode' => 'BBMK11203'],
                ],
                'prev' => '',
                'next' => '',
            ],
        ]),
    ]);

    expect(app(EsbCoreService::class)->suggestNextProductCode(11))->toBe('BBMK0103');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/list')
        && (int) $request['categoryID'] === 11);
});

it('renders project cards and the individual project workspace', function () {
    $project = RndProject::query()->create([
        'name' => 'Menu Lebaran 2027',
        'description' => 'Pengembangan rangkaian menu Lebaran.',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = RndProjectProduct::query()->create([
        'rnd_project_id' => $project->id,
        'name' => 'Lebaran Cookies',
        'product_code' => 'PRD-LBR',
        'offline_price' => 45000,
        'online_price' => 50000,
        'release_date' => '2026-10-01',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $project->id,
        'esb_bom_id' => 777,
        'bom_code' => 'BOM-LBR',
        'bom_name' => 'Lebaran Cookies',
        'product_name' => 'Cookies',
        'uom_name' => 'PCS',
        'created_by' => auth()->id(),
    ]);
    $product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ListProjects::class)
        ->assertSee('Menu Lebaran 2027')
        ->assertSee('Buka Project');

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSee('Menu Lebaran 2027')
        ->assertSee('Lebaran Cookies')
        ->assertSee('Harga Offline')
        ->assertSee('Buka Product')
        ->assertDontSee('Marketing Materials')
        ->assertDontSee('Bill of Materials');

    Livewire::test(ViewProjectProductPage::class, ['project' => $project->id, 'product' => $product->id])
        ->assertSee('Lebaran Cookies')
        ->assertSee('Marketing Materials')
        ->assertSee('Bill of Material')
        ->assertSee('BOM-LBR')
        ->assertSee('Main Recipe')
        ->assertSee('Add Component')
        ->assertSee('Add Packaging')
        ->assertDontSee('Add Support')
        ->assertSee('Create Main Recipe');
});

it('imports an existing ESB BOM into a project workspace', function () {
    $project = RndProject::query()->create([
        'name' => 'Existing Recipe Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-09-30',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Existing Product Release',
        'offline_price' => 10000,
        'online_price' => 12000,
        'status' => 'draft',
        'created_by' => auth()->id(),
    ]);
    $mainBom = $project->boms()->create([
        'esb_bom_id' => 777,
        'bom_code' => 'BOM-MAIN',
        'bom_name' => 'Main Product Recipe',
        'product_name' => 'Existing Product Release',
        'uom_name' => 'PCS',
        'created_by' => auth()->id(),
    ]);
    $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);

    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/888' => Http::response([
            'status' => 'ok',
            'result' => [
                'bomID' => 888,
                'bomCode' => 'BOM-EXIST',
                'bomName' => 'Existing Assembly',
                'bomTypeName' => 'Assembly',
                'productName' => 'Existing Product',
                'uomName' => 'PCS',
                'flagActive' => 1,
            ],
        ]),
    ]);

    Livewire::test(ViewProjectProductPage::class, ['project' => $project->id, 'product' => $product->id])
        ->set('importUsageType', 'component')
        ->set('importParentBomId', $mainBom->id)
        ->call('attachBom', 888)
        ->assertHasNoErrors()
        ->assertSee('Existing Assembly');

    $this->assertDatabaseHas('rnd_project_boms', [
        'rnd_project_id' => $project->id,
        'esb_bom_id' => 888,
        'bom_code' => 'BOM-EXIST',
    ]);
    $projectBomId = RndProjectBom::query()->where('esb_bom_id', 888)->value('id');
    $this->assertDatabaseHas('rnd_project_product_boms', [
        'rnd_project_product_id' => $product->id,
        'rnd_project_bom_id' => $projectBomId,
        'usage_type' => 'component',
        'parent_rnd_project_bom_id' => $mainBom->id,
    ]);
});

it('exports the complete product BOM as a PIN-protected PDF', function () {
    config()->set([
        'cache.default' => 'array',
        'rnd.bom_pin' => '246810',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/901' => Http::response([
            'status' => 'ok',
            'result' => [
                'bomID' => 901,
                'bomTypeName' => 'Assembly',
                'bomName' => 'Signature Cake Recipe',
                'bomCode' => 'BOM-SIG',
                'productName' => 'Signature Cake',
                'uomName' => 'PCS',
                'notes' => 'Main recipe',
                'bomDetails' => [[
                    'productCode' => 'BBMK001',
                    'productName' => 'Flour',
                    'uomName' => 'GR',
                    'qty' => 250,
                    'yieldPercent' => 0,
                    'printGroup' => '',
                ]],
            ],
        ]),
    ]);

    $project = RndProject::query()->create([
        'name' => 'Signature Product',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Signature Cake',
        'product_code' => 'SIG-CAKE',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $bom = $project->boms()->create([
        'esb_bom_id' => 901,
        'bom_code' => 'BOM-SIG',
        'bom_name' => 'Signature Cake Recipe',
        'created_by' => auth()->id(),
    ]);
    $product->boms()->attach($bom->id, ['usage_type' => 'main']);
    $exportUrl = route('helpdesk.rnd-products.bom-pdf', ['project' => $project->id, 'product' => $product->id, 'scope' => 'kitchen']);

    Livewire::test(ViewProjectProductPage::class, ['project' => $project->id, 'product' => $product->id])
        ->assertSee('Export Kitchen PDF')
        ->call('openExportPdf', 'kitchen')
        ->set('exportPin', '000000')
        ->call('exportBomPdf')
        ->assertHasErrors('exportPin')
        ->set('exportPin', '246810')
        ->call('exportBomPdf')
        ->assertHasNoErrors()
        ->assertRedirect($exportUrl);

    $this->get($exportUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('includes the Bill of Material Store section and product photo when exporting a product with a Menu BOM', function () {
    config()->set([
        'cache.default' => 'array',
        'rnd.bom_pin' => '246810',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();
    Storage::fake('b2');
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/903' => Http::response([
            'status' => 'ok',
            'result' => [
                'bomID' => 903,
                'bomTypeName' => 'Menu',
                'bomName' => 'Signature Cake Menu',
                'bomCode' => 'BOM-SIG-MENU',
                'productName' => 'Signature Cake',
                'uomName' => 'PCS',
                'notes' => 'Menu set',
                'bomDetails' => [[
                    'productCode' => 'SIG-CAKE',
                    'productName' => 'Signature Cake',
                    'uomName' => 'PCS',
                    'qty' => 1,
                    'yieldPercent' => 0,
                    'printGroup' => '',
                ]],
            ],
        ]),
    ]);

    $imagePath = 'rnd/products/photo.jpg';
    Storage::disk('b2')->put($imagePath, UploadedFile::fake()->image('produk.jpg')->get());

    $project = RndProject::query()->create([
        'name' => 'Signature Product Store',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Signature Cake',
        'product_code' => 'SIG-CAKE-STORE',
        'status' => 'development',
        'image_path' => $imagePath,
        'created_by' => auth()->id(),
    ]);
    $bom = $project->boms()->create([
        'esb_bom_id' => 903,
        'bom_code' => 'BOM-SIG-MENU',
        'bom_name' => 'Signature Cake Menu',
        'bom_type_name' => 'Menu',
        'created_by' => auth()->id(),
    ]);
    $product->boms()->attach($bom->id, ['usage_type' => 'menu']);
    SalesRegion::query()->where('is_active', true)->orderBy('sort_order')->take(3)->get()
        ->each(function (SalesRegion $region, int $index) use ($product): void {
            $product->regionalPrices()->create([
                'sales_region_id' => $region->id,
                'offline_price' => 45000 + ($index * 1000),
                'online_price' => 50000 + ($index * 1000),
                'effective_from' => today()->subDay(),
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);
        });
    $exportUrl = route('helpdesk.rnd-products.bom-pdf', ['project' => $project->id, 'product' => $product->id, 'scope' => 'store']);

    Livewire::test(ViewProjectProductPage::class, ['project' => $project->id, 'product' => $product->id])
        ->assertSee('Export Store PDF')
        ->call('openExportPdf', 'store')
        ->set('exportPin', '246810')
        ->call('exportBomPdf')
        ->assertHasNoErrors()
        ->assertRedirect($exportUrl);

    $this->get($exportUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=BOM-STORE-SIG-CAKE-STORE.pdf');
});

it('inlines R2-hosted BOM instruction images as base64 in the exported PDF', function () {
    config()->set([
        'cache.default' => 'array',
        'rnd.bom_pin' => '246810',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();
    Storage::fake('b2');
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/902' => Http::response([
            'status' => 'ok',
            'result' => [
                'bomID' => 902,
                'bomTypeName' => 'Assembly',
                'bomName' => 'Brownie Recipe',
                'bomCode' => 'BOM-BRW',
                'productName' => 'Brownie',
                'uomName' => 'PCS',
                'notes' => 'Main recipe',
                'bomDetails' => [[
                    'productCode' => 'BBMK002',
                    'productName' => 'Cocoa',
                    'uomName' => 'GR',
                    'qty' => 100,
                    'yieldPercent' => 0,
                    'printGroup' => '',
                ]],
            ],
        ]),
    ]);

    $project = RndProject::query()->create([
        'name' => 'Brownie Product',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $product = $project->products()->create([
        'name' => 'Brownie',
        'product_code' => 'BRW',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $bom = $project->boms()->create([
        'esb_bom_id' => 902,
        'bom_code' => 'BOM-BRW',
        'bom_name' => 'Brownie Recipe',
        'created_by' => auth()->id(),
    ]);
    $product->boms()->attach($bom->id, ['usage_type' => 'main']);

    $path = "rnd/bom-instructions/{$project->id}/{$product->id}/902/inline/".Str::uuid().'.jpg';
    Storage::disk('b2')->put($path, UploadedFile::fake()->image('foto.jpg')->get());
    $imageUrl = route('helpdesk.rnd-products.bom-instruction-images.show', ['path' => $path]);
    RndBomInstruction::query()->create([
        'rnd_project_id' => $project->id,
        'rnd_project_product_id' => $product->id,
        'esb_bom_id' => 902,
        'content_html' => '<p>Panggang selama 30 menit.</p><img src="'.$imageUrl.'" alt="Foto proses BOM">',
        'updated_by' => auth()->id(),
    ]);

    $exportUrl = route('helpdesk.rnd-products.bom-pdf', ['project' => $project->id, 'product' => $product->id]);

    Livewire::test(ViewProjectProductPage::class, ['project' => $project->id, 'product' => $product->id])
        ->set('exportPin', '246810')
        ->call('exportBomPdf')
        ->assertHasNoErrors()
        ->assertRedirect($exportUrl);

    $this->get($exportUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
