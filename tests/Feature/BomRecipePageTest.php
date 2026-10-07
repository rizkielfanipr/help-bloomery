<?php

use App\Filament\Helpdesk\Pages\CreateBomRecipePage;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Http\Controllers\Helpdesk\RndProductBomPdfController;
use App\Models\RndBomInstruction;
use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\RndProjectProduct;
use App\Models\User;
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

    $this->project = RndProject::query()->create([
        'name' => 'Test R&D Project',
        'description' => 'Project untuk pengujian BOM.',
        'start_date' => '2026-07-01',
        'end_date' => '2026-08-31',
        'created_by' => $admin->id,
    ]);
    $this->product = RndProjectProduct::query()->create([
        'rnd_project_id' => $this->project->id,
        'name' => 'Test Product Release',
        'product_code' => 'PRD-TEST',
        'offline_price' => 25000,
        'online_price' => 28000,
        'status' => 'development',
        'created_by' => $admin->id,
    ]);
});

it('hides Kitchen and Store BOM sections without the BOM view permission', function () {
    $projectViewer = User::factory()->create(['is_active' => true]);
    $projectViewer->givePermissionTo(['access backoffice', 'view rnd projects']);
    $this->actingAs($projectViewer);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])
        ->assertSee('Test Product Release')
        ->assertDontSee('Bill of Material Kitchen')
        ->assertDontSee('Bill of Material Store')
        ->assertDontSee('Harga WA Dari Tanggal')
        ->assertDontSee('Belum ada Main Recipe')
        ->assertDontSee('Belum ada BOM Menu');
});

it('keeps Create BOM actions but hides Add Existing without its permission', function () {
    $bomCreator = User::factory()->create(['is_active' => true]);
    $bomCreator->givePermissionTo([
        'access backoffice',
        'view rnd projects',
        'edit rnd projects',
        'view bill of materials',
        'create bill of materials',
    ]);
    $this->actingAs($bomCreator);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])
        ->assertSee('Create Main Recipe')
        ->assertSee('Create Menu')
        ->assertDontSee('Add Existing Main')
        ->assertDontSee('Add Existing Menu')
        ->call('openBomPicker', 'main')
        ->assertForbidden();
});

it('renders the BOM recipe form without a Blade parse error', function () {
    Livewire::test(CreateBomRecipePage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->assertSee('Buat Bill of Material Baru')
        ->assertSee('Test R&amp;D Project', false)
        ->assertSee('Test Product Release')
        ->assertSee('Pilih produk hasil')
        ->assertDontSeeHtml('wire:model="data.bomName"')
        ->assertDontSeeHtml('wire:model="data.bomCode"')
        ->assertDontSeeHtml('wire:model="data.bomCostTotal"')
        ->assertDontSeeHtml('wire:model="data.notes"')
        ->assertDontSeeHtml('wire:model="data.bomDetails.0.lastHPP"')
        ->assertDontSeeHtml('wire:model="data.bomDetails.0.yieldPercent"')
        ->assertDontSeeHtml('wire:model="data.bomDetails.0.tolerancePercent"')
        ->assertDontSeeHtml('wire:model="data.bomDetails.0.printGroup"');
});

it('keeps the BOM name input visible when creating a Store BOM', function () {
    Livewire::withQueryParams(['usageType' => 'menu'])
        ->test(CreateBomRecipePage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->assertSet('usageType', 'menu')
        ->assertSeeHtml('wire:model="data.bomName"')
        ->assertDontSeeHtml('wire:model="data.bomCode"')
        ->assertDontSeeHtml('wire:model.live="data.accessType"')
        ->assertDontSeeHtml('wire:model="data.selectedUserAccess"');
});

it('stores a newly created ESB BOM inside its project', function () {
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
        'https://core-esb.test/product/bom' => Http::response([
            'status' => 'ok',
            'result' => ['bomID' => 424],
        ]),
    ]);

    $products = [
        101 => [
            'productDetailID' => 101,
            'productName' => 'Croissant',
            'productCode' => 'CRS',
            'unit' => 'PCS',
            'baseUnit' => 'PCS',
            'basePrice' => 0,
            'receiptTolerance' => 0,
        ],
        202 => [
            'productDetailID' => 202,
            'productName' => 'Butter',
            'productCode' => 'BTR',
            'unit' => 'GRAM',
            'baseUnit' => 'GRAM',
            'basePrice' => 100,
            'receiptTolerance' => 0,
        ],
    ];

    Livewire::test(CreateBomRecipePage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->set('selectedProducts', $products)
        ->set('data', [
            'bomName' => 'Croissant Assembly',
            'bomCode' => 'BOM-CRS',
            'productDetailID' => 101,
            'notes' => 'Test BOM',
            'bomCostTotal' => 0,
            'accessType' => 0,
            'selectedUserAccess' => [],
            'bomDetails' => [[
                'ID' => 0,
                'productDetailID' => 202,
                'lastHPP' => 100,
                'qty' => 250,
                'yieldPercent' => 0,
                'printGroup' => '',
                'tolerancePercent' => 0,
            ]],
            'documentMaterials' => [[
                'name' => 'Air',
                'quantity' => 200,
                'unit' => 'ml',
                'notes' => 'Hanya ditampilkan di SOP',
            ]],
        ])
        ->call('create')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://core-esb.test/product/bom'
        && ! array_key_exists('documentMaterials', $request->data())
        && collect($request['bomDetails'])->doesntContain(fn (array $row): bool => ($row['productName'] ?? null) === 'Air'));

    $this->assertDatabaseHas('rnd_project_boms', [
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 424,
        'bom_code' => 'CRS',
        'bom_name' => 'Croissant',
        'product_name' => 'Croissant',
    ]);
    $projectBomId = RndProjectBom::query()->where('esb_bom_id', 424)->value('id');
    $this->assertDatabaseHas('rnd_project_product_boms', [
        'rnd_project_product_id' => $this->product->id,
        'rnd_project_bom_id' => $projectBomId,
        'usage_type' => 'main',
    ]);
    $this->assertDatabaseHas('rnd_bom_document_materials', [
        'rnd_project_bom_id' => $projectBomId,
        'name' => 'Air',
        'quantity' => 200,
        'unit' => 'ml',
    ]);
});

