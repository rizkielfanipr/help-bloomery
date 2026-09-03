<?php

use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Models\RndProject;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    Cache::forget('esb_core.access_token');
    config()->set('esb.core.base_url', 'https://services.esb.co.id/core');
    config()->set('esb.core.username', 'rnd-test');
    config()->set('esb.core.password', 'secret');

    $project = RndProject::query()->create([
        'name' => 'Inbound ESB Sync Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $this->product = $project->products()->create([
        'name' => 'Inbound Sync Product',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
    $this->material = $this->product->esbMaterials()->create([
        'category_id' => 11,
        'sub_category_id' => 21,
        'uom_id' => 5,
        'uom_name' => 'GR',
        'product_code' => 'BBMK-TAPIOKA',
        'product_name' => 'Tepung Tapioka',
        'sku' => 'BBMK-TAPIOKA-GR',
        'conversion_factor' => 1,
        'base_price' => 100,
        'status' => 'synced',
        'esb_product_id' => 8123,
        'created_by' => auth()->id(),
    ]);
    $this->material->units()->create([
        'uom_id' => 5,
        'uom_name' => 'GR',
        'esb_product_detail_id' => 9123,
        'sku' => 'BBMK-TAPIOKA-GR',
        'conversion_factor' => 1,
        'base_price' => 100,
        'is_base' => true,
        'is_stock' => true,
        'is_purchase' => true,
        'is_transfer' => true,
        'is_sales' => true,
        'flag_active' => true,
    ]);
});

it('pulls a product name and related data changed directly in ESB', function () {
    fakeInboundEsbProducts();

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->product->rnd_project_id,
        'product' => $this->product->id,
    ])->call('refreshEsbMaterial', $this->material->id)->assertHasNoErrors();

    $material = $this->material->fresh();
    expect($material->product_name)->toBe('Tepung Tapioka Premium ESB')
        ->and($material->product_code)->toBe('BBMK-TAPIOKA-NEW')
        ->and($material->category_name)->toBe('Bahan Baku Baru')
        ->and((float) $material->base_price)->toBe(150.0)
        ->and($material->units()->firstOrFail()->esb_product_detail_id)->toBe(9123);
});

it('runs the scheduled inbound synchronization command', function () {
    fakeInboundEsbProducts();

    $this->artisan('rnd:sync-esb-materials')
        ->expectsOutput('Sinkronisasi selesai: 1 berhasil, 0 gagal.')
        ->assertSuccessful();

    expect($this->material->fresh()->product_name)->toBe('Tepung Tapioka Premium ESB');
});

function fakeInboundEsbProducts(): void
{
    Http::fake([
        'https://services.esb.co.id/core/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token-test'],
        ]),
        'https://services.esb.co.id/core/product/list*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 100,
                'count' => 1,
                'data' => [[
                    'productID' => 8123,
                    'productCode' => 'BBMK-TAPIOKA-NEW',
                    'productName' => 'Tepung Tapioka Premium ESB',
                    'categoryID' => 12,
                    'categoryName' => 'Bahan Baku Baru',
                    'subCategoryID' => 22,
                    'subCategoryName' => 'Tepung Baru',
                    'notes' => 'Diubah langsung dari ESB',
                    'productDetails' => [[
                        'productDetailID' => 9123,
                        'uomID' => 5,
                        'unit' => 'GR',
                        'sku' => 'BBMK-TAPIOKA-NEW-GR',
                        'conversionFactor' => 1,
                        'basePrice' => 150,
                        'defaultUnit' => ['baseUnit' => 'yes'],
                        'isSales' => true,
                    ]],
                ]],
                'prev' => null,
                'next' => null,
            ],
        ]),
    ]);
}
