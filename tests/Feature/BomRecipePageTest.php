<?php

use App\Filament\Helpdesk\Pages\CreateBomRecipePage;
use App\Filament\Helpdesk\Pages\EditBomRecipePage;
use App\Filament\Helpdesk\Pages\ViewBomPage;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
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

it('renders the BOM recipe form without a Blade parse error', function () {
    Livewire::test(CreateBomRecipePage::class, ['project' => $this->project->id, 'product' => $this->product->id])
        ->assertSee('Buat Bill of Material Baru')
        ->assertSee('Test R&amp;D Project', false)
        ->assertSee('Test Product Release')
        ->assertSee('Pilih produk hasil');
});

it('renders the BOM view and update workspaces', function () {
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
        'https://core-esb.test/product/bom/42' => Http::response([
            'status' => 'ok',
            'result' => bomDetail(),
        ]),
    ]);

    $projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 42,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'product_name' => 'Croissant',
        'uom_name' => 'PCS',
        'created_by' => auth()->id(),
    ]);
    $this->product->boms()->attach($projectBom->id, ['usage_type' => 'main']);

    $view = Livewire::test(ViewBomPage::class, ['project' => $this->project->id, 'product' => $this->product->id, 'bom' => 42])
        ->assertSee('Resep Dilindungi PIN')
        ->assertDontSee('Croissant Assembly');

    Http::assertNothingSent();

    $view->set('pin', '000000')
        ->call('verifyPin')
        ->assertHasErrors('pin')
        ->assertDontSee('Croissant Assembly');

    Http::assertNothingSent();

    $view->set('pin', '246810')
        ->call('verifyPin')
        ->assertHasNoErrors()
        ->assertSee('Croissant Assembly')
        ->assertSee('Butter');

    Livewire::test(EditBomRecipePage::class, ['project' => $this->project->id, 'product' => $this->product->id, 'bom' => 42])
        ->assertSet('isEditing', true)
        ->assertSet('data.bomName', 'Croissant Assembly')
        ->assertSee('Update Bill of Material');
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
        ])
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rnd_project_boms', [
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 424,
        'bom_code' => 'BOM-CRS',
        'bom_name' => 'Croissant Assembly',
        'product_name' => 'Croissant',
    ]);
    $projectBomId = RndProjectBom::query()->where('esb_bom_id', 424)->value('id');
    $this->assertDatabaseHas('rnd_project_product_boms', [
        'rnd_project_product_id' => $this->product->id,
        'rnd_project_bom_id' => $projectBomId,
        'usage_type' => 'main',
    ]);
});

it('loads and updates BOM components inline from the product release page', function () {
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
        'https://core-esb.test/product/bom/42' => Http::response([
            'status' => 'ok',
            'result' => bomDetail(),
        ]),
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
        ->assertDontSee('Muat Komponen')
        ->assertDontSee('Tutup Komponen')
        ->call('editBomComponents', $projectBom->id)
        ->assertSee('Tambah Komponen')
        ->assertSee('Ganti Product Hasil')
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
        && data_get($request->data(), 'bomDetails.1.ID') === 0);

    expect((float) data_get($projectBom->fresh()->detail_snapshot, 'bomDetails.0.qty'))->toBe(125.0)
        ->and(data_get($projectBom->fresh()->detail_snapshot, 'productDetailID'))->toBe(101)
        ->and(data_get($projectBom->fresh()->detail_snapshot, 'productName'))->toBe('Adonan Bitterballen')
        ->and($projectBom->fresh()->sync_status)->toBe('synced');
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