it('creates a BOM Menu with bomTypeID 3 when usageType is menu', function () {
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
        'https://core-esb.test/product/bom' => Http::response([
            'status' => 'ok',
            'result' => ['bomID' => 777],
        ]),
    ]);

    $products = [
        101 => [
            'productDetailID' => 101,
            'productName' => 'Croissant Set',
            'productCode' => 'CRS-SET',
            'unit' => 'PCS',
            'baseUnit' => 'PCS',
            'basePrice' => 0,
            'receiptTolerance' => 0,
        ],
        202 => [
            'productDetailID' => 202,
            'productName' => 'Croissant',
            'productCode' => 'CRS',
            'unit' => 'PCS',
            'baseUnit' => 'PCS',
            'basePrice' => 15000,
            'receiptTolerance' => 0,
        ],
    ];

    // Note: no top-level "Product Hasil" (data.productDetailID) is set — BOM
    // Menu has no result-product concept in ESB's API, unlike Assembly.
    Livewire::test(CreateBomRecipePage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->set('usageType', 'menu')
        ->set('selectedProducts', $products)
        ->set('data', [
            'bomName' => 'Croissant Set Menu',
            'bomCode' => 'BOM-CRS-SET',
            'notes' => 'Test BOM Menu',
            'bomCostTotal' => 0,
            'accessType' => 1,
            'selectedUserAccess' => [99],
            'bomDetails' => [[
                'ID' => 0,
                'productDetailID' => 202,
                'lastHPP' => 15000,
                'qty' => 1,
                'yieldPercent' => 0,
                'printGroup' => '',
                'tolerancePercent' => 0,
            ]],
        ])
        ->call('create')
        ->assertHasNoErrors();

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://core-esb.test/product/bom' || $request->method() !== 'POST') {
            return false;
        }

        // ESB's BOM Menu API doesn't have a tolerancePercent field on bomDetails
        // (unlike Assembly), nor a top-level productDetailID (no result product)
        // — neither must be sent for a Menu BOM.
        return $request['bomTypeID'] === 3
            && $request['accessType'] === 0
            && $request['selectedUserAccess'] === []
            && ! array_key_exists('productDetailID', $request->data())
            && ! array_key_exists('tolerancePercent', $request['bomDetails'][0]);
    });

    $this->assertDatabaseHas('rnd_project_boms', [
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 777,
        'bom_type_name' => 'Menu',
    ]);
    $projectBomId = RndProjectBom::query()->where('esb_bom_id', 777)->value('id');
    $this->assertDatabaseHas('rnd_project_product_boms', [
        'rnd_project_product_id' => $this->product->id,
        'rnd_project_bom_id' => $projectBomId,
        'usage_type' => 'menu',
    ]);
});

it('filters "Add Existing Menu" to only Menu-type BOMs from ESB', function () {
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
        'https://core-esb.test/product/bom*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1, 'limit' => 100, 'count' => 2,
                'data' => [
                    ['bomID' => 601, 'bomCode' => 'BOM-ASM', 'bomName' => 'Assembly Recipe', 'bomTypeName' => 'Assembly', 'productName' => 'Croissant', 'uomName' => 'PCS'],
                    ['bomID' => 602, 'bomCode' => 'BOM-MENU', 'bomName' => 'Croissant Set Menu', 'bomTypeName' => 'Menu', 'productName' => 'Croissant Set', 'uomName' => 'PCS'],
                ],
                'prev' => '', 'next' => '',
            ],
        ]),
    ]);

    $page = Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->call('openBomPicker', 'menu')
        ->call('loadImportBoms');

    $rows = collect($page->instance()->importRows());

    expect($rows->pluck('bomCode')->all())->toBe(['BOM-MENU'])
        ->and($rows->pluck('bomTypeName')->all())->toBe(['Menu']);
});

it('filters "Add Main Recipe" to only Assembly-type BOMs from ESB', function () {
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
        'https://core-esb.test/product/bom*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1, 'limit' => 100, 'count' => 2,
                'data' => [
                    ['bomID' => 601, 'bomCode' => 'BOM-ASM', 'bomName' => 'Assembly Recipe', 'bomTypeName' => 'Assembly', 'productName' => 'Croissant', 'uomName' => 'PCS'],
                    ['bomID' => 602, 'bomCode' => 'BOM-MENU', 'bomName' => 'Croissant Set Menu', 'bomTypeName' => 'Menu', 'productName' => 'Croissant Set', 'uomName' => 'PCS'],
                ],
                'prev' => '', 'next' => '',
            ],
        ]),
    ]);

    $page = Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->call('openBomPicker', 'main')
        ->call('loadImportBoms');

    $rows = collect($page->instance()->importRows());

    expect($rows->pluck('bomCode')->all())->toBe(['BOM-ASM'])
        ->and($rows->pluck('bomTypeName')->all())->toBe(['Assembly']);
});

