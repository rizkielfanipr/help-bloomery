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
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
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
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'view material forecast', 'edit material forecast']);
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

it('prevents a project editor without the forecast edit permission from changing the percentage', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo([
        'access backoffice',
        'view rnd projects',
        'edit rnd projects',
        'view material forecast',
    ]);
    $this->actingAs($user);
    $project = RndProject::query()->create([
        'name' => 'Read Only Forecast Project',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'forecast_percentage' => 100,
        'created_by' => $user->id,
    ]);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertDontSee('Terapkan')
        ->set('forecastPercentage', '35')
        ->call('saveForecastPercentage')
        ->assertForbidden();

    expect((float) $project->fresh()->forecast_percentage)->toBe(100.0);
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
    // conflated into "1 recipe run per unit sold" (docs/products/rnd/bom-adjustment/prd.md-style
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

it('counts a shared WIP from every Menu even when another Main Recipe is attached to one Menu', function () {
    $user = User::factory()->create();
    $project = RndProject::query()->create([
        'name' => 'Shared Froyo Forecast',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'created_by' => $user->id,
    ]);
    $region = SalesRegion::query()->create([
        'name' => 'Shared Froyo Region',
        'code' => 'SHARED-FROYO',
        'is_active' => true,
        'sort_order' => 5,
    ]);
    $froyoRecipe = $project->boms()->create([
        'esb_bom_id' => 9500,
        'bom_name' => 'WIP | Froyo Mix',
        'detail_snapshot' => [
            'productCode' => 'BW-FROYO',
            'convertionQty' => 6000,
            'bomDetails' => [[
                'productCode' => 'RAW-MILK',
                'productName' => 'Susu UHT',
                'uomName' => 'ML',
                'qty' => 3491,
            ]],
        ],
        'created_by' => $user->id,
    ]);

    $blackforest = $project->products()->create([
        'name' => 'Blackforest BCB Frojoy',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $blackforest->salesProjections()->create([
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 390,
        'target_revenue' => 1000000,
        'created_by' => $user->id,
    ]);
    $blackforestMenu = $project->boms()->create([
        'esb_bom_id' => 9501,
        'bom_name' => 'Blackforest BCB Frojoy',
        'detail_snapshot' => ['bomDetails' => [[
            'productCode' => 'BW-FROYO',
            'productName' => 'WIP | Froyo Mix',
            'categoryName' => 'Barang WIP',
            'uomName' => 'GR',
            'qty' => 130,
        ]]],
        'created_by' => $user->id,
    ]);
    $blackforest->boms()->attach($froyoRecipe->id, ['usage_type' => 'main']);
    $blackforest->boms()->attach($blackforestMenu->id, ['usage_type' => 'menu']);

    $matcha = $project->products()->create([
        'name' => 'Matcha BCB Frojoy',
        'status' => 'development',
        'created_by' => $user->id,
    ]);
    $matcha->salesProjections()->create([
        'sales_region_id' => $region->id,
        'projection_month' => '2026-11-01',
        'channel' => 'all',
        'target_quantity' => 390,
        'target_revenue' => 1000000,
        'created_by' => $user->id,
    ]);
    $matchaSauceRecipe = $project->boms()->create([
        'esb_bom_id' => 9502,
        'bom_name' => 'Central | Matcha Sauce',
        'detail_snapshot' => [
            'productCode' => 'BW-MATCHA',
            'convertionQty' => 10,
            'bomDetails' => [[
                'productCode' => 'RAW-MATCHA',
                'productName' => 'Matcha Powder',
                'uomName' => 'GR',
                'qty' => 2,
            ]],
        ],
        'created_by' => $user->id,
    ]);
    $matchaMenu = $project->boms()->create([
        'esb_bom_id' => 9503,
        'bom_name' => 'Matcha BCB Frojoy',
        'detail_snapshot' => ['bomDetails' => [
            [
                'productCode' => 'BW-FROYO',
                'productName' => 'WIP | Froyo Mix',
                'categoryName' => 'Barang WIP',
                'uomName' => 'GR',
                'qty' => 130,
            ],
            [
                'productCode' => 'BW-MATCHA',
                'productName' => 'Central | Matcha Sauce',
                'categoryName' => 'Barang WIP',
                'uomName' => 'GR',
                'qty' => 10,
            ],
        ]],
        'created_by' => $user->id,
    ]);
    $matcha->boms()->attach($matchaSauceRecipe->id, ['usage_type' => 'main']);
    $matcha->boms()->attach($matchaMenu->id, ['usage_type' => 'menu']);
    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    $kitchen = app(RndProjectMaterialForecastService::class)->calculate($project, 'kitchen');
    $store = app(RndProjectMaterialForecastService::class)->calculate($project, 'store');

    expect(collect($kitchen['rows'])->firstWhere('code', 'RAW-MILK')['quantity'])->toEqualWithDelta(58997.9, 0.0001)
        ->and(collect($kitchen['rows'])->firstWhere('code', 'RAW-MILK')['product_count'])->toBe(2)
        ->and(collect($kitchen['rows'])->firstWhere('code', 'RAW-MILK')['calculation_notes'])->toHaveCount(2)
        ->and(collect($kitchen['rows'])->firstWhere('code', 'RAW-MATCHA')['quantity'])->toEqualWithDelta(780.0, 0.0001)
        ->and(collect($store['rows'])->firstWhere('code', 'BW-FROYO')['quantity'])->toBe(101400.0)
        ->and(collect($store['rows'])->pluck('code'))->not->toContain('RAW-MILK');
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

/**
 * Project at 50% with Alpha (two regions, two channels), Beta (shares a material with Alpha and has
 * an unresolved WIP), Gamma (BOM but no projection), and Delta (projection but no BOM).
 *
 * @return array{project: RndProject, alpha: int, beta: int, gamma: int, delta: int}
 */
function createMenuScopedForecastProject(User $user, string $name = 'Menu Scope Forecast', int $esbBomIdBase = 9600): array
{
    $core = Mockery::mock(EsbBillOfMaterialService::class);
    $core->shouldReceive('getBillOfMaterials')->andReturn(['data' => []]);
    app()->instance(EsbBillOfMaterialService::class, $core);
    config()->set('cache.default', 'array');

    $project = RndProject::query()->create([
        'name' => $name,
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'forecast_percentage' => 50,
        'created_by' => $user->id,
    ]);
    $regions = collect(['A', 'B'])->map(fn (string $suffix) => SalesRegion::query()->create([
        'name' => "$name Region $suffix",
        'code' => substr(md5($name), 0, 8).$suffix,
        'is_active' => true,
        'sort_order' => 7,
    ]));
    $menus = [];

    foreach (['Alpha', 'Beta', 'Gamma', 'Delta'] as $index => $menuName) {
        $menus[strtolower($menuName)] = $project->products()->create([
            'name' => "$menuName Scope Menu",
            'product_code' => "MENU-$menuName-$esbBomIdBase",
            'status' => 'development',
            'created_by' => $user->id,
        ]);
        DB::table('rnd_project_products')->where('id', $menus[strtolower($menuName)]->id)->update(['updated_at' => now()->subMinutes($index)]);
    }

    foreach ([
        ['alpha', 0, '2026-11-01', 'offline', 10],
        ['alpha', 1, '2026-12-01', 'online', 30],
        ['beta', 0, '2026-11-01', 'all', 20],
        ['delta', 0, '2026-11-01', 'all', 10],
    ] as [$menu, $region, $month, $channel, $quantity]) {
        $menus[$menu]->salesProjections()->create([
            'sales_region_id' => $regions[$region]->id,
            'projection_month' => $month,
            'channel' => $channel,
            'target_quantity' => $quantity,
            'target_revenue' => 1000000,
            'created_by' => $user->id,
        ]);
    }

    $attach = function (string $menu, string $usageType, string $bomName, array $snapshot) use (&$esbBomIdBase, $menus, $project, $user): void {
        $bom = $project->boms()->create([
            'esb_bom_id' => $esbBomIdBase++,
            'bom_name' => $bomName,
            'detail_snapshot' => $snapshot,
            'created_by' => $user->id,
        ]);
        $menus[$menu]->boms()->attach($bom->id, ['usage_type' => $usageType]);
    };

    $attach('alpha', 'main', 'Alpha Mix', [
        'productCode' => 'BW-ALPHA',
        'convertionQty' => 100,
        'bomDetails' => [
            ['productCode' => 'RAW-SHARED', 'productName' => 'Bahan Bersama', 'uomName' => 'GR', 'qty' => 10],
            ['productCode' => 'RAW-ALPHA', 'productName' => 'Bahan Alpha', 'uomName' => 'GR', 'qty' => 5],
        ],
    ]);
    $attach('alpha', 'menu', 'Alpha BOM Menu', ['bomDetails' => [
        ['productCode' => 'BW-ALPHA', 'productName' => 'Alpha Mix', 'uomName' => 'GR', 'qty' => 10],
    ]]);
    $attach('beta', 'main', 'Beta Main', ['bomDetails' => [
        ['productCode' => 'RAW-SHARED', 'productName' => 'Bahan Bersama', 'uomName' => 'GR', 'qty' => 3],
        ['productCode' => 'RAW-BETA', 'productName' => 'Bahan Beta', 'uomName' => 'GR', 'qty' => 1],
        ['productCode' => 'BW-MISSING', 'productName' => 'WIP Hilang', 'categoryName' => 'Barang WIP', 'uomName' => 'GR', 'qty' => 1],
    ]]);
    $attach('beta', 'menu', 'Beta BOM Menu', ['bomDetails' => [
        ['productCode' => 'RAW-CUP', 'productName' => 'Cup', 'uomName' => 'PCS', 'qty' => 1],
    ]]);
    $attach('gamma', 'main', 'Gamma Main', ['bomDetails' => [
        ['productCode' => 'RAW-GAMMA', 'productName' => 'Bahan Gamma', 'uomName' => 'GR', 'qty' => 1],
    ]]);

    $project->load(['products.boms.documentMaterials', 'products.salesProjections', 'boms.documentMaterials']);

    return [
        'project' => $project,
        'alpha' => $menus['alpha']->id,
        'beta' => $menus['beta']->id,
        'gamma' => $menus['gamma']->id,
        'delta' => $menus['delta']->id,
    ];
}

function actingAsForecastUser(array $permissions = ['view material forecast']): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'view bill of materials', ...$permissions]);
    test()->actingAs($user);

    return $user;
}

/** @return array<string, list<array<int, mixed>>> */
function readForecastWorkbook(TestResponse $response): array
{
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[$sheet->getName()] = array_values(collect(iterator_to_array($sheet->getRowIterator()))
            ->map(fn ($row): array => $row->toArray())
            ->all());
    }
    $reader->close();

    return $sheets;
}

/** @param list<array<int, mixed>> $rows */
function forecastSheetRow(array $rows, string $label): ?array
{
    return collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === $label);
}

