<?php

use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\RndProductSalesProjection;
use App\Models\RndProject;
use App\Models\SalesRegion;
use App\Models\User;
use App\Services\EsbBillOfMaterialService;
use App\Services\EsbService;
use App\Services\RndProjectMaterialForecastService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

it('aggregates projected raw materials and expands attached component BOMs', function () {
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Forecast Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'Forecast Cake',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Jakarta',
        'code' => 'JKT-FC',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    RndProductSalesProjection::query()->create([
        'rnd_project_product_id' => $product->id,
        'sales_region_id' => $region->id,
        'projection_month' => '2026-10-01',
        'channel' => 'all',
        'target_quantity' => 10,
        'target_revenue' => 1000000,
        'target_outlets' => 1,
        'created_by' => $user->id,
    ]);
    $mainBom = $project->boms()->create([
        'esb_bom_id' => 7101,
        'bom_name' => 'Main Cake',
        'detail_snapshot' => [
            'bomDetails' => [
                ['productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'uomName' => 'GR', 'qty' => 100, 'tolerancePercent' => 10],
                ['productCode' => 'WIP-CREAM', 'productName' => 'Cream', 'uomName' => 'RESEP', 'qty' => 2],
            ],
        ],
        'created_by' => $user->id,
    ]);
    $componentBom = $project->boms()->create([
        'esb_bom_id' => 7102,
        'bom_name' => 'Cream Recipe',
        'detail_snapshot' => [
            'productCode' => 'WIP-CREAM',
            'bomDetails' => [
                ['productCode' => 'RAW-SUGAR', 'productName' => 'Gula', 'uomName' => 'GR', 'qty' => 50],
            ],
        ],
        'created_by' => $user->id,
    ]);
    $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);
    $product->boms()->attach($componentBom->id, [
        'usage_type' => 'component',
        'parent_rnd_project_bom_id' => $mainBom->id,
    ]);
    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    $forecast = app(RndProjectMaterialForecastService::class)->calculate($project);

    expect($forecast['projected_units'])->toBe(10.0)
        ->and($forecast['projection_products'])->toBe(1)
        ->and($forecast['projected_products'])->toBe(1)
        ->and(collect($forecast['rows'])->firstWhere('code', 'RAW-FLOUR')['quantity'])->toBe(1100.0)
        ->and(collect($forecast['rows'])->firstWhere('code', 'RAW-SUGAR')['quantity'])->toBe(1000.0);
});

it('calculates Store from menu BOMs on all projected products without mixing Kitchen materials', function () {
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Store Forecast Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Jakarta',
        'code' => 'JKT-STORE',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    foreach ([['Store Cake', 10, 2], ['Store Drink', 5, 3], ['No Menu', 7, null]] as [$name, $target, $menuQuantity]) {
        $product = $project->products()->create([
            'name' => $name,
            'status' => 'development',
            'created_by' => $user->id,
        ]);
        $product->salesProjections()->create([
            'sales_region_id' => $region->id,
            'projection_month' => '2026-10-01',
            'channel' => 'all',
            'target_quantity' => $target,
            'target_revenue' => 1000000,
            'created_by' => $user->id,
        ]);

        if ($menuQuantity === null) {
            continue;
        }

        $menuBom = $project->boms()->create([
            'esb_bom_id' => $name === 'Store Cake' ? 8101 : 8102,
            'bom_name' => $name.' Menu',
            'detail_snapshot' => [
                'bomDetails' => [
                    ['productCode' => 'RAW-MILK', 'productName' => 'Susu', 'uomName' => 'ML', 'qty' => $menuQuantity],
                ],
            ],
            'created_by' => $user->id,
        ]);
        $product->boms()->attach($menuBom->id, ['usage_type' => 'menu']);

        if ($name === 'Store Cake') {
            $mainBom = $project->boms()->create([
                'esb_bom_id' => 8103,
                'bom_name' => 'Kitchen Cake',
                'detail_snapshot' => [
                    'bomDetails' => [
                        ['productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'uomName' => 'GR', 'qty' => 100],
                    ],
                ],
                'created_by' => $user->id,
            ]);
            $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);
        }
    }

    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    $storeForecast = app(RndProjectMaterialForecastService::class)->calculate($project, 'store');
    $kitchenForecast = app(RndProjectMaterialForecastService::class)->calculate($project);

    expect($storeForecast['projected_products'])->toBe(2)
        ->and($storeForecast['projection_products'])->toBe(3)
        ->and($storeForecast['projected_units'])->toBe(22.0)
        ->and($storeForecast['projection_details'])->toMatchArray([
            ['name' => 'Store Cake', 'quantity' => 10.0, 'effective_quantity' => 10.0, 'is_calculated' => true],
            ['name' => 'Store Drink', 'quantity' => 5.0, 'effective_quantity' => 5.0, 'is_calculated' => true],
            ['name' => 'No Menu', 'quantity' => 7.0, 'effective_quantity' => 7.0, 'is_calculated' => false],
        ])
        ->and(collect($storeForecast['rows'])->firstWhere('code', 'RAW-MILK')['quantity'])->toBe(35.0)
        ->and(collect($storeForecast['rows'])->pluck('code'))->not->toContain('RAW-FLOUR')
        ->and($kitchenForecast['projected_products'])->toBe(1)
        ->and($kitchenForecast['projected_units'])->toBe(22.0)
        ->and(collect($kitchenForecast['rows'])->firstWhere('code', 'RAW-FLOUR')['quantity'])->toBe(1000.0);
});