it('keeps a WIP recipe owned by another project visible in the Add BOM picker, but hides a regular one', function () {
    $otherProject = RndProject::query()->create([
        'name' => 'Other Project', 'description' => 'x', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01',
        'created_by' => auth()->id(),
    ]);
    RndProjectBom::query()->create([
        'rnd_project_id' => $otherProject->id, 'esb_bom_id' => 601, 'bom_code' => 'BW1369',
        'bom_name' => 'WIP | Froyo Mix', 'sync_status' => 'synced', 'created_by' => auth()->id(),
    ]);
    RndProjectBom::query()->create([
        'rnd_project_id' => $otherProject->id, 'esb_bom_id' => 602, 'bom_code' => 'BOM-ASM2',
        'bom_name' => 'Other Project Assembly', 'sync_status' => 'synced', 'created_by' => auth()->id(),
    ]);

    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'access-token']]),
        'https://core-esb.test/product/bom*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1, 'limit' => 100, 'count' => 2,
                'data' => [
                    ['bomID' => 601, 'bomCode' => 'BW1369', 'bomName' => 'WIP | Froyo Mix', 'bomTypeName' => 'Assembly', 'productName' => 'Froyo Mix', 'uomName' => 'GR'],
                    ['bomID' => 602, 'bomCode' => 'BOM-ASM2', 'bomName' => 'Other Project Assembly', 'bomTypeName' => 'Assembly', 'productName' => 'Something', 'uomName' => 'PCS'],
                ],
                'prev' => '', 'next' => '',
            ],
        ]),
    ]);

    $page = Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->call('openBomPicker', 'main')
        ->call('loadImportBoms');

    $rows = collect($page->instance()->importRows());

    expect($rows->pluck('bomCode')->all())->toBe(['BW1369']);
});

it('lets attachBom reuse a WIP recipe already owned by another project without warning', function () {
    $otherProject = RndProject::query()->create([
        'name' => 'Other Project', 'description' => 'x', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01',
        'created_by' => auth()->id(),
    ]);
    $sharedWip = RndProjectBom::query()->create([
        'rnd_project_id' => $otherProject->id, 'esb_bom_id' => 601, 'bom_code' => 'BW1369',
        'bom_name' => 'WIP | Froyo Mix', 'sync_status' => 'synced', 'created_by' => auth()->id(),
    ]);

    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'access-token']]),
        'https://core-esb.test/product/bom/601' => Http::response(['status' => 'ok', 'result' => [
            'bomID' => 601, 'bomCode' => 'BW1369', 'bomName' => 'WIP | Froyo Mix', 'bomTypeName' => 'Assembly',
            'productName' => 'Froyo Mix', 'uomName' => 'GR', 'flagActive' => 1, 'bomDetails' => [],
        ]]),
    ]);

    Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->set('importUsageType', 'main')
        ->call('attachBom', 601)
        ->assertNotNotified('BOM dimiliki project lain');

    expect($sharedWip->fresh()->rnd_project_id)->toBe($otherProject->id)
        ->and($this->product->fresh()->boms->pluck('esb_bom_id')->all())->toContain(601);
});

it('still blocks attaching a regular (non-WIP) BOM already owned by another project', function () {
    $otherProject = RndProject::query()->create([
        'name' => 'Other Project', 'description' => 'x', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01',
        'created_by' => auth()->id(),
    ]);
    RndProjectBom::query()->create([
        'rnd_project_id' => $otherProject->id, 'esb_bom_id' => 602, 'bom_code' => 'BOM-ASM2',
        'bom_name' => 'Other Project Assembly', 'sync_status' => 'synced', 'created_by' => auth()->id(),
    ]);

    Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->set('importUsageType', 'main')
        ->call('attachBom', 602)
        ->assertNotified('BOM dimiliki project lain');

    expect($this->product->fresh()->boms->pluck('esb_bom_id')->all())->not->toContain(602);
});

it('shows Bill of Material Kitchen and Store sections with a Menu BOM\'s components', function () {
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
        'https://core-esb.test/product/bom/555' => Http::response([
            'status' => 'ok',
            'result' => [
                'bomID' => 555,
                'bomTypeID' => 3,
                'bomTypeName' => 'Menu',
                'bomName' => 'Croissant Set Menu',
                'bomCode' => 'BOM-CRS-SET',
                'bomCostTotal' => 0,
                'notes' => '',
                'accessType' => 0,
                'bomDetails' => [[
                    'ID' => 1,
                    'productDetailID' => 202,
                    'productName' => 'Croissant Panggang',
                    'productCode' => 'CRS',
                    'uomName' => 'PCS',
                    'qty' => 1,
                    'lastHPP' => 15000,
                    'yieldPercent' => 0,
                    'tolerancePercent' => 0,
                    'printGroup' => '',
                ]],
            ],
        ]),
    ]);

    $menuBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 555,
        'bom_code' => 'BOM-CRS-SET',
        'bom_name' => 'Croissant Set Menu',
        'bom_type_name' => 'Menu',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($menuBom->id, ['usage_type' => 'menu']);

    Livewire::test(ViewProjectProductPage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->call('loadAllBomComponents')
        ->assertSee('Bill of Material Kitchen')
        ->assertSee('Bill of Material Store')
        ->assertSee('Croissant Set Menu')
        ->assertSee('Croissant Panggang')
        ->assertDontSee('Belum Ditentukan')
        // BOM Menu has no tolerancePercent field in ESB's API, unlike Assembly,
        // and has no top-level "Product Hasil" (result product) concept either.
        ->assertDontSee('Tolerance %')
        ->assertDontSee('Product Hasil');
});

