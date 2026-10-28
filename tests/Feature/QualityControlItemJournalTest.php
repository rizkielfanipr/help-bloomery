<?php

use App\Filament\Casual\Pages\ItemJournalPage;
use App\Models\QualityControlItemJournal;
use App\Models\User;
use App\Services\EsbItemJournalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

it('shows current available stock for the selected branch and location', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'company-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [['branchID' => 373, 'branchCode' => 'BLA', 'branchName' => 'Bloomery Test']]]),
        'https://esb.test/core/location*' => Http::response(['status' => 'ok', 'result' => [['locationID' => 964, 'locationName' => 'Kitchen']]]),
        'https://esb.test/core/purpose*' => Http::response(['status' => 'ok', 'result' => ['data' => [], 'next' => '']]),
        'https://esb.test/core/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1,
            'limit' => 20,
            'count' => 1,
            'next' => '',
            'prev' => '',
            'data' => [['productCode' => 'BB001', 'productName' => 'Whipping Cream']],
        ]]),
        'https://esb.test/core/report/stock-movement*' => Http::response(['status' => 'ok', 'result' => [
            'data' => [[
                'branchCode' => 'BLA',
                'productCode' => 'BB001',
                'UOM' => 'GR',
                'locationID' => 964,
                'documentDate' => now()->toDateString(),
                'createdDate' => now()->toDateTimeString(),
                'qtyBalance' => 12.5,
            ]],
            'next' => '',
            'count' => 1,
        ]]),
        'https://master-product.test/corev1/master/product*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1,
            'limit' => 10,
            'count' => 1,
            'next' => '',
            'data' => [[
                'productCode' => 'BB001',
                'productName' => 'Whipping Cream',
                'productDetails' => [['productDetailID' => 2112, 'unit' => 'GR']],
            ]],
        ]]),
    ]);

    $component = Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('companyCode', 'BLSS')
        ->set('branchId', '373')
        ->set('locationId', '964')
        ->call('openProductPicker', 0)
        ->call('loadProducts');

    $component
        ->assertSet('stockLoadWarning', null)
        ->assertSet('productOptions.2112.availableStock', 12.5)
        ->assertSee('Stok saat ini:')
        ->assertSee('12,5 GR')
        ->call('selectProduct', 2112)
        ->assertSet('items.0.availableStock', 12.5)
        ->assertSet('items.0.availableStockUnit', 'GR');
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
                    'productDetails' => [['productDetailID' => 2112, 'unit' => 'GR']],
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

    $component = Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->assertSee('Informasi Pengisian')
        ->assertSee('Ambil Foto')
        ->assertDontSee('Tambah Foto / PDF')
        ->assertSee('Kirim Item Journal ke ESB')
        ->set('companyCode', 'BLSS')
        ->set('branchId', '373')
        ->set('productOptions', [['productDetailID' => 2112, 'productCode' => 'BB001', 'productName' => 'Whipping Cream', 'unit' => 'GR']])
        ->set('items.0.productDetailID', 2112)
        ->set('items.0.purposeID', 10)
        ->set('items.0.adjustmentMode', 'decrease')
        ->set('items.0.qty', 2)
        ->set('items.0.hpp', 45000)
        ->set('additionalInfo', 'Sampling QC')
        ->call('submit')
        ->assertHasNoErrors();

    $component->call('submit')->assertHasNoErrors();

    $journal = QualityControlItemJournal::query()->with('details')->sole();
    expect($journal->item_journal_number)->toBe('IU202609160001')
        ->and($journal->status)->toBe('succeeded')
        ->and($journal->esb_branch_id)->toBe(373)
        ->and($journal->branch_id)->toBeNull()
        ->and($journal->submission_key)->not->toBeNull()
        ->and($journal->payload_hash)->toHaveLength(64)
        ->and($journal->attempted_at)->not->toBeNull()
        ->and((float) $journal->details->sole()->qty)->toBe(-2.0);

    expect(QualityControlItemJournal::query()->count())->toBe(1);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://esb.test/core/inventory/item-journal'
        && $request['requestTemplateID'] === null
        && $request['branchID'] === 373
        && $request['itemJournalDetails'][0]['productDetailID'] === 2112
        && (float) $request['itemJournalDetails'][0]['qty'] === -2.0);
    Http::assertSentCount(5);
});