it('stops Store forecast at direct BOM Menu components without expanding WIP recipes', function () {
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Direct Store Forecast',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'forecast_percentage' => 25,
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'Froyo Store Menu',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Store Forecast Region',
        'code' => 'STORE-DIRECT',
        'is_active' => true,
        'sort_order' => 4,
    ]);
    $product->salesProjections()->create([
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 10,
        'target_revenue' => 1000000,
        'created_by' => $user->id,
    ]);
    $menuBom = $project->boms()->create([
        'esb_bom_id' => 9300,
        'bom_name' => 'Froyo BOM Menu',
        'detail_snapshot' => [
            'bomDetails' => [[
                'productCode' => 'BW-FROYO',
                'productName' => 'Froyo Mix',
                'categoryName' => 'Barang WIP',
                'uomName' => 'GR',
                'qty' => 130,
            ]],
        ],
        'created_by' => $user->id,
    ]);
    $wipBom = $project->boms()->create([
        'esb_bom_id' => 9301,
        'bom_name' => 'Froyo Mix Recipe',
        'detail_snapshot' => [
            'productCode' => 'BW-FROYO',
            'bomDetails' => [[
                'productCode' => 'RAW-MILK',
                'productName' => 'Susu',
                'categoryName' => 'Bahan Baku',
                'uomName' => 'ML',
                'qty' => 3000,
            ]],
        ],
        'created_by' => $user->id,
    ]);
    $product->boms()->attach($menuBom->id, ['usage_type' => 'menu']);
    $product->boms()->attach($wipBom->id, [
        'usage_type' => 'component',
        'parent_rnd_project_bom_id' => $menuBom->id,
    ]);
    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    $forecast = app(RndProjectMaterialForecastService::class)->calculate($project, 'store');

    expect(collect($forecast['rows'])->firstWhere('code', 'BW-FROYO')['quantity'])
        ->toBe(325.0)
        ->and($forecast['forecast_percentage'])->toBe(25.0)
        ->and($forecast['projected_units'])->toBe(10.0)
        ->and($forecast['effective_projected_units'])->toBe(2.5)
        ->and($forecast['projection_details'][0]['effective_quantity'])->toBe(2.5)
        ->and(collect($forecast['rows'])->pluck('code'))->not->toContain('RAW-MILK')
        ->and($forecast['warnings'])->toBe([]);
});

it('allows an R&D editor to save the project forecast percentage', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'edit rnd projects']);
    $this->actingAs($user);
    $project = RndProject::query()->create([
        'name' => 'Adjustable Forecast Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->set('forecastPercentage', '65.5')
        ->call('saveForecastPercentage')
        ->assertHasNoErrors();

    expect((float) $project->fresh()->forecast_percentage)->toBe(65.5);
});