it('keeps the exact All Menu result for missing, null, and empty menu selections', function () {
    $scope = createMenuScopedForecastProject(User::factory()->create());
    $service = app(RndProjectMaterialForecastService::class);

    $kitchen = $service->calculate($scope['project']);
    $store = $service->calculate($scope['project'], 'store');

    expect($kitchen['projected_units'])->toBe(70.0)
        ->and($kitchen['effective_projected_units'])->toBe(35.0)
        ->and($kitchen['projection_products'])->toBe(3)
        ->and($kitchen['projected_products'])->toBe(2)
        ->and(collect($kitchen['rows'])->map(fn (array $row): array => [$row['code'], $row['quantity'], $row['product_count']])->all())->toBe([
            ['RAW-SHARED', 50.0, 2],
            ['RAW-ALPHA', 10.0, 1],
            ['RAW-BETA', 10.0, 1],
        ])
        ->and($kitchen['warnings'])->toHaveCount(1)
        ->and($kitchen['recipe_notes'])->toHaveCount(1)
        ->and(collect($store['rows'])->map(fn (array $row): array => [$row['code'], $row['quantity'], $row['product_count']])->all())->toBe([
            ['BW-ALPHA', 200.0, 1],
            ['RAW-CUP', 10.0, 1],
        ])
        ->and($service->calculate($scope['project'], 'kitchen', null))->toBe($kitchen)
        ->and($service->calculate($scope['project'], 'kitchen', []))->toBe($kitchen)
        ->and($service->calculate($scope['project'], 'store', []))->toBe($store);
});

