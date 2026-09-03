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
        'name' => 'Existing ESB Product Project',
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'created_by' => auth()->id(),
    ]);
    $this->product = $project->products()->create([
        'name' => 'Existing Product Release',
        'status' => 'development',
        'created_by' => auth()->id(),
    ]);
});

it('links an exact existing ESB product instead of creating a duplicate', function () {
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
                    'productName' => 'Tepung Tapioka',
                    'productDetails' => [[
                        'productDetailID' => 9123,
                        'uomID' => 5,
                        'sku' => 'BBMK-TAPIOKA-GR',
                    ]],
                ]],
                'prev' => null,
                'next' => null,
            ],
        ]),
    ]);

    $material = $this->product->esbMaterials()->create([
        'category_id' => 11,
        'sub_category_id' => 21,
        'uom_id' => 5,
        'uom_name' => 'GR',
        'product_code' => 'BBMK-TAPIOKA',
        'product_name' => 'Tepung Tapioka',
        'sku' => 'BBMK-TAPIOKA-GR',
        'conversion_factor' => 1,
        'base_price' => 100,
        'status' => 'draft',
        'created_by' => auth()->id(),
    ]);
    $material->units()->create([
        'uom_id' => 5,
        'uom_name' => 'GR',
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

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->product->rnd_project_id,
        'product' => $this->product->id,
    ])->call('syncEsbMaterial', $material->id)->assertHasNoErrors();

    $material->refresh();
    expect($material->status)->toBe('synced')
        ->and($material->esb_product_id)->toBe(8123)
        ->and($material->units()->firstOrFail()->esb_product_detail_id)->toBe(9123);

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://services.esb.co.id/core/product');
});

it('updates ESB when an already linked material is edited', function () {
    Http::fake([
        'https://services.esb.co.id/core/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'access-token-test'],
        ]),
        'https://services.esb.co.id/core/product/list*' => Http::response([
            'status' => 'ok',
            'result' => ['page' => 1, 'limit' => 100, 'count' => 0, 'data' => [], 'prev' => null, 'next' => null],
        ]),
        'https://services.esb.co.id/core/product/8123' => Http::response([
            'status' => 'ok',
            'result' => ['productID' => 8123],
        ]),
    ]);

    $material = $this->product->esbMaterials()->create([
        'category_id' => 11,
        'category_name' => 'Bahan Baku',
        'sub_category_id' => 21,
        'sub_category_name' => 'Tepung',
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
    $material->units()->create([
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

    Livewire::test(ViewProjectProductPage::class, [
        'project' => $this->product->rnd_project_id,
        'product' => $this->product->id,
    ])->set('esbCategoryOptions', [11 => 'Bahan Baku'])
        ->set('esbSubCategoryOptions', [21 => 'Tepung'])
        ->call('openEsbMaterialForm', $material->id)
        ->set('esbMaterialProductName', 'Tepung Tapioka Premium')
        ->set('esbMaterialNotes', 'Nama diperbarui dari RnD')
        ->call('saveEsbMaterial')
        ->assertHasNoErrors();

    expect($material->fresh()->product_name)->toBe('Tepung Tapioka Premium');
    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://services.esb.co.id/core/product/8123'
        && $request['productName'] === 'Tepung Tapioka Premium'
        && data_get($request->data(), 'productDetails.0.productDetailID') === 9123);
});
