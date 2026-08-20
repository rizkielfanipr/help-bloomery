<?php

use App\Filament\Helpdesk\Pages\ProductListPage;
use App\Models\Branch;
use App\Models\Location;
use App\Models\ProductSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function fakeEsbProductPage(array $products, int $page = 1, ?int $count = null, ?string $next = null): void
{
    Http::fake([
        'core-api.esb.co.id/*' => function ($request) use ($products, $page, $count, $next) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $requestedPage = (int) ($query['page'] ?? $page);

            return Http::response([
                'status' => 'ok',
                'result' => [
                    'data' => $products,
                    'page' => $requestedPage,
                    'count' => $count ?? count($products),
                    'limit' => 10,
                    'next' => $next,
                ],
            ]);
        },
    ]);
}

function fakeEsbCoreProductList(array $products, int $page = 1, ?int $count = null, ?string $next = null): void
{
    Http::fake([
        'services.esb.co.id/core/auth/login' => Http::response([
            'status' => 'ok',
            'result' => ['accessToken' => 'fake-token'],
        ]),
        'services.esb.co.id/core/product/list*' => function ($request) use ($products, $page, $count, $next) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $requestedPage = (int) ($query['page'] ?? $page);

            return Http::response([
                'status' => 'ok',
                'result' => [
                    'data' => $products,
                    'page' => $requestedPage,
                    'limit' => 20,
                    'count' => $count ?? count($products),
                    'prev' => null,
                    'next' => $next,
                ],
            ]);
        },
    ]);
}

function esbProductRow(string $code, string $name, int $detailId): array
{
    return [
        'productID' => $detailId,
        'productCode' => $code,
        'productName' => $name,
        'categoryName' => 'Bahan Baku',
        'subCategoryName' => 'Tepung',
        'receiptTolerance' => 0,
        'productDetails' => [
            [
                'productDetailID' => $detailId,
                'unit' => 'KG',
                'conversionFactor' => 1,
                'sku' => $code,
                'basePrice' => 1000,
                'defaultUnit' => ['baseUnit' => 'yes'],
            ],
        ],
    ];
}

function esbCoreProductRow(string $code, string $name): array
{
    return [
        'productCode' => $code,
        'productName' => $name,
        'categoryID' => 1,
        'categoryName' => 'Bahan Baku',
        'subCategoryID' => 1,
        'subCategoryName' => 'Tepung',
    ];
}

beforeEach(function () {
    Storage::fake('b2');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);
});

it('lists products fetched from the esb master product api', function () {
    fakeEsbProductPage([esbProductRow('SKU-001', 'Tepung Terigu', 1)]);

    Livewire::test(ProductListPage::class)
        ->assertSee('Tepung Terigu')
        ->assertSee('SKU-001')
        ->assertSee('Bahan Baku')
        ->assertSee('Tepung')
        ->assertSee('KG');
});

it('searches products through the core product-list endpoint, same as the bom product modal', function () {
    fakeEsbCoreProductList([esbCoreProductRow('SKU-001', 'Tepung Terigu')]);
    fakeEsbProductPage([esbProductRow('SKU-001', 'Tepung Terigu', 1)]);

    Livewire::test(ProductListPage::class)
        ->set('productCodeSearch', 'SKU-001')
        ->set('productSearch', 'Tepung')
        ->assertSee('Tepung Terigu');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'product/list')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['productCode'] ?? null) === 'SKU-001' && ($query['productName'] ?? null) === 'Tepung';
    });
});

it('finds a product outside the current browse page once a search term matches it', function () {
    fakeEsbCoreProductList([esbCoreProductRow('WIP001', 'WIP | Adonan Bolu')]);
    fakeEsbProductPage([esbProductRow('WIP001', 'WIP | Adonan Bolu', 1)]);

    Livewire::test(ProductListPage::class)
        ->set('productPage', 5)
        ->set('productSearch', 'wip')
        ->assertSee('WIP | Adonan Bolu')
        ->assertSet('productPage', 1);
});