it('limits every forecast output to a single selected menu', function () {
    $scope = createMenuScopedForecastProject(User::factory()->create());
    $service = app(RndProjectMaterialForecastService::class);

    $beta = $service->calculate($scope['project'], 'kitchen', [$scope['beta']]);
    $alpha = $service->calculate($scope['project'], 'kitchen', [$scope['alpha']]);

    expect($beta['projected_units'])->toBe(20.0)
        ->and($beta['effective_projected_units'])->toBe(10.0)
        ->and($beta['projection_products'])->toBe(1)
        ->and($beta['projected_products'])->toBe(1)
        ->and(collect($beta['projection_details'])->pluck('name')->all())->toBe(['Beta Scope Menu'])
        ->and(collect($beta['rows'])->map(fn (array $row): array => [$row['code'], $row['quantity'], $row['product_count']])->all())->toBe([
            ['RAW-SHARED', 30.0, 1],
            ['RAW-BETA', 10.0, 1],
        ])
        ->and($beta['warnings'])->toHaveCount(1)
        ->and($beta['warnings'][0])->toContain('BW-MISSING')
        ->and($beta['recipe_notes'])->toBe([])
        ->and($alpha['warnings'])->toBe([])
        ->and($alpha['recipe_notes'])->toHaveCount(1)
        ->and(collect($alpha['rows'])->pluck('code')->all())->not->toContain('RAW-BETA');
});