it('loads and updates BOM components inline from the product release page', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    $verifiedAfterDetail = array_merge(bomDetail(), [
        'productDetailID' => 101,
        'productCode' => 'ATL',
        'productName' => 'Adonan Bitterballen',
        'uomName' => 'Resep',
        'editedDate' => '2026-07-30T11:00:00+07:00',
        'bomDetails' => [
            ['ID' => 7, 'productID' => 2, 'productDetailID' => 200, 'productName' => 'Butter', 'productCode' => 'BTR', 'uomName' => 'GRAM', 'qty' => 125, 'lastHpp' => 125, 'yieldPercent' => 2, 'tolerancePercent' => 3, 'printGroup' => ''],
            ['ID' => 8, 'productID' => 0, 'productDetailID' => 201, 'productName' => 'Tepung Premium', 'productCode' => 'BBM-201', 'uomName' => 'GR', 'qty' => 1, 'lastHpp' => 10, 'yieldPercent' => 0, 'tolerancePercent' => 2, 'printGroup' => ''],
        ],
    ]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/42' => Http::sequence()
            ->push(['status' => 'ok', 'result' => bomDetail()])
            ->push(['status' => 'ok', 'result' => bomDetail()])
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => $verifiedAfterDetail]),
    ]);

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'product_name' => 'Croissant',
        'uom_name' => 'PCS',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    $page = Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents')
        ->assertSee('Butter')
        ->assertSee('Product Hasil')
        ->assertSee('Edit BOM')
        ->assertDontSee('View Recipe')
        ->assertDontSee('Waste %')
        ->assertDontSee('Tolerance %')
        ->assertDontSee('Print Group')
        ->assertDontSee('/bom/42/view', false)
        ->assertDontSee('/bom/42/edit', false)
        ->assertDontSee('Muat Komponen')
        ->assertDontSee('Tutup Komponen')
        ->call('editBomComponents', $projectBom->id)
        ->assertSee('Tambah Komponen')
        ->assertSee('Bahan Khusus SOP')
        ->assertSee('Ganti Product Hasil')
        ->assertDontSeeHtml("wire:model=\"bomComponentDrafts.{$projectBom->id}.bomDetails.0.yieldPercent\"")
        ->assertDontSeeHtml("wire:model=\"bomComponentDrafts.{$projectBom->id}.bomDetails.0.tolerancePercent\"")
        ->assertDontSeeHtml("wire:model=\"bomComponentDrafts.{$projectBom->id}.bomDetails.0.printGroup\"")
        ->call('addInlineDocumentMaterial', $projectBom->id)
        ->set("bomComponentDrafts.{$projectBom->id}.documentMaterials.0.name", 'Air')
        ->set("bomComponentDrafts.{$projectBom->id}.documentMaterials.0.quantity", 200)
        ->set("bomComponentDrafts.{$projectBom->id}.documentMaterials.0.unit", 'ml')
        ->set("bomComponentDrafts.{$projectBom->id}.documentMaterials.0.notes", 'Hanya untuk SOP')
        ->set('inlineProductBomId', $projectBom->id)
        ->set('inlineProductTarget', 'result')
        ->set('inlineProductOptions', [
            101 => [
                'productDetailID' => 101,
                'productCode' => 'ATL',
                'productName' => 'Adonan Bitterballen',
                'categoryName' => 'Barang WIP',
                'subCategoryName' => 'Adonan',
                'baseUnit' => 'Resep',
                'unit' => 'Resep',
                'basePrice' => 0,
                'receiptTolerance' => 0,
            ],
        ])
        ->call('selectInlineProduct', 101)
        ->set('inlineProductTarget', 'component')
        ->set('inlineProductOptions', [
            201 => [
                'productDetailID' => 201,
                'productCode' => 'BBM-201',
                'productName' => 'Tepung Premium',
                'categoryName' => 'Bahan Baku Makanan',
                'subCategoryName' => 'Tepung',
                'baseUnit' => 'GR',
                'unit' => 'GR',
                'basePrice' => 10,
                'receiptTolerance' => 2,
            ],
        ])
        ->call('selectInlineProduct', 201)
        ->set("bomComponentDrafts.{$projectBom->id}.bomDetails.0.qty", 125)
        ->set("bomComponentDrafts.{$projectBom->id}.bomDetails.0.tolerancePercent", 3)
        ->call('updateInlineBom', $projectBom->id)
        ->assertHasNoErrors();

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://core-esb.test/product/bom/42'
        && data_get($request->data(), 'productDetailID') === 101
        && data_get($request->data(), 'bomDetails.0.qty') === 125.0
        && data_get($request->data(), 'bomDetails.0.tolerancePercent') === 3.0
        && data_get($request->data(), 'bomDetails.1.productDetailID') === 201
        && data_get($request->data(), 'bomDetails.1.ID') === 0
        && ! array_key_exists('documentMaterials', $request->data()));

    expect((float) data_get($projectBom->fresh()->detail_snapshot, 'bomDetails.0.qty'))->toBe(125.0)
        ->and(data_get($projectBom->fresh()->detail_snapshot, 'bomDetails.1.productCode'))->toBe('BBM-201')
        ->and(data_get($projectBom->fresh()->detail_snapshot, 'bomDetails.1.productName'))->toBe('Tepung Premium')
        ->and(data_get($projectBom->fresh()->detail_snapshot, 'bomDetails.1.uomName'))->toBe('GR')
        ->and(data_get($projectBom->fresh()->detail_snapshot, 'productDetailID'))->toBe(101)
        ->and(data_get($projectBom->fresh()->detail_snapshot, 'productName'))->toBe('Adonan Bitterballen')
        ->and($projectBom->fresh()->sync_status)->toBe('synced');
    $this->assertDatabaseHas('rnd_bom_document_materials', [
        'rnd_project_bom_id' => $projectBom->id,
        'name' => 'Air',
        'quantity' => 200,
        'unit' => 'ml',
    ]);
});

