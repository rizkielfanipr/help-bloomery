<?php

use App\Filament\Casual\Pages\ItemJournalPage;
use App\Models\QualityControlItemJournal;
use App\Models\User;
use App\Services\EsbItemJournalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    Cache::flush();
    Storage::fake('b2');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.username', 'core-user');
    config()->set('esb.core.password', 'core-secret');
    config()->set('esb.core.companies.BLSS', ['username' => 'qc-user', 'password' => 'secret']);
    config()->set('esb.master_product.base_url', 'https://master-product.test');
    config()->set('esb.master_product.token', 'master-token');
    $this->user = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->user->assignRole('QUALITY_CONTROL');
    $this->actingAs($this->user);
});

it('selects products from a paginated ESB picker', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'company-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => []]),
        'https://esb.test/core/purpose*' => Http::response(['status' => 'ok', 'result' => ['data' => [], 'next' => '']]),
        'https://esb.test/core/product/list*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 20,
                'count' => 11,
                'next' => 'page=2',
                'prev' => '',
                'data' => [['productCode' => 'BB001', 'productName' => 'Whipping Cream']],
            ],
        ]),
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 10,
                'count' => 11,
                'next' => 'page=2',
                'data' => [[
                    'productCode' => 'BB001',
                    'productName' => 'Whipping Cream',
                    'categoryName' => 'Bahan Baku',
                    'subCategoryName' => 'Dairy',
                    'productDetails' => [[
                        'productDetailID' => 2112,
                        'uomName' => 'GR',
                        'conversionFactor' => 1,
                    ]],
                ]],
            ],
        ]),
    ]);

    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('companyCode', 'BLSS')
        ->call('openProductPicker', 0)
        ->assertSet('productPickerOpen', true)
        ->call('loadProducts')
        ->assertSet('productTotal', 11)
        ->assertSet('productHasNext', true)
        ->assertSee('Whipping Cream')
        ->call('selectProduct', 2112)
        ->assertSet('productPickerOpen', false)
        ->assertSet('items.0.productDetailID', 2112)
        ->assertSet('items.0.productName', 'Whipping Cream');
});

it('finds products by name through the fuzzy ESB product list', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'core-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => []]),
        'https://esb.test/core/purpose*' => Http::response(['status' => 'ok', 'result' => ['data' => [], 'next' => '']]),
        'https://esb.test/core/product/list*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 20,
                'count' => 1,
                'next' => '',
                'prev' => '',
                'data' => [['productCode' => 'BB001', 'productName' => 'Whipping Cream']],
            ],
        ]),
        'https://master-product.test/corev1/master/product*' => Http::response([
            'status' => 'ok',
            'result' => [
                'page' => 1,
                'limit' => 10,
                'count' => 1,
                'next' => '',
                'data' => [[
                    'productCode' => 'BB001',
                    'productName' => 'Whipping Cream',
                    'productDetails' => [['productDetailID' => 2112, 'uomName' => 'GR']],
                ]],
            ],
        ]),
    ]);

    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('companyCode', 'BLSS')
        ->call('openProductPicker', 0)
        ->set('productSearch', 'whipping')
        ->assertSet('productTotal', 1)
        ->assertSee('Whipping Cream');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/product/list')
        && $request['productName'] === 'whipping');
});

it('creates a non-template item journal from the quality control app', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [['branchID' => 373, 'branchCode' => 'BLA', 'branchName' => 'Bloomery Test']]]),
        'https://esb.test/core/location*' => Http::response(['status' => 'ok', 'result' => [['locationID' => 964, 'locationName' => 'Kitchen']]]),
        'https://esb.test/core/purpose*' => Http::response(['status' => 'ok', 'result' => ['data' => [['purposeID' => 10, 'purposeName' => 'Sampling QC', 'purposeAccount' => 'QC Expense', 'purposeAppliedTo' => ['ITEM JOURNAL'], 'flagActive' => true]], 'next' => '']]),
        'https://esb.test/core/inventory/item-journal' => Http::response(['status' => 'ok', 'result' => ['itemJournalNum' => 'IU202609160001']]),
    ]);

    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->assertSee('Informasi Pengisian')
        ->assertSee('Tambah Foto / PDF')
        ->assertSee('Kirim Item Journal ke ESB')
        ->set('companyCode', 'BLSS')
        ->set('branchId', '373')
        ->set('productOptions', [['productDetailID' => 2112, 'productCode' => 'BB001', 'productName' => 'Whipping Cream', 'unit' => 'GR']])
        ->set('items.0.productDetailID', 2112)
        ->set('items.0.purposeID', 10)
        ->set('items.0.qty', -2)
        ->set('items.0.hpp', 45000)
        ->set('additionalInfo', 'Sampling QC')
        ->call('submit')
        ->assertHasNoErrors();

    $journal = QualityControlItemJournal::query()->with('details')->sole();
    expect($journal->item_journal_number)->toBe('IU202609160001')
        ->and($journal->status)->toBe('succeeded')
        ->and($journal->esb_branch_id)->toBe(373)
        ->and($journal->branch_id)->toBeNull()
        ->and((float) $journal->details->sole()->qty)->toBe(-2.0);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://esb.test/core/inventory/item-journal'
        && $request['requestTemplateID'] === null
        && $request['branchID'] === 373
        && $request['itemJournalDetails'][0]['productDetailID'] === 2112);
});

it('uploads and deletes item journal attachments through ESB', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/inventory/item-journal/*/attachment' => Http::sequence()
            ->push(['status' => 'ok', 'result' => ['urls' => ['https://files.test/qc.jpg']]])
            ->push(['status' => 'ok', 'result' => null]),
    ]);
    $service = app(EsbItemJournalService::class);
    $urls = $service->uploadAttachments('BLSS', 'IU202609160001', [['name' => 'qc.jpg', 'contents' => 'image', 'mime' => 'image/jpeg']]);
    $service->deleteAttachments('BLSS', 'IU202609160001');

    expect($urls)->toBe(['https://files.test/qc.jpg']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && str_ends_with($request->url(), '/IU202609160001/attachment'));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/IU202609160001/attachment'));
});