it('sums every month, region, and channel of the selected menus and applies the global percentage', function () {
    $scope = createMenuScopedForecastProject(User::factory()->create());

    $forecast = app(RndProjectMaterialForecastService::class)
        ->calculate($scope['project'], 'kitchen', [$scope['alpha'], $scope['beta']]);

    expect($forecast['projected_units'])->toBe(60.0)
        ->and($forecast['effective_projected_units'])->toBe(30.0)
        ->and($forecast['forecast_percentage'])->toBe(50.0)
        ->and($forecast['projection_products'])->toBe(2)
        ->and(collect($forecast['projection_details'])->firstWhere('name', 'Alpha Scope Menu')['quantity'])->toBe(40.0)
        ->and(collect($forecast['rows'])->firstWhere('code', 'RAW-SHARED')['quantity'])->toBe(50.0)
        ->and(collect($forecast['rows'])->firstWhere('code', 'RAW-SHARED')['product_count'])->toBe(2);
});

it('never calculates a menu without projection or a menu of another project', function () {
    $user = User::factory()->create();
    $scope = createMenuScopedForecastProject($user);
    $otherScope = createMenuScopedForecastProject($user, 'Other Scope Forecast', 9700);
    $service = app(RndProjectMaterialForecastService::class);

    $noProjection = $service->calculate($scope['project'], 'kitchen', [$scope['gamma']]);
    $foreign = $service->calculate($scope['project'], 'kitchen', [$otherScope['alpha']]);

    expect($noProjection['rows'])->toBe([])
        ->and($noProjection['projection_products'])->toBe(0)
        ->and($foreign['rows'])->toBe([])
        ->and($foreign['projected_units'])->toBe(0.0)
        ->and(collect($service->menuOptions($scope['project']))->where('is_eligible', true)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$scope['alpha'], $scope['beta'], $scope['delta']])->sort()->values()->all());
});