it('preserves ESB Assembly payload fields the user did not edit', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    $detail = bomDetail();
    $detail['accessType'] = 2;
    $detail['selectedUserAccess'] = [10, 20];
    $detail['bomCosts'] = [['costID' => 1, 'amount' => 500]];
    $detail['bomCostTotal'] = 999.5;
    $detail['notes'] = 'Catatan ESB asli';

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/42' => Http::sequence()
            ->push(['status' => 'ok', 'result' => $detail])
            ->push(['status' => 'ok', 'result' => $detail])
            ->push(['status' => 'ok', 'result' => null]),
    ]);

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents')
        ->call('editBomComponents', $projectBom->id)
        ->set("bomComponentDrafts.{$projectBom->id}.bomDetails.0.qty", 175)
        ->call('updateInlineBom', $projectBom->id)
        ->assertHasNoErrors();

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && data_get($request->data(), 'accessType') === 2
        && data_get($request->data(), 'selectedUserAccess') === [10, 20]
        && data_get($request->data(), 'bomCosts.0.amount') === 500
        && (float) data_get($request->data(), 'bomCostTotal') === 999.5
        && data_get($request->data(), 'notes') === 'Catatan ESB asli'
        && (float) data_get($request->data(), 'bomDetails.0.qty') === 175.0);
});

it('blocks updateInlineBom for a user missing edit bill of materials or edit rnd projects', function (array $permissions) {
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    $restrictedUser = User::factory()->create(['is_active' => true]);
    $restrictedUser->givePermissionTo(array_merge(
        ['access backoffice', 'view rnd projects', 'view bill of materials'],
        $permissions,
    ));
    $this->actingAs($restrictedUser);

    Http::fake();

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('updateInlineBom', $projectBom->id)
        ->assertForbidden();

    Http::assertNothingSent();
})->with([
    'missing edit bill of materials' => [['edit rnd projects']],
    'missing edit rnd projects' => [['edit bill of materials']],
]);

it('stops updateInlineBom without sending a PUT when editedDate changed in ESB since load', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    $loadedDetail = bomDetail();
    $changedDetail = bomDetail();
    $changedDetail['editedDate'] = '2026-08-01T09:00:00+07:00';

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/42' => Http::sequence()
            ->push(['status' => 'ok', 'result' => $loadedDetail])
            ->push(['status' => 'ok', 'result' => $changedDetail]),
    ]);

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents')
        ->call('editBomComponents', $projectBom->id)
        ->set("bomComponentDrafts.{$projectBom->id}.bomDetails.0.qty", 150)
        ->call('updateInlineBom', $projectBom->id)
        ->assertHasErrors(["bomComponentDrafts.{$projectBom->id}"]);

    Http::assertNotSent(fn ($request): bool => $request->method() === 'PUT');
    expect($projectBom->fresh()->sync_status)->toBe('synced');
});

it('marks BOM sync status as failed without retrying after an ESB validation error on update', function () {
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
        'https://core-esb.test/product/bom/42' => Http::sequence()
            ->push(['status' => 'ok', 'result' => bomDetail()])
            ->push(['status' => 'ok', 'result' => bomDetail()])
            ->push(['status' => 'fail', 'errors' => [['message' => 'Quantity komponen tidak valid']]], 422),
    ]);

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents')
        ->call('editBomComponents', $projectBom->id)
        ->set("bomComponentDrafts.{$projectBom->id}.bomDetails.0.qty", 150)
        ->call('updateInlineBom', $projectBom->id)
        ->assertNotified('Komponen BOM gagal diperbarui');

    expect(Http::recorded(fn ($request): bool => $request->method() === 'PUT'))->toHaveCount(1);
    expect($projectBom->fresh()->sync_status)->toBe('failed');
});

it('marks BOM sync status as failed without retrying after an ESB connection failure on update', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'access-token']]);
        }
        if ($request->method() === 'PUT') {
            return Http::failedConnection('cURL error 7: Failed to connect to core-esb.test port 443: Connection refused');
        }

        return Http::response(['status' => 'ok', 'result' => bomDetail()]);
    });

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents')
        ->call('editBomComponents', $projectBom->id)
        ->set("bomComponentDrafts.{$projectBom->id}.bomDetails.0.qty", 150)
        ->call('updateInlineBom', $projectBom->id)
        ->assertNotified('Komponen BOM gagal diperbarui');

    expect(Http::recorded(fn ($request): bool => $request->method() === 'PUT'))->toHaveCount(1);
    expect($projectBom->fresh()->sync_status)->toBe('failed');
});