it('exports Kitchen and Store forecasts into separate Excel sheets', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'view bill of materials', 'view material forecast']);
    $this->actingAs($user);
    $project = RndProject::query()->create([
        'name' => 'Forecast Export Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'forecast_percentage' => 50,
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'Export Menu',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Export Region',
        'code' => 'EXPORT-FC',
        'is_active' => true,
        'sort_order' => 5,
    ]);
    $product->salesProjections()->create([
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 10,
        'target_revenue' => 1000000,
        'created_by' => $user->id,
    ]);
    $kitchenBom = $project->boms()->create([
        'esb_bom_id' => 9401,
        'bom_name' => 'Export Main Recipe',
        'detail_snapshot' => ['bomDetails' => [[
            'productCode' => 'RAW-FLOUR', 'productName' => 'Tepung', 'uomName' => 'GR', 'qty' => 100,
        ]]],
        'created_by' => $user->id,
    ]);
    $storeBom = $project->boms()->create([
        'esb_bom_id' => 9402,
        'bom_name' => 'Export BOM Menu',
        'detail_snapshot' => ['bomDetails' => [[
            'productCode' => 'BW-MIX', 'productName' => 'Mix Store', 'categoryName' => 'Barang WIP', 'uomName' => 'GR', 'qty' => 2,
        ]]],
        'created_by' => $user->id,
    ]);
    $product->boms()->attach($kitchenBom->id, ['usage_type' => 'main']);
    $product->boms()->attach($storeBom->id, ['usage_type' => 'menu']);

    $response = $this->get(route('helpdesk.rnd-projects.material-forecast-export', $project));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);

    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
            ->map(fn ($row): array => $row->toArray())
            ->all();
    }
    $reader->close();

    expect(array_keys($sheets))->toBe(['Kitchen', 'Store'])
        ->and(collect($sheets['Kitchen'])->flatten()->all())->toContain('RAW-FLOUR', 500)
        ->and(collect($sheets['Store'])->flatten()->all())->toContain('BW-MIX', 10)
        ->and(collect($sheets['Kitchen'])->flatten()->all())->toContain('Export Menu', 50, 'Forecast Efektif');
});

it('expands ESB auto-mapped WIP and does not count the WIP itself', function () {
    config()->set('cache.default', 'array');
    $core = Mockery::mock(EsbBillOfMaterialService::class);
    $core->shouldReceive('getBillOfMaterials')->twice()->andReturn(
        ['data' => [['bomID' => 7301, 'bomCode' => 'BOM-WIP-7301']]],
        ['data' => []],
    );
    $core->shouldReceive('getBillOfMaterial')->once()->with(7301)->andReturn([
        'bomID' => 7301,
        'bomCode' => 'BOM-WIP-7301',
        'productCode' => 'BW9999',
        'bomDetails' => [
            ['productCode' => 'BBM002', 'productName' => 'Tepung WIP', 'categoryName' => 'Bahan Baku', 'uomName' => 'GR', 'qty' => 3],
        ],
    ]);
    app()->instance(EsbBillOfMaterialService::class, $core);

    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Unresolved WIP Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'WIP Product',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Bandung',
        'code' => 'BDG-FC',
        'is_active' => true,
        'sort_order' => 2,
    ]);
    RndProductSalesProjection::query()->create([
        'rnd_project_product_id' => $product->id,
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 5,
        'target_revenue' => 500000,
        'created_by' => $user->id,
    ]);
    $mainBom = $project->boms()->create([
        'esb_bom_id' => 7201,
        'bom_name' => 'Main WIP Product',
        'detail_snapshot' => [
            'bomDetails' => [
                ['productCode' => 'BW9999', 'productName' => 'Adonan WIP', 'categoryName' => 'Barang WIP', 'uomName' => 'RESEP', 'qty' => 1],
                ['productCode' => 'BBM001', 'productName' => 'Garam', 'categoryName' => 'Bahan Baku', 'uomName' => 'GR', 'qty' => 2],
                ['productCode' => 'BW404', 'productName' => 'Cream Tidak Ditemukan', 'categoryName' => 'Barang WIP', 'uomName' => 'RESEP', 'qty' => 1],
            ],
        ],
        'created_by' => $user->id,
    ]);
    $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);
    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    $forecast = app(RndProjectMaterialForecastService::class)->calculate($project);

    expect(collect($forecast['rows'])->pluck('code')->sort()->values()->all())
        ->toBe(['BBM001', 'BBM002'])
        ->and(collect($forecast['rows'])->firstWhere('code', 'BBM002')['quantity'])->toBe(15.0)
        ->and(collect($forecast['rows'])->pluck('code'))->not->toContain('BW9999')
        ->and($forecast['warnings'])->toContain('WIP Cream Tidak Ditemukan (BW404) pada produk WIP Product · Main WIP Product belum memiliki BOM turunan yang cocok di ESB.');
});

