<?php

use App\Actions\SubmitBulkProductAction;
use App\Filament\Helpdesk\Resources\BulkProductSubmissions\Pages\CreateBulkProductSubmission;
use App\Models\BulkProductSubmission;
use App\Models\User;
use App\Services\EsbCompanyProductService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config([
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.companies.BLSS' => ['username' => 'blss-user', 'password' => 'blss-secret'],
        'esb.core.companies.BLO7' => ['username' => 'blo7-user', 'password' => 'blo7-secret'],
    ]);
});

function bulkProductPayload(): array
{
    return [
        'categoryID' => 10,
        'subCategoryID' => 11,
        'productCode' => 'PRD-001',
        'productName' => 'Produk Baru',
        'requestable' => true,
        'purchasable' => true,
        'saleable' => false,
        'vat' => false,
        'flagLuxuryItem' => 0,
        'receiptTolerance' => 0,
        'productDetails' => [[
            'uomID' => 2,
            'basePrice' => 1000,
            'sku' => 'PRD-001-PCS',
            'qty' => 1,
            'isStock' => true,
            'isPurchase' => true,
            'isTransfer' => true,
            'isSales' => false,
            'isBase' => true,
            'flagActive' => true,
        ]],
    ];
}

it('submits only selected comcodes and keeps independent results', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://core-esb.test/auth/login') {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => $request['username'].'-token']]);
        }

        if ($request->url() === 'https://core-esb.test/product' && $request->hasHeader('Authorization', 'Bearer blss-user-token')) {
            return Http::response(['status' => 'ok', 'result' => ['productID' => 101, 'isTemp' => false]]);
        }

        return Http::response(['status' => 'fail', 'message' => 'Product code already registered'], 422);
    });

    $submission = BulkProductSubmission::factory()->create([
        'target_comcodes' => ['BLSS', 'BLO7'],
        'payload' => bulkProductPayload(),
    ]);

    app(SubmitBulkProductAction::class)->execute($submission);

    expect($submission->refresh()->status)->toBe('partial')
        ->and($submission->items)->toHaveCount(2)
        ->and($submission->items->firstWhere('comcode', 'BLSS')->status)->toBe('succeeded')
        ->and($submission->items->firstWhere('comcode', 'BLSS')->remote_product_id)->toBe(101)
        ->and($submission->items->firstWhere('comcode', 'BLO7')->status)->toBe('failed');

    Http::assertSentCount(4);
    Http::assertNotSent(fn (Request $request): bool => str_contains((string) ($request->header('Authorization')[0] ?? ''), 'BLO6'));
});

it('uses target-specific product and detail ids for update', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'blss-token']]),
        'https://core-esb.test/product/501' => Http::response(['status' => 'ok', 'result' => null]),
    ]);

    $payload = bulkProductPayload();
    $payload['productDetails'][0]['productDetailIDs'] = ['BLSS' => 601, 'BLO7' => 602];
    $submission = BulkProductSubmission::factory()->create([
        'operation' => 'update',
        'target_comcodes' => ['BLSS'],
        'remote_product_ids' => ['BLSS' => 501, 'BLO7' => 502],
        'payload' => $payload,
    ]);

    app(SubmitBulkProductAction::class)->execute($submission);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://core-esb.test/product/501'
        && $request['productDetails'][0]['productDetailID'] === 601
        && ! isset($request['productDetails'][0]['productDetailIDs']));
    expect($submission->refresh()->status)->toBe('succeeded');
});

it('refreshes only the affected company token after a 401', function () {
    Http::fakeSequence('https://core-esb.test/auth/login')
        ->push(['status' => 'ok', 'result' => ['accessToken' => 'expired-token']])
        ->push(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]);
    Http::fakeSequence('https://core-esb.test/product')
        ->push([], 401)
        ->push(['status' => 'ok', 'result' => ['productID' => 77, 'isTemp' => false]]);

    $submission = BulkProductSubmission::factory()->create(['target_comcodes' => ['BLSS'], 'payload' => bulkProductPayload()]);
    app(SubmitBulkProductAction::class)->execute($submission);

    expect($submission->refresh()->status)->toBe('succeeded')
        ->and(Cache::get('esb_core.access_token.BLSS'))->toBe('fresh-token');
});

it('loads and caches category names grouped with their subcategories', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'blss-token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1,
            'limit' => 100,
            'count' => 2,
            'next' => null,
            'data' => [
                ['categoryID' => 10, 'categoryName' => 'Bahan Baku', 'subCategoryID' => 101, 'subCategoryName' => 'Tepung', 'productCode' => 'BBMK0001'],
                ['categoryID' => 10, 'categoryName' => 'Bahan Baku', 'subCategoryID' => 102, 'subCategoryName' => 'Gula', 'productCode' => 'BBMK0009'],
            ],
        ]]),
    ]);

    $service = app(EsbCompanyProductService::class);
    $first = $service->taxonomy('BLSS');
    $second = $service->taxonomy('BLSS');

    expect($first['categories'])->toBe([10 => 'Bahan Baku'])
        ->and($first['subCategoriesByCategory'][10])->toBe([102 => 'Gula', 101 => 'Tepung'])
        ->and($service->suggestNextProductCode('BLSS', 10))->toBe('BBMK0010')
        ->and($second)->toBe($first);
    Http::assertSentCount(2);
});