it('marks BOM sync status as failed without retrying after an ESB timeout on update', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'access-token']]);
        }
        if ($request->method() === 'PUT') {
            return Http::failedConnection('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received');
        }

        return Http::response(['status' => 'ok', 'result' => bomDetail()]);
    });

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'sync_status' => 'synced',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('loadAllBomComponents')
        ->call('editBomComponents', $projectBom->id)
        ->set("bomComponentDrafts.{$projectBom->id}.bomDetails.0.qty", 150)
        ->call('updateInlineBom', $projectBom->id)
        ->assertNotified('Komponen BOM gagal diperbarui');

    expect(Http::recorded(fn ($request): bool => $request->method() === 'PUT'))->toHaveCount(1);
    expect($projectBom->fresh()->sync_status)->toBe('failed');
});

it('refreshes the displayed BOM result metadata from ESB', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    $updatedDetail = array_replace(bomDetail(), [
        'productDetailID' => 1367,
        'productName' => 'WIP | Hot Cocoa Mix Updated',
        'productCode' => 'BW1367',
        'uomName' => 'Resep',
    ]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/42' => Http::response([
            'status' => 'ok',
            'result' => $updatedDetail,
        ]),
    ]);

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-OLD',
        'bom_name' => 'Old Recipe',
        'product_name' => 'Old Result Product',
        'uom_name' => 'GR',
        'bom_type_name' => 'Assembly',
        'sync_status' => 'synced',
        'detail_snapshot' => array_replace(bomDetail(), [
            'productName' => 'Old Result Product',
            'uomName' => 'GR',
        ]),
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])
        ->assertSee('Old Result Product')
        ->call('loadBomComponents', $projectBom->id, true)
        ->assertSee('WIP | Hot Cocoa Mix Updated')
        ->assertDontSee('Old Result Product');

    expect($projectBom->fresh())
        ->product_name->toBe('WIP | Hot Cocoa Mix Updated')
        ->uom_name->toBe('Resep')
        ->bom_name->toBe('Croissant Assembly')
        ->bom_code->toBe('BOM-CRS');
});

it('backfills missing component metadata from master product ESB', function () {
    config()->set([
        'esb.master_product.base_url' => 'https://master-esb.test',
        'esb.master_product.token' => 'static-token',
    ]);
    Http::fake([
        'https://master-esb.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 10,
                'count' => 1,
                'data' => [[
                    'productID' => 88,
                    'productCode' => 'BBMK575',
                    'productName' => 'Osmanthus Tea',
                    'categoryName' => 'Bahan Baku Makanan',
                    'productDetails' => [[
                        'productDetailID' => 2443,
                        'unit' => 'GR',
                        'basePrice' => 875,
                    ]],
                ]],
            ],
        ]),
    ]);

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1212,
        'bom_name' => 'Osmanthus Gelee Garnish',
        'detail_snapshot' => [
            'bomID' => 1212,
            'productDetailID' => 999,
            'productName' => 'Osmanthus Gelee Garnish',
            'bomDetails' => [[
                'ID' => 1,
                'productDetailID' => 2443,
                'qty' => 3,
            ]],
        ],
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])
        ->call('loadBomComponents', $projectBom->id)
        ->assertSee('BBMK575')
        ->assertSee('Osmanthus Tea')
        ->assertSee('GR');

    expect(data_get($projectBom->fresh()->detail_snapshot, 'bomDetails.0'))->toMatchArray([
        'productDetailID' => 2443,
        'productCode' => 'BBMK575',
        'productName' => 'Osmanthus Tea',
        'uomName' => 'GR',
    ]);
});

it('automatically displays a matching Barang WIP recipe below its main recipe', function () {
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'integration-user',
        'esb.core.password' => 'integration-password',
    ]);
    Cache::flush();

    $mainDetail = bomDetail();
    $mainDetail['bomDetails'][0] = array_merge($mainDetail['bomDetails'][0], [
        'productID' => 0,
        'productDetailID' => 200,
        'productCode' => 'BW0200',
        'productName' => 'Adonan Bitterballen',
        'categoryName' => '',
        'uomName' => 'Resep',
        'qty' => 2,
    ]);
    $mainDetail['bomDetails'][] = [
        'ID' => 13,
        'productID' => 900,
        'productDetailID' => 901,
        'productCode' => 'PAM001',
        'productName' => 'Box Bitterballen',
        'uomName' => 'PCS',
        'qty' => 1,
        'lastHPP' => 1000,
        'yieldPercent' => 0,
        'tolerancePercent' => 0,
        'printGroup' => '',
    ];
    $wipDetail = array_merge(bomDetail(), [
        'bomID' => 99,
        'bomCode' => 'BOM-ATL',
        'bomName' => 'Resep Adonan Bitterballen',
        'productID' => 999,
        'productDetailID' => 201,
        'productCode' => 'BW0200',
        'productName' => 'Adonan Bitterballen',
        'uomName' => 'Resep',
        'bomDetails' => [[
            'ID' => 12,
            'productDetailID' => 300,
            'productCode' => 'BBM0300',
            'productName' => 'Tepung Premium',
            'uomName' => 'GR',
            'qty' => 300,
            'lastHPP' => 10,
            'yieldPercent' => 0,
            'tolerancePercent' => 0,
            'printGroup' => '',
        ]],
    ]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token'],
        ]),
        'https://core-esb.test/product/bom/42' => Http::response(['status' => 'ok', 'result' => $mainDetail]),
        'https://core-esb.test/product/bom/99' => Http::response(['status' => 'ok', 'result' => $wipDetail]),
        'https://core-esb.test/product/bom*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 100,
                'count' => 1,
                'data' => [[
                    'bomID' => 99,
                    'bomCode' => 'BOM-ATL',
                    'bomName' => 'Resep Adonan Bitterballen',
                    'productName' => 'Adonan Bitterballen',
                    'uomName' => 'Resep',
                ]],
                'prev' => '',
                'next' => '',
            ],
        ]),
    ]);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->set('importUsageType', 'main')
        ->call('attachBom', 42)
        ->assertDispatched('run-rnd-bom-mapping')
        ->call('refreshWipComponentRecipes')
        ->assertSee('AUTO · BARANG WIP')
        ->assertSee('Resep Adonan Bitterballen')
        ->assertSee('Tepung Premium')
        ->assertSee('Dipakai 2 Resep')
        ->assertSee('AUTO · PACKAGING')
        ->assertSee('PAM001')
        ->assertSee('Box Bitterballen');
});