it('calculates WIP ingredients proportionally from the required quantity and recipe output', function () {
    $esbService = Mockery::mock(EsbService::class);
    $esbService->shouldReceive('findActiveProductDetail')
        ->once()
        ->with(9201, 'BW-FROYO', 'Froyo Mix')
        ->andReturn([
            'productDetailID' => 9201,
            'unit' => 'Resep',
            'baseUnit' => 'GR',
            'conversionFactor' => 6000,
        ]);
    app()->instance(EsbService::class, $esbService);

    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Froyo Forecast Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'Froyo Menu',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Semarang',
        'code' => 'SMG-FROYO',
        'is_active' => true,
        'sort_order' => 3,
    ]);
    $product->salesProjections()->create([
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 10,
        'target_revenue' => 1000000,
        'created_by' => $user->id,
    ]);
    $mainBom = $project->boms()->create([
        'esb_bom_id' => 9200,
        'bom_name' => 'Froyo Menu Main Recipe',
        'detail_snapshot' => [
            'bomDetails' => [[
                'productDetailID' => 9202,
                'productCode' => 'BW-FROYO',
                'productName' => 'Froyo Mix',
                'categoryName' => 'Barang WIP',
                'uomName' => 'GR',
                'convertionQty' => 1,
                'qty' => 130,
            ]],
        ],
        'created_by' => $user->id,
    ]);
    $wipBom = $project->boms()->create([
        'esb_bom_id' => 9201,
        'bom_name' => 'Froyo Mix Recipe',
        'detail_snapshot' => [
            'productDetailID' => 9201,
            'productCode' => 'BW-FROYO',
            'productName' => 'Froyo Mix',
            'bomDetails' => [[
                'productCode' => 'RAW-MILK',
                'productName' => 'Susu',
                'categoryName' => 'Bahan Baku',
                'uomName' => 'ML',
                'qty' => 3000,
            ]],
        ],
        'created_by' => $user->id,
    ]);
    $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);
    $product->boms()->attach($wipBom->id, [
        'usage_type' => 'component',
        'parent_rnd_project_bom_id' => $mainBom->id,
    ]);
    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    $forecast = app(RndProjectMaterialForecastService::class)->calculate($project);

    expect(collect($forecast['rows'])->firstWhere('code', 'RAW-MILK')['quantity'])
        ->toBe(650.0)
        ->and($forecast['warnings'])->toBe([]);
});

it('scales a shared-batch Main Recipe proportionally to how much of it the Menu BOM actually consumes per unit', function () {
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Froyo Cup Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'Froyo Cup',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Bandung',
        'code' => 'BDG-FROYO',
        'is_active' => true,
        'sort_order' => 4,
    ]);
    $product->salesProjections()->create([
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 100,
        'target_revenue' => 1000000,
        'created_by' => $user->id,
    ]);

    // Froyo Mix is a shared batch recipe: one recipe run yields 6000 GR, but a single
    // sold unit only consumes 130 GR of it per the Menu BOM below — the two must not be
    // conflated into "1 recipe run per unit sold" (docs/rnd-bom-adjustment-prd.md-style
    // regression: Kitchen forecast must reflect actual batch usage, not sales units 1:1).
    $mainBom = $project->boms()->create([
        'esb_bom_id' => 9300,
        'bom_name' => 'Froyo Mix Recipe',
        'detail_snapshot' => [
            'productCode' => 'BW-FROYO',
            'convertionQty' => 6000,
            'bomDetails' => [
                ['productCode' => 'RAW-MILK', 'productName' => 'Susu', 'uomName' => 'ML', 'qty' => 3000],
                ['productCode' => 'RAW-SUGAR', 'productName' => 'Gula', 'uomName' => 'GR', 'qty' => 600],
            ],
        ],
        'created_by' => $user->id,
    ]);
    $menuBom = $project->boms()->create([
        'esb_bom_id' => 9301,
        'bom_name' => 'Froyo Cup Menu',
        'detail_snapshot' => [
            'bomDetails' => [
                ['productCode' => 'BW-FROYO', 'productName' => 'Froyo Mix', 'uomName' => 'GR', 'qty' => 130],
            ],
        ],
        'created_by' => $user->id,
    ]);
    $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);
    $product->boms()->attach($menuBom->id, ['usage_type' => 'menu']);
    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    $kitchen = app(RndProjectMaterialForecastService::class)->calculate($project, 'kitchen');
    $store = app(RndProjectMaterialForecastService::class)->calculate($project, 'store');

    // 100 units × 130 GR/unit = 13,000 GR needed ÷ 6000 GR/recipe run = 2.1666... runs.
    expect(collect($kitchen['rows'])->firstWhere('code', 'RAW-MILK')['quantity'])->toBe(6500.0)
        ->and(collect($kitchen['rows'])->firstWhere('code', 'RAW-SUGAR')['quantity'])->toBe(1300.0)
        ->and(collect($kitchen['rows'])->pluck('code'))->not->toContain('BW-FROYO');

    // Store is untouched by the Kitchen-side fix: it still reports the Menu's own
    // direct qty × sales target, without expanding into the shared recipe at all.
    expect(collect($store['rows'])->firstWhere('code', 'BW-FROYO')['quantity'])->toBe(13000.0)
        ->and(collect($store['rows'])->pluck('code'))->not->toContain('RAW-MILK');

    // The header-level "recipe_notes" explain the proportional multiplier itself, and each
    // material row's own "calculation_notes" explain how that row's quantity was derived —
    // both filled with this project's real numbers, not a generic formula string.
    expect($kitchen['recipe_notes'])->toHaveCount(1)
        ->and($kitchen['recipe_notes'][0])->toBe(
            'Froyo Cup · Froyo Mix Recipe: 100 unit terjual × 130 GR/unit (dari BOM Menu) = 13.000 GR dibutuhkan ÷ 6.000 GR hasil per resep = 2,1667 kali resep.'
        )
        ->and(collect($kitchen['rows'])->firstWhere('code', 'RAW-MILK')['calculation_notes'])->toBe(
            ['Froyo Cup · Froyo Mix Recipe: 3.000 ML/resep × 2,1667 = 6.500 ML']
        );
});

