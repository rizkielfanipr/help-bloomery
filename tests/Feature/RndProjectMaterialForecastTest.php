<?php

use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\RndProductSalesProjection;
use App\Models\RndProject;
use App\Models\SalesRegion;
use App\Models\User;
use App\Services\EsbBillOfMaterialService;
use App\Services\RndProjectMaterialForecastService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

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
        ->and($storeForecast['projected_units'])->toBe(15.0)
        ->and(collect($storeForecast['rows'])->firstWhere('code', 'RAW-MILK')['quantity'])->toBe(35.0)
        ->and(collect($storeForecast['rows'])->pluck('code'))->not->toContain('RAW-FLOUR')
        ->and($kitchenForecast['projected_products'])->toBe(1)
        ->and(collect($kitchenForecast['rows'])->firstWhere('code', 'RAW-FLOUR')['quantity'])->toBe(1000.0);
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

it('shows the material forecast on the Project page for BOM viewers', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(['access backoffice', 'view rnd projects', 'view bill of materials']);
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
        ->assertSee('Produk Terhitung')
        ->assertSee('Total Proyeksi Penjualan')
        ->assertSee('Sales Projection × Qty Main Recipe + Tolerance bahan')
        ->assertSee('Belum ada forecast')
        ->call('setForecastType', 'store')
        ->assertSet('forecastType', 'store')
        ->assertSee('Sales Projection × Qty BOM Menu')
        ->assertSee('Belum ada forecast');
});