it('blocks the product list page for a user without permission', function () {
    fakeEsbProductPage([]);
    $otherUser = User::factory()->create(['is_active' => true]);
    $this->actingAs($otherUser);

    $this->get(route('filament.helpdesk.pages.product-list-page'))
        ->assertForbidden();
});

it('creates a product setting on demand when opening the settings modal', function () {
    fakeEsbProductPage([esbProductRow('SKU-002', 'Gula Pasir', 2)]);

    Livewire::test(ProductListPage::class)
        ->call('openSettingsModal', 'SKU-002', 'Gula Pasir')
        ->assertSet('editingProductCode', 'SKU-002');

    expect(ProductSetting::where('product_code', 'SKU-002')->exists())->toBeTrue();
});

it('saves expiry days, barcode value, and multiple branch locations for a product', function () {
    fakeEsbProductPage([esbProductRow('SKU-003', 'Mentega', 3)]);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $locationA = Location::create(['branch_id' => $branchA->id, 'name' => 'Rak A', 'type' => 'Rak', 'segment' => 'A']);
    $locationB = Location::create(['branch_id' => $branchB->id, 'name' => 'Rak B', 'type' => 'Rak', 'segment' => 'B']);

    Livewire::test(ProductListPage::class)
        ->call('openSettingsModal', 'SKU-003', 'Mentega')
        ->set('settingsData.expiry_days', 90)
        ->set('settingsData.barcode_value', 'CUSTOM-BC')
        ->set('settingsData.location_ids', [$locationA->id, $locationB->id])
        ->call('saveSettings')
        ->assertHasNoErrors();

    $setting = ProductSetting::where('product_code', 'SKU-003')->sole();

    expect($setting->expiry_days)->toBe(90)
        ->and($setting->barcode_value)->toBe('CUSTOM-BC')
        ->and($setting->locations()->pluck('locations.id')->sort()->values()->all())
        ->toBe(collect([$locationA->id, $locationB->id])->sort()->values()->all());
});

it('defaults the effective barcode value to the product code when left blank', function () {
    $setting = ProductSetting::create(['product_code' => 'SKU-004']);

    expect($setting->effectiveBarcodeValue())->toBe('SKU-004');

    $setting->update(['barcode_value' => 'OVERRIDE-CODE']);
    expect($setting->fresh()->effectiveBarcodeValue())->toBe('OVERRIDE-CODE');
});

it('generates and regenerates qr and barcode svgs when relevant fields change', function () {
    $setting = ProductSetting::create(['product_code' => 'SKU-005']);

    expect($setting->qr_svg_path)->not->toBeNull()
        ->and($setting->barcode_svg_path)->not->toBeNull();
    Storage::disk('b2')->assertExists($setting->qr_svg_path);
    Storage::disk('b2')->assertExists($setting->barcode_svg_path);

    $oldBarcodePath = $setting->barcode_svg_path;
    $setting->update(['barcode_value' => 'NEW-VALUE']);

    expect($setting->barcode_svg_path)->toBe($oldBarcodePath);
    Storage::disk('b2')->assertExists($setting->barcode_svg_path);
});

it('downloads a single product label pdf and creates a setting if missing', function () {
    $response = $this->get(route('helpdesk.products.label-pdf', ['code' => 'SKU-006']));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(ProductSetting::where('product_code', 'SKU-006')->exists())->toBeTrue();
});

it('downloads a bulk product labels pdf', function () {
    $response = $this->get(route('helpdesk.products.labels-pdf', ['codes' => ['SKU-007', 'SKU-008']]));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('renders the product list menu link in the custom helpdesk sidebar', function () {
    $response = $this->get(route('filament.helpdesk.pages.product-list-page'));

    $response->assertOk()
        ->assertSee('Product List')
        ->assertSee(route('filament.helpdesk.pages.product-list-page'), false);
});