it('renders the real project calculation and per-row cara hitung in the Material Forecast header', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'view bill of materials', 'view material forecast']);
    $this->actingAs($user);

    $project = RndProject::query()->create([
        'name' => 'Froyo Header Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);
    $product = $project->products()->create([
        'name' => 'Froyo Cup',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Surabaya',
        'code' => 'SBY-FROYO',
        'is_active' => true,
        'sort_order' => 6,
    ]);
    $product->salesProjections()->create([
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 100,
        'target_revenue' => 1000000,
        'created_by' => $user->id,
    ]);
    $mainBom = $project->boms()->create([
        'esb_bom_id' => 9310,
        'bom_name' => 'Froyo Mix Recipe',
        'detail_snapshot' => [
            'productCode' => 'BW-FROYO',
            'convertionQty' => 6000,
            'bomDetails' => [
                ['productCode' => 'RAW-MILK', 'productName' => 'Susu', 'uomName' => 'ML', 'qty' => 3000],
            ],
        ],
        'created_by' => $user->id,
    ]);
    $menuBom = $project->boms()->create([
        'esb_bom_id' => 9311,
        'bom_name' => 'Froyo Cup Menu',
        'detail_snapshot' => [
            'bomDetails' => [
                ['productCode' => 'BW-FROYO', 'productName' => 'Froyo Mix', 'uomName' => 'GR', 'qty' => 130],
            ],
        ],
        'created_by' => $user->id,
    ]);
    $product->boms()->attach($mainBom->id, ['usage_type' => 'main']);
    $product->boms()->attach($menuBom->id, ['usage_type' => 'menu']);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSee('Perhitungan Project Ini')
        ->assertSee('Sales Projection 100,00 unit × Persentase Forecast 100,00% =', false)
        ->assertSee('100,00 unit forecast efektif')
        ->assertSee('Resep WIP Bersama (Proporsional)')
        ->assertSee('Froyo Cup · Froyo Mix Recipe: 100 unit terjual × 130 GR/unit (dari BOM Menu) = 13.000 GR dibutuhkan ÷ 6.000 GR hasil per resep = 2,1667 kali resep.')
        ->assertSee('Froyo Cup · Froyo Mix Recipe: 3.000 ML/resep × 2,1667 = 6.500 ML');
});

it('shows the material forecast on the Project page only for users with view material forecast', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'view bill of materials', 'view material forecast']);
    $this->actingAs($user);
    $project = RndProject::query()->create([
        'name' => 'Visible Forecast Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSee('Critical Control Point (CCP)')
        ->assertSee('Dokumen pendukung titik kendali kritis')
        ->assertSee('Material Forecast')
        ->assertSee('Forecast Kitchen')
        ->assertSee('Forecast Store')
        ->assertSee('Purchasing Preparation')
        ->assertSee('Sales Projection Project')
        ->assertSee('menu dapat dihitung pada Forecast Kitchen karena memiliki Main Recipe')
        ->assertSee('Sales Projection × Qty Main Recipe + Tolerance bahan. WIP dihitung proporsional')
        ->assertSee('Belum ada forecast')
        ->call('setForecastType', 'store')
        ->assertSet('forecastType', 'store')
        ->assertSee('Sales Projection × Qty BOM Menu. Komponen ditampilkan langsung tanpa menguraikan WIP.')
        ->assertSee('Belum ada forecast');
});

it('hides the material forecast section from a BOM viewer without view material forecast', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'view bill of materials']);
    $this->actingAs($user);
    $project = RndProject::query()->create([
        'name' => 'Hidden Forecast Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSee('Critical Control Point (CCP)')
        ->assertDontSee('Material Forecast')
        ->assertDontSee('Purchasing Preparation')
        ->call('setForecastType', 'store')
        ->assertForbidden();
});