it('stores sanitized BOM instructions and process images on R2', function () {
    Storage::fake('b2');
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1054,
        'bom_code' => 'BOM-1054',
        'bom_name' => 'Crepes Assembly',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->set('bomInstructionInlineUploads.1054', [
        UploadedFile::fake()->image('proses-crepes.jpg', 800, 600),
    ])
        ->call('saveInlineBomInstruction', 1054, '<h2 onclick="alert(1)">Cara Membuat</h2><p>Aduk perlahan.</p><script>alert(1)</script>')
        ->assertHasNoErrors();

    $instruction = RndBomInstruction::query()->firstOrFail();
    expect($instruction->content_html)
        ->toContain('Cara Membuat')
        ->not->toContain('onclick')
        ->not->toContain('<script>');
    expect($instruction->image_paths)->toHaveCount(1);
    Storage::disk('b2')->assertExists($instruction->image_paths[0]);
});

it('stores safe inline rich editor images for BOM instructions', function () {
    $image = 'data:image/png;base64,'.base64_encode('small-image');
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1054,
        'bom_code' => 'BOM-1054',
        'bom_name' => 'Crepes Assembly',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('saveInlineBomInstruction', 1054, '<p>Diamkan 10 menit.</p><img src="'.$image.'" onerror="alert(1)">')
        ->assertHasNoErrors();

    $html = RndBomInstruction::query()->firstOrFail()->content_html;
    expect($html)->toContain('Diamkan 10 menit.')
        ->toContain($image)
        ->not->toContain('onerror');
});

it('uses the native Filament rich editor instead of custom clipboard handlers', function () {
    $view = file_get_contents(resource_path('views/filament/helpdesk/pages/view-project-product.blade.php'));
    $page = file_get_contents(app_path('Filament/Helpdesk/Pages/ViewProjectProductPage.php'));

    expect($view)
        ->not->toContain('quill@')
        ->not->toContain('bomQuillEditor');
    expect($page)
        ->toContain('RichEditor::make')
        ->toContain('->resizableImages()')
        ->toContain("['bulletList', 'orderedList']")
        ->toContain("['link', 'attachFiles']");
});

it('preserves safe resized image dimensions in BOM instructions', function () {
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1054,
        'bom_code' => 'BOM-1054',
        'bom_name' => 'Crepes Assembly',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);
    $image = 'data:image/png;base64,'.base64_encode('small-image');

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call(
        'saveInlineBomInstruction',
        1054,
        '<img src="'.$image.'" width="640" height="360" style="position:fixed" onerror="alert(1)">',
    )->assertHasNoErrors();

    expect(RndBomInstruction::query()->firstOrFail()->content_html)
        ->toContain('width="640"')
        ->toContain('height="360"')
        ->not->toContain('style=')
        ->not->toContain('onerror');
});

it('supports deeply nested TipTap content and keeps the editor modal scrollable', function () {
    $page = file_get_contents(app_path('Filament/Helpdesk/Pages/ViewProjectProductPage.php'));

    expect(config('livewire.payload.max_nesting_depth'))->toBe(30)
        ->and($page)
        ->toContain("'style' => 'min-height: 16rem; max-height: 60vh; overflow-y: auto;'")
        ->toContain('->stickyModalHeader()')
        ->toContain('->stickyModalFooter()');
});

it('saves formatted instruction content through the TipTap modal action', function () {
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1054,
        'bom_code' => 'BOM-1054',
        'bom_name' => 'Crepes Assembly',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->callAction(
        'editBomInstruction',
        ['content' => '<p><strong>Persiapan</strong></p><ol><li>Campurkan bahan</li></ol>'],
        ['bomId' => 1054],
    )->assertHasNoActionErrors();

    expect(RndBomInstruction::query()->firstOrFail()->content_html)
        ->toContain('<strong>Persiapan</strong>')
        ->toContain('<ol>')
        ->toContain('Campurkan bahan');
});

it('embeds TipTap attachment images into the exported PDF content', function () {
    Storage::fake('b2');
    $path = "rnd/bom-instructions/{$this->project->id}/{$this->product->id}/rich-editor/process.jpg";
    Storage::disk('b2')->put($path, 'image-bytes');
    $controller = app(RndProductBomPdfController::class);
    $method = new ReflectionMethod($controller, 'inlineStoredImages');

    $html = $method->invoke($controller, '<p>Proses</p><img data-id="'.$path.'" alt="Foto">');

    expect($html)
        ->toContain('<p>Proses</p>')
        ->toContain('src="data:image/jpeg;base64,'.base64_encode('image-bytes').'"')
        ->not->toContain('data-id=');
});