it('adds no query when calculating a menu selection on an eager loaded project', function () {
    $scope = createMenuScopedForecastProject(User::factory()->create());
    $service = app(RndProjectMaterialForecastService::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $service->menuOptions($scope['project']);
    $service->calculate($scope['project']);
    $service->calculate($scope['project'], 'kitchen', [$scope['alpha']]);
    $service->calculate($scope['project'], 'store', [$scope['alpha'], $scope['beta']]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBe([]);
});

it('opens with All Menu and applies a draft selection only after Terapkan', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    $page = Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertSet('forecastMenuIds', [])
        ->assertSee('All Menu')
        ->assertSee('Sales Projection Project')
        ->assertSee('RAW-BETA')
        ->assertDontSee('Terapkan');

    $page->call('openForecastMenuPicker')
        ->assertSet('forecastMenuPickerOpen', true)
        ->assertSee('Belum memiliki Sales Projection')
        ->assertSee('Main Recipe tersedia')
        ->assertSee('Belum ada BOM Menu')
        ->assertSee('MENU-Gamma-9600')
        ->set('forecastMenuDraftIds', [(string) $scope['beta']])
        ->assertSet('forecastMenuIds', [])
        ->assertSee('RAW-ALPHA');

    expect($page->instance()->materialForecast()['projected_units'])->toBe(70.0);

    $page->call('applyForecastMenus')
        ->assertHasNoErrors()
        ->assertSet('forecastMenuPickerOpen', false)
        ->assertSet('forecastMenuIds', [$scope['beta']])
        ->assertSee('1 dari 3 menu dipilih')
        ->assertSee('Sales Projection Menu Terpilih')
        ->assertSee('RAW-BETA')
        ->assertDontSee('RAW-ALPHA');

    expect($page->instance()->materialForecast()['projected_units'])->toBe(20.0);
});

it('discards the draft on cancel and returns to All Menu with one action', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    $page = Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
        ->call('openForecastMenuPicker')
        ->set('forecastMenuDraftIds', [$scope['alpha']])
        ->call('closeForecastMenuPicker')
        ->assertSet('forecastMenuPickerOpen', false)
        ->assertSet('forecastMenuDraftIds', [])
        ->assertSet('forecastMenuIds', []);

    $page->call('openForecastMenuPicker')
        ->set('forecastMenuDraftIds', [$scope['alpha'], $scope['beta']])
        ->call('applyForecastMenus')
        ->assertSee('2 dari 3 menu dipilih')
        ->call('openForecastMenuPicker')
        ->assertSet('forecastMenuDraftIds', [$scope['alpha'], $scope['beta']])
        ->set('forecastMenuDraftIds', [$scope['beta']])
        ->call('closeForecastMenuPicker')
        ->assertSee('2 dari 3 menu dipilih')
        ->call('resetForecastMenus')
        ->assertSet('forecastMenuIds', [])
        ->assertSee('Sales Projection Project');

    expect($page->instance()->materialForecast()['projected_units'])->toBe(70.0);
});

it('keeps the applied menus when switching between Kitchen and Store', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    $page = Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
        ->call('openForecastMenuPicker')
        ->set('forecastMenuDraftIds', [$scope['beta']])
        ->call('applyForecastMenus')
        ->call('setForecastType', 'store')
        ->assertSet('forecastMenuIds', [$scope['beta']])
        ->assertSee('RAW-CUP')
        ->assertDontSee('BW-ALPHA');

    expect($page->instance()->materialForecast()['projection_products'])->toBe(1);

    $page->call('setForecastType', 'kitchen')
        ->assertSet('forecastMenuIds', [$scope['beta']])
        ->assertSee('RAW-BETA')
        ->assertDontSee('RAW-ALPHA');
});