it('ignores an isolated product code outlier when suggesting a Barang WIP code', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'blss-token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1,
            'limit' => 100,
            'count' => 5,
            'next' => null,
            'data' => [
                ['categoryID' => 20, 'categoryName' => 'Barang WIP', 'productCode' => 'BW1297'],
                ['categoryID' => 20, 'categoryName' => 'Barang WIP', 'productCode' => 'BW1298'],
                ['categoryID' => 20, 'categoryName' => 'Barang WIP', 'productCode' => 'BW1299'],
                ['categoryID' => 20, 'categoryName' => 'Barang WIP', 'productCode' => 'BW1300'],
                ['categoryID' => 20, 'categoryName' => 'Barang WIP', 'productCode' => 'BW11203'],
            ],
        ]]),
    ]);

    expect(app(EsbCompanyProductService::class)->suggestNextProductCode('BLSS', 20))
        ->toBe('BW1301');
});

it('validates one base and stock unit in the Filament form', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'blss-token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 100, 'count' => 1, 'next' => null,
            'data' => [['categoryID' => 10, 'categoryName' => 'Bahan Baku', 'subCategoryID' => 11, 'subCategoryName' => 'Tepung']],
        ]]),
    ]);
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('IT_STAFF');
    $this->actingAs($user);

    Livewire::test(CreateBulkProductSubmission::class)
        ->fillForm([
            'operation' => 'create',
            'target_comcodes' => ['BLSS'],
            'payload' => [...bulkProductPayload(), 'productDetails' => [
                [...bulkProductPayload()['productDetails'][0], 'isBase' => false, 'isStock' => false],
            ]],
        ])
        ->call('create')
        ->assertHasFormErrors();
});

it('resets category selections when the target comcode changes', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 100, 'count' => 1, 'next' => null,
            'data' => [['categoryID' => 10, 'categoryName' => 'Bahan Baku', 'subCategoryID' => 11, 'subCategoryName' => 'Tepung']],
        ]]),
    ]);
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('IT_STAFF');
    $this->actingAs($user);

    Livewire::test(CreateBulkProductSubmission::class)
        ->fillForm(['target_comcodes' => ['BLSS']])
        ->fillForm(['payload.categoryID' => 10, 'payload.subCategoryID' => 11])
        ->fillForm(['target_comcodes' => ['BLO7']])
        ->assertFormSet([
            'payload.categoryID' => null,
            'payload.subCategoryID' => null,
        ]);
});

it('suggests the next product code and builds sku from the selected unit', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 100, 'count' => 2, 'next' => null,
            'data' => [
                ['categoryID' => 10, 'categoryName' => 'Bahan Baku', 'subCategoryID' => 11, 'subCategoryName' => 'Tepung', 'productCode' => 'BBMK0001'],
                ['categoryID' => 10, 'categoryName' => 'Bahan Baku', 'subCategoryID' => 11, 'subCategoryName' => 'Tepung', 'productCode' => 'BBMK0009'],
            ],
        ]]),
    ]);
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('IT_STAFF');
    $this->actingAs($user);

    $component = Livewire::test(CreateBulkProductSubmission::class)
        ->fillForm(['payload.productDetails' => [['uomID' => 5, 'uomName' => 'GR']]])
        ->fillForm(['payload.categoryID' => 10]);

    expect(data_get($component->get('data'), 'payload.productCode'))->toBe('BBMK0010')
        ->and(data_get($component->get('data'), 'payload.productDetails.*.sku'))->toBe(['BBMK0010-GR']);
});

it('grants Bulk Data access to IT staff', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('IT_STAFF');

    expect($user->can('view bulk product submissions'))->toBeTrue()
        ->and($user->can('create bulk product submissions'))->toBeTrue()
        ->and($user->can('edit bulk product submissions'))->toBeTrue();
});

it('renders the Bulk Data list and create pages for IT staff', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'blss-token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 100, 'count' => 1, 'next' => null,
            'data' => [['categoryID' => 10, 'categoryName' => 'Bahan Baku', 'subCategoryID' => 11, 'subCategoryName' => 'Tepung']],
        ]]),
    ]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('IT_STAFF');

    $this->actingAs($user)
        ->get(route('filament.helpdesk.resources.bulk-product-submissions.index'))
        ->assertOk()
        ->assertSee('Bulk Data');

    $this->get(route('filament.helpdesk.resources.bulk-product-submissions.create'))
        ->assertOk()
        ->assertSee('Target Comcode');

    Livewire::test(CreateBulkProductSubmission::class)
        ->assertFormSet(['target_comcodes' => ['BLSS']]);

    $submission = BulkProductSubmission::factory()->create(['created_by' => $user->id, 'payload' => bulkProductPayload()]);
    $submission->items()->create(['comcode' => 'BLO7', 'status' => 'succeeded', 'remote_product_id' => 123]);

    $this->get(route('filament.helpdesk.resources.bulk-product-submissions.index'))
        ->assertOk()
        ->assertSee('BLO7');

    $this->get(route('filament.helpdesk.resources.bulk-product-submissions.view', $submission))
        ->assertOk()
        ->assertSee('BLO7')
        ->assertSee('123');
});