it('marks a connection failure as unknown for reconciliation', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [['branchID' => 373, 'branchCode' => 'BLA', 'branchName' => 'Bloomery Test']]]),
        'https://esb.test/core/location*' => Http::response(['status' => 'ok', 'result' => [['locationID' => 964, 'locationName' => 'Kitchen']]]),
        'https://esb.test/core/purpose*' => Http::response(['status' => 'ok', 'result' => ['data' => [['purposeID' => 10, 'purposeName' => 'Sampling QC', 'purposeAppliedTo' => ['ITEM JOURNAL'], 'flagActive' => true]], 'next' => '']]),
        'https://esb.test/core/inventory/item-journal' => Http::failedConnection('connection reset after send'),
    ]);

    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('companyCode', 'BLSS')
        ->set('branchId', '373')
        ->set('productOptions', [['productDetailID' => 2112, 'productCode' => 'BB001', 'productName' => 'Whipping Cream', 'unit' => 'GR']])
        ->set('items.0.productDetailID', 2112)
        ->set('items.0.purposeID', 10)
        ->set('items.0.adjustmentMode', 'decrease')
        ->set('items.0.qty', 2)
        ->call('submit');

    $journal = QualityControlItemJournal::query()->sole();
    expect($journal->status)->toBe('unknown')
        ->and($journal->item_journal_number)->toBeNull()
        ->and($journal->payload_hash)->toHaveLength(64);
});

it('no longer links Item Journal from the Quality Control bottom navigation', function () {
    $this->get(route('filament.casual.pages.quality-control-audits'))
        ->assertOk()
        ->assertDontSee(route('filament.casual.pages.item-journal-page'), false)
        ->assertDontSee('Item Journal');
});

it('renders a camera-first image-only photo input without the Quality Control navigation', function () {
    $html = Livewire::test(ItemJournalPage::class)
        ->assertDontSee(route('filament.casual.pages.quality-control-audits'), false)
        ->call('openForm')
        ->assertSee('Ambil Foto')
        ->assertSee('sebagian browser tetap menawarkan galeri')
        ->html();

    expect($html)->toContain('wire:model="photoUpload" accept="image/*" capture="environment"')
        ->not->toContain('.pdf')
        ->not->toContain('multiple accept');
});

it('accepts JPEG, PNG, and WebP photos and shows a preview with retake and delete', function (string $name) {
    $photo = UploadedFile::fake()->image($name, 640, 480);

    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('photoUpload', $photo)
        ->assertHasNoErrors()
        ->assertSet('photoUpload', null)
        ->assertCount('attachments', 1)
        ->assertSee('Ambil Ulang')
        ->assertSee('Hapus')
        ->assertSeeHtml('wire:model="retakeUploads.0"');
})->with(['qc.jpg', 'qc.png', 'qc.webp']);

it('rejects PDF and other non-image files as new attachments', function (UploadedFile $file) {
    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('photoUpload', $file)
        ->assertHasErrors('photoUpload')
        ->assertSee('File harus berupa foto JPG, PNG, atau WebP.')
        ->assertCount('attachments', 0);
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
    'text renamed as jpg' => fn () => UploadedFile::fake()->createWithContent('bukti.jpg', 'not an image'),
    'gif' => fn () => UploadedFile::fake()->image('bukti.gif'),
]);

it('rejects photos above 5 MB and more than five photos', function () {
    $component = Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('photoUpload', UploadedFile::fake()->image('besar.jpg')->size(5121))
        ->assertHasErrors('photoUpload')
        ->assertSee('Ukuran foto maksimal 5 MB.')
        ->assertCount('attachments', 0);

    foreach (range(1, 5) as $number) {
        $component->set('photoUpload', UploadedFile::fake()->image("qc-{$number}.jpg"));
    }

    $component->assertCount('attachments', 5)
        ->assertSee('Batas 5 foto tercapai.')
        ->set('photoUpload', UploadedFile::fake()->image('qc-6.jpg'))
        ->assertHasErrors('photoUpload')
        ->assertCount('attachments', 5);
});

it('replaces a photo on retake and removes it on delete', function () {
    $component = Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('photoUpload', UploadedFile::fake()->image('first.jpg'))
        ->set('photoUpload', UploadedFile::fake()->image('second.jpg'))
        ->set('retakeUploads.0', UploadedFile::fake()->image('retake.png'));

    expect(collect($component->get('attachments'))->map->getClientOriginalName()->all())->toBe(['retake.png', 'second.jpg'])
        ->and($component->get('retakeUploads'))->toBe([]);

    $component->set('retakeUploads.1', UploadedFile::fake()->create('swap.pdf', 10, 'application/pdf'))
        ->assertHasErrors('photoUpload');
    expect(collect($component->get('attachments'))->map->getClientOriginalName()->all())->toBe(['retake.png', 'second.jpg']);

    $component->call('removeAttachment', 0);
    expect(collect($component->get('attachments'))->map->getClientOriginalName()->all())->toBe(['second.jpg']);
});