it('restores the applied menus from the URL and points the export at the applied state', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);
    $expectedExportUrl = route('helpdesk.rnd-projects.material-forecast-export', [
        'project' => $scope['project']->id,
        'menu_ids' => [$scope['beta']],
    ]);

    $page = Livewire::withQueryParams(['menu_ids' => [(string) $scope['beta']]])
        ->test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertSee('1 dari 3 menu dipilih')
        ->assertSeeHtml(e($expectedExportUrl));

    expect($page->instance()->materialForecast()['projected_units'])->toBe(20.0);

    $page->call('openForecastMenuPicker')
        ->set('forecastMenuDraftIds', [$scope['alpha']])
        ->assertSeeHtml(e($expectedExportUrl));
});

it('links the export without a menu scope while All Menu is active', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertSeeHtml('href="'.route('helpdesk.rnd-projects.material-forecast-export', ['project' => $scope['project']->id]).'"');
});

it('discards stale, foreign, and malformed URL selections without leaking other projects', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);
    $otherScope = createMenuScopedForecastProject($user, 'Secret Scope Forecast', 9700);

    $partial = Livewire::withQueryParams(['menu_ids' => [$scope['beta'], $scope['gamma'], $otherScope['alpha'], 'abc']])
        ->test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertSee('3 pilihan menu tidak lagi tersedia')
        ->assertSee('Hasil memakai menu terpilih yang masih valid.')
        ->assertSee('1 dari 3 menu dipilih')
        ->assertDontSee('Secret Scope Forecast');

    expect($partial->instance()->forecastMenuFilter()['applied_ids'])->toBe([$scope['beta']])
        ->and($partial->instance()->materialForecast()['projected_units'])->toBe(20.0);

    $allStale = Livewire::withQueryParams(['menu_ids' => [$otherScope['alpha']]])
        ->test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertSee('Hasil kembali memakai All Menu.')
        ->assertSeeHtml('href="'.route('helpdesk.rnd-projects.material-forecast-export', ['project' => $scope['project']->id]).'"');

    expect($allStale->instance()->materialForecast()['projected_units'])->toBe(70.0);

    $wrongType = Livewire::withQueryParams(['menu_ids' => 'abc'])
        ->test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertSet('forecastMenuIds', []);

    expect($wrongType->instance()->materialForecast()['projected_units'])->toBe(70.0);
});

it('marks a selection stale when its menu loses the projection after being applied', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    $page = Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
        ->call('openForecastMenuPicker')
        ->set('forecastMenuDraftIds', [$scope['alpha'], $scope['beta']])
        ->call('applyForecastMenus');

    RndProductSalesProjection::query()->where('rnd_project_product_id', $scope['beta'])->delete();

    $page->call('$refresh')
        ->assertSee('1 pilihan menu tidak lagi tersedia');

    expect($page->instance()->forecastMenuFilter()['applied_ids'])->toBe([$scope['alpha']]);
});

it('rejects draft menus that are not eligible and keeps the picker open', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);
    $otherScope = createMenuScopedForecastProject($user, 'Other Draft Forecast', 9700);

    foreach ([$scope['gamma'], $otherScope['alpha'], 'abc'] as $invalidId) {
        Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
            ->call('openForecastMenuPicker')
            ->set('forecastMenuDraftIds', [$scope['beta'], $invalidId])
            ->call('applyForecastMenus')
            ->assertHasErrors('forecastMenuDraftIds')
            ->assertSet('forecastMenuPickerOpen', true)
            ->assertSet('forecastMenuDraftIds', [$scope['beta']])
            ->assertSet('forecastMenuIds', []);
    }
});

it('treats applying an empty draft as All Menu', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    Livewire::withQueryParams(['menu_ids' => [$scope['beta']]])
        ->test(ViewProject::class, ['record' => $scope['project']->id])
        ->call('openForecastMenuPicker')
        ->set('forecastMenuDraftIds', [])
        ->call('applyForecastMenus')
        ->assertHasNoErrors()
        ->assertSet('forecastMenuIds', [])
        ->assertSee('Sales Projection Project');
});