it('opens a single modal rich editor instead of mounting an editor for every BOM', function () {
    $view = file_get_contents(resource_path('views/filament/helpdesk/pages/view-project-product.blade.php'));
    $partial = file_get_contents(resource_path('views/filament/helpdesk/rnd-projects/partials/inline-bom-instruction.blade.php'));

    expect($view)
        ->not->toContain('new Quill');
    expect($partial)
        ->toContain("mountAction('editBomInstruction'")
        ->toContain('Edit Informasi')
        ->not->toContain('wire:ignore');
});

it('uses unique stable Livewire keys for instruction previews in every BOM hierarchy', function () {
    $view = file_get_contents(resource_path('views/filament/helpdesk/pages/view-project-product.blade.php'));
    $partial = file_get_contents(resource_path('views/filament/helpdesk/rnd-projects/partials/inline-bom-instruction.blade.php'));

    expect($view)
        ->toContain("'instructionInstanceKey' => 'main-'")
        ->toContain("'instructionInstanceKey' => 'auto-'")
        ->toContain("'instructionInstanceKey' => 'child-'")
        ->toContain("'instructionInstanceKey' => 'unassigned-'")
        ->toContain("'instructionInstanceKey' => 'menu-'");
    expect($partial)->toContain('wire:key="bom-instruction-{{ $instructionInstanceKey }}"');
});

it('rejects saving a BOM instruction for a BOM not attached to the product', function () {
    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('saveInlineBomInstruction', 9999, '<p>Tidak sah.</p>')
        ->assertStatus(422);

    expect(RndBomInstruction::query()->count())->toBe(0);
});

it('strips dangerous href schemes and entity-encoded javascript links from BOM instructions', function () {
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1054,
        'bom_code' => 'BOM-1054',
        'bom_name' => 'Crepes Assembly',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call(
        'saveInlineBomInstruction',
        1054,
        '<p>Lihat <a href="jav&#09;ascript:alert(1)">tautan berbahaya</a> dan '
        .'<a href="https://bloomery.org/resep">tautan aman</a>.</p>',
    )->assertHasNoErrors();

    $html = RndBomInstruction::query()->firstOrFail()->content_html;
    expect($html)
        ->not->toContain('javascript:')
        ->toContain('href="https://bloomery.org/resep"')
        ->toContain('tautan berbahaya');
});

it('preserves R2-hosted image references uploaded through the instruction image endpoint', function () {
    Storage::fake('b2');
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1054,
        'bom_code' => 'BOM-1054',
        'bom_name' => 'Crepes Assembly',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    $path = "rnd/bom-instructions/{$this->project->id}/{$this->product->id}/1054/inline/".Str::uuid().'.jpg';
    Storage::disk('b2')->put($path, UploadedFile::fake()->image('foto.jpg')->get());
    $imageUrl = route('helpdesk.rnd-products.bom-instruction-images.show', ['path' => $path]);

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('saveInlineBomInstruction', 1054, '<p>Langkah 1</p><img src="'.$imageUrl.'">')
        ->assertHasNoErrors();

    expect(RndBomInstruction::query()->firstOrFail()->content_html)->toContain($path);
});

it('deletes orphaned R2 images from storage when removed from BOM instruction content', function () {
    Storage::fake('b2');
    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 1054,
        'bom_code' => 'BOM-1054',
        'bom_name' => 'Crepes Assembly',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    $path = "rnd/bom-instructions/{$this->project->id}/{$this->product->id}/1054/inline/".Str::uuid().'.jpg';
    Storage::disk('b2')->put($path, UploadedFile::fake()->image('foto.jpg')->get());
    $imageUrl = route('helpdesk.rnd-products.bom-instruction-images.show', ['path' => $path]);

    $page = Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->project->id,
        'product' => $this->product->id,
    ])->call('saveInlineBomInstruction', 1054, '<p>Langkah 1</p><img src="'.$imageUrl.'">')
        ->assertHasNoErrors();

    Storage::disk('b2')->assertExists($path);

    $page->call('saveInlineBomInstruction', 1054, '<p>Langkah 1 tanpa foto lagi.</p>')
        ->assertHasNoErrors();

    Storage::disk('b2')->assertMissing($path);
});

function bomDetail(): array
{
    return [
        'bomID' => 42,
        'bomTypeID' => 1,
        'bomTypeName' => 'Assembly',
        'bomName' => 'Croissant Assembly',
        'bomCode' => 'BOM-CRS',
        'productDetailID' => 100,
        'productName' => 'Croissant',
        'productCode' => 'CRS',
        'uomName' => 'PCS',
        'bomCostTotal' => 0,
        'notes' => 'Test BOM',
        'accessType' => 0,
        'editedDate' => '2026-07-30T10:00:00+07:00',
        'bomDetails' => [[
            'ID' => 7,
            'productID' => 2,
            'productDetailID' => 200,
            'productName' => 'Butter',
            'productCode' => 'BTR',
            'uomName' => 'GRAM',
            'qty' => 100,
            'lastHpp' => 125,
            'yieldPercent' => 2,
            'tolerancePercent' => 0,
            'printGroup' => '',
        ]],
    ];
}