it('rejects a PDF placed directly into attachments on submit', function () {
    Http::fake();

    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('attachments', [UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
        ->call('submit')
        ->assertHasErrors('attachments.0');

    expect(QualityControlItemJournal::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('stores a new photo with its detected MIME and sends it to ESB after the journal is created', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => [['branchID' => 373, 'branchCode' => 'BLA', 'branchName' => 'Bloomery Test']]]),
        'https://esb.test/core/location*' => Http::response(['status' => 'ok', 'result' => [['locationID' => 964, 'locationName' => 'Kitchen']]]),
        'https://esb.test/core/purpose*' => Http::response(['status' => 'ok', 'result' => ['data' => [['purposeID' => 10, 'purposeName' => 'Sampling', 'purposeAppliedTo' => ['ITEM JOURNAL'], 'flagActive' => true]], 'next' => '']]),
        'https://esb.test/core/inventory/item-journal/*/attachment' => Http::response(['status' => 'ok', 'result' => ['urls' => ['https://files.test/bukti.png']]]),
        'https://esb.test/core/inventory/item-journal' => Http::response(['status' => 'ok', 'result' => ['itemJournalNum' => 'IU202610280001']]),
    ]);

    Livewire::test(ItemJournalPage::class)
        ->call('openForm')
        ->set('companyCode', 'BLSS')
        ->set('branchId', '373')
        ->set('productOptions', [['productDetailID' => 2112, 'productCode' => 'BB001', 'productName' => 'Whipping Cream', 'unit' => 'GR']])
        ->set('items.0.productDetailID', 2112)
        ->set('items.0.purposeID', 10)
        ->set('items.0.qty', 1)
        ->set('photoUpload', UploadedFile::fake()->image('bukti.png'))
        ->call('submit')
        ->assertHasNoErrors();

    $journal = QualityControlItemJournal::query()->with('attachments')->sole();
    $attachment = $journal->attachments->sole();
    expect($journal->status)->toBe('succeeded')
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->original_name)->toBe('bukti.png')
        ->and($attachment->file_path)->not->toContain('bukti.png')
        ->and($attachment->esb_url)->toBe('https://files.test/bukti.png');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://esb.test/core/inventory/item-journal'
        && array_keys($request['itemJournalDetails'][0]) === ['ID', 'productDetailID', 'purposeID', 'qty', 'hpp']
        && $request['itemJournalDetails'][0]['productDetailID'] === 2112
        && (float) $request['itemJournalDetails'][0]['qty'] === -1.0);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && collect($request->data())->contains(fn (array $part): bool => ($part['filename'] ?? null) === 'bukti.png'));
});

function legacyPdfJournal(User $owner, string $status = 'attachment_failed'): QualityControlItemJournal
{
    $journal = QualityControlItemJournal::query()->create([
        'created_by' => $owner->id,
        'item_journal_number' => 'IU202609010001',
        'esb_comcode' => 'BLSS',
        'esb_branch_id' => 373,
        'esb_branch_code' => 'BLA',
        'location_id' => 964,
        'location_name' => 'Kitchen',
        'journal_date' => '2026-09-01',
        'status' => $status,
        'submission_key' => (string) Str::uuid(),
    ]);
    Storage::disk('b2')->put('quality-control/item-journals/'.$journal->id.'/legacy.pdf', '%PDF-1.4 legacy');
    $journal->attachments()->create([
        'uploaded_by' => $owner->id,
        'file_path' => 'quality-control/item-journals/'.$journal->id.'/legacy.pdf',
        'original_name' => 'legacy.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 15,
    ]);

    return $journal;
}

it('retries a historical PDF attachment with its stored MIME type', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/inventory/item-journal/*/attachment' => Http::response(['status' => 'ok', 'result' => ['urls' => ['https://files.test/legacy.pdf']]]),
    ]);
    $journal = legacyPdfJournal($this->user);

    Livewire::test(ItemJournalPage::class)
        ->assertSee('IU202609010001')
        ->call('retryAttachments', $journal->id);

    $attachment = $journal->attachments()->sole();
    expect($journal->refresh()->status)->toBe('succeeded')
        ->and($attachment->esb_url)->toBe('https://files.test/legacy.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && collect($request->data())->contains(fn (array $part): bool => ($part['filename'] ?? null) === 'legacy.pdf'));
});

it('deletes historical PDF attachments without touching the journal', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/inventory/item-journal/*/attachment' => Http::response(['status' => 'ok', 'result' => null]),
    ]);
    $journal = legacyPdfJournal($this->user, 'succeeded');

    Livewire::test(ItemJournalPage::class)->call('deleteAllAttachments', $journal->id);

    expect($journal->attachments()->count())->toBe(0)
        ->and(Storage::disk('b2')->exists('quality-control/item-journals/'.$journal->id.'/legacy.pdf'))->toBeFalse()
        ->and($journal->refresh()->item_journal_number)->toBe('IU202609010001')
        ->and($journal->status)->toBe('succeeded');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/IU202609010001/attachment'));
});

it('refuses retry and delete on another users journal', function () {
    Http::fake();
    $journal = legacyPdfJournal(User::factory()->create());

    Livewire::test(ItemJournalPage::class)->call('retryAttachments', $journal->id)->assertForbidden();
    Livewire::test(ItemJournalPage::class)->call('deleteAllAttachments', $journal->id)->assertForbidden();

    expect($journal->attachments()->count())->toBe(1);
    Http::assertNothingSent();
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