it('distinguishes the empty states of the forecast table', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    Livewire::withQueryParams(['menu_ids' => [$scope['delta']]])
        ->test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertSee('Menu terpilih belum memiliki Main Recipe yang dapat dihitung.')
        ->assertDontSee('Belum ada forecast.');
});

it('does not change business data when filtering and lets a viewer filter without edit permission', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);
    $snapshot = fn (): array => [
        RndProject::query()->findOrFail($scope['project']->id)->getAttributes(),
        RndProductSalesProjection::query()->orderBy('id')->get()->map->getAttributes()->all(),
        DB::table('rnd_project_products')->orderBy('id')->get()->all(),
        DB::table('rnd_project_boms')->orderBy('id')->get()->all(),
    ];
    $before = $snapshot();

    Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
        ->call('openForecastMenuPicker')
        ->set('forecastMenuDraftIds', [$scope['beta']])
        ->call('applyForecastMenus')
        ->assertHasNoErrors()
        ->call('resetForecastMenus')
        ->assertHasNoErrors();

    expect($snapshot())->toEqual($before);
});

it('forbids every filter action without view material forecast', function (string $method) {
    $user = actingAsForecastUser([]);
    $scope = createMenuScopedForecastProject($user);

    Livewire::test(ViewProject::class, ['record' => $scope['project']->id])
        ->assertDontSee('Menu Projection')
        ->call($method)
        ->assertForbidden();
})->with(['openForecastMenuPicker', 'closeForecastMenuPicker', 'applyForecastMenus', 'resetForecastMenus', 'forecastMenuFilter']);

it('does not add page queries when a menu selection is applied', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);
    $countQueries = function (array $queryParams) use ($scope): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::withQueryParams($queryParams)->test(ViewProject::class, ['record' => $scope['project']->id]);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $countQueries([]);
    $allMenuQueries = $countQueries([]);

    expect($countQueries(['menu_ids' => [$scope['alpha'], $scope['beta']]]))->toBeLessThanOrEqual($allMenuQueries);
});

it('exports All Menu with scope metadata when no menu parameter is present', function (array $query) {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    $response = $this->get(route('helpdesk.rnd-projects.material-forecast-export', ['project' => $scope['project']->id, ...$query]));

    $response->assertOk();
    $sheets = readForecastWorkbook($response);

    expect(forecastSheetRow($sheets['Kitchen'], 'Scope Menu'))->toBe(['Scope Menu', 'All Menu'])
        ->and(forecastSheetRow($sheets['Store'], 'Scope Menu'))->toBe(['Scope Menu', 'All Menu'])
        ->and(forecastSheetRow($sheets['Kitchen'], 'Total Sales Projection')[1])->toEqual(70)
        ->and(forecastSheetRow($sheets['Kitchen'], 'RAW-ALPHA'))->not->toBeNull()
        ->and(forecastSheetRow($sheets['Kitchen'], 'RAW-BETA'))->not->toBeNull();
})->with([
    'no parameter' => [[]],
    'empty parameter' => [['menu_ids' => '']],
]);

it('exports the selected menus on both sheets with the same totals as the page', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);

    $response = $this->get(route('helpdesk.rnd-projects.material-forecast-export', [
        'project' => $scope['project']->id,
        'menu_ids' => [$scope['beta'], $scope['beta']],
    ]));

    $response->assertOk();
    $sheets = readForecastWorkbook($response);
    $service = app(RndProjectMaterialForecastService::class);

    expect(array_keys($sheets))->toBe(['Kitchen', 'Store'])
        ->and(forecastSheetRow($sheets['Kitchen'], 'Scope Menu'))->toBe(['Scope Menu', 'Beta Scope Menu'])
        ->and(forecastSheetRow($sheets['Store'], 'Scope Menu'))->toBe(['Scope Menu', 'Beta Scope Menu'])
        ->and(forecastSheetRow($sheets['Kitchen'], 'Total Sales Projection')[1])->toEqual($service->calculate($scope['project'], 'kitchen', [$scope['beta']])['projected_units'])
        ->and(forecastSheetRow($sheets['Store'], 'Forecast Efektif')[1])->toEqual($service->calculate($scope['project'], 'store', [$scope['beta']])['effective_projected_units'])
        ->and(forecastSheetRow($sheets['Kitchen'], 'RAW-BETA')[3])->toEqual(10)
        ->and(forecastSheetRow($sheets['Kitchen'], 'RAW-SHARED')[3])->toEqual(30)
        ->and(forecastSheetRow($sheets['Kitchen'], 'RAW-ALPHA'))->toBeNull()
        ->and(forecastSheetRow($sheets['Store'], 'RAW-CUP')[3])->toEqual(10)
        ->and(forecastSheetRow($sheets['Store'], 'BW-ALPHA'))->toBeNull()
        ->and(forecastSheetRow($sheets['Kitchen'], 'Alpha Scope Menu'))->toBeNull();

    $multiple = $this->get(route('helpdesk.rnd-projects.material-forecast-export', [
        'project' => $scope['project']->id,
        'menu_ids' => [$scope['alpha'], $scope['beta']],
    ]));
    $multipleSheets = readForecastWorkbook($multiple);

    expect(forecastSheetRow($multipleSheets['Store'], 'Scope Menu'))->toBe(['Scope Menu', 'Alpha Scope Menu, Beta Scope Menu'])
        ->and(forecastSheetRow($multipleSheets['Kitchen'], 'RAW-SHARED')[4])->toEqual(2)
        ->and(forecastSheetRow($multipleSheets['Store'], 'Total Sales Projection')[1])->toEqual(60);
});

it('rejects the whole export for any invalid menu without producing a file or naming a menu', function () {
    config()->set('app.debug', false);
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);
    $otherScope = createMenuScopedForecastProject($user, 'Secret Export Forecast', 9700);
    $exportUrl = fn (mixed $menuIds): string => route('helpdesk.rnd-projects.material-forecast-export', [
        'project' => $scope['project']->id,
        'menu_ids' => $menuIds,
    ]);

    foreach ([
        'foreign' => $exportUrl([$scope['beta'], $otherScope['alpha']]),
        'no projection' => $exportUrl([$scope['gamma']]),
        'missing' => $exportUrl([999999]),
        'not an integer' => $exportUrl(['abc']),
        'not an array' => $exportUrl($scope['beta']),
    ] as $url) {
        $response = $this->get($url);

        $response->assertStatus(422);
        expect($response->baseResponse)->not->toBeInstanceOf(BinaryFileResponse::class)
            ->and($response->getContent())->not->toContain('Secret Export Forecast')
            ->and($response->getContent())->not->toContain('Alpha Scope Menu');
    }
});

it('forbids a scoped export without view material forecast', function () {
    $user = actingAsForecastUser([]);
    $scope = createMenuScopedForecastProject($user);

    $this->get(route('helpdesk.rnd-projects.material-forecast-export', [
        'project' => $scope['project']->id,
        'menu_ids' => [$scope['beta']],
    ]))->assertForbidden();
});

it('neutralizes a spreadsheet formula in the exported menu scope', function () {
    $user = actingAsForecastUser();
    $scope = createMenuScopedForecastProject($user);
    $scope['project']->products()->whereKey($scope['beta'])->update(['name' => '=HYPERLINK("http://example.test")']);

    $response = $this->get(route('helpdesk.rnd-projects.material-forecast-export', [
        'project' => $scope['project']->id,
        'menu_ids' => [$scope['beta']],
    ]));

    $response->assertOk();
    $sheets = readForecastWorkbook($response);

    expect(forecastSheetRow($sheets['Kitchen'], 'Scope Menu')[1])->toBe("'=HYPERLINK(\"http://example.test\")")
        ->and(forecastSheetRow($sheets['Store'], 'Scope Menu')[1])->toBe("'=HYPERLINK(\"http://example.test\")");
});
