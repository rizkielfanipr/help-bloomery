<?php

use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Filament\Helpdesk\Pages\BomAdjustmentPage;
use App\Filament\Helpdesk\Pages\EditBomAdjustmentPage;
use App\Models\RndBomCatalog;
use App\Models\RndBomChangeLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->editor = User::factory()->create(['is_active' => true]);
    $this->editor->givePermissionTo(['access backoffice', 'view bill of materials', 'edit bill of materials']);
    $this->actingAs($this->editor);

    config([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'global-user',
        'esb.core.password' => 'global-password',
        'esb.master_product.base_url' => 'https://core-api.esb.test',
        'esb.master_product.token' => 'master-token',
    ]);
    Cache::flush();
});

function editorBomDetail(array $overrides = []): array
{
    return array_merge([
        'bomID' => 55,
        'bomTypeID' => 1,
        'bomTypeName' => 'Assembly',
        'bomName' => 'Croissant Assembly',
        'bomCode' => 'BOM-CRS',
        'productDetailID' => 100,
        'productName' => 'Croissant',
        'productCode' => 'CRS',
        'uomName' => 'PCS',
        'editedDate' => '2026-07-30T10:00:00+07:00',
        'bomDetails' => [[
            'ID' => 7, 'productID' => 2, 'productDetailID' => 200, 'productName' => 'Butter',
            'productCode' => 'BTR', 'uomName' => 'GRAM', 'qty' => 100, 'lastHpp' => 125,
            'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '',
        ]],
    ], $overrides);
}

it('loads the BOM detail on mount and shows a load error with retry on failure', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::response(['status' => 'fail', 'message' => 'BOM not found'], 404),
    ]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->assertSee('BOM belum dapat dimuat')
        ->assertSee('Coba Lagi');
});

it('lets the editor pick a component product with duplicate prevention', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::response(['status' => 'ok', 'result' => editorBomDetail()]),
    ]);

    $page = Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->set('productOptions', [
            200 => ['productDetailID' => 200, 'productCode' => 'BTR', 'productName' => 'Butter', 'categoryName' => 'Dairy', 'subCategoryName' => 'Butter', 'baseUnit' => 'GRAM', 'unit' => 'GRAM', 'conversionFactor' => 1, 'basePrice' => 5, 'receiptTolerance' => 0],
            201 => ['productDetailID' => 201, 'productCode' => 'FLR', 'productName' => 'Flour', 'categoryName' => 'Dry Goods', 'subCategoryName' => 'Flour', 'baseUnit' => 'GRAM', 'unit' => 'GRAM', 'conversionFactor' => 1, 'basePrice' => 3, 'receiptTolerance' => 0],
        ]);

    $page->call('selectProduct', 'component', 200)->assertNotified('Product sudah menjadi komponen BOM');
    expect($page->get('draft.bomDetails'))->toHaveCount(1);

    $page->call('selectProduct', 'component', 201);
    expect($page->get('draft.bomDetails'))->toHaveCount(2)
        ->and($page->get('draft.bomDetails')[1]['productCode'])->toBe('FLR');
});

it('requires a reason before opening the preview', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::response(['status' => 'ok', 'result' => editorBomDetail()]),
    ]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->set('reason', '')
        ->call('openPreview')
        ->assertHasErrors(['reason' => 'required']);
});

it('updates the BOM successfully, logs it as bom_adjustment source, and syncs the local catalog', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::sequence()
            ->push(['status' => 'ok', 'result' => editorBomDetail()])
            ->push(['status' => 'ok', 'result' => editorBomDetail()])
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => editorBomDetail(['editedDate' => '2026-07-30T11:00:00+07:00', 'bomDetails' => [[
                'ID' => 7, 'productID' => 2, 'productDetailID' => 200, 'productName' => 'Butter',
                'productCode' => 'BTR', 'uomName' => 'GRAM', 'qty' => 175, 'lastHpp' => 125,
                'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '',
            ]]])]),
    ]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->set('reason', 'Naikkan porsi butter untuk batch besar')
        ->set('draft.bomDetails.0.qty', 175)
        ->call('openPreview')
        ->assertSee('Butter')
        ->call('submit')
        ->assertRedirect(BomAdjustmentPage::getUrl());

    $log = RndBomChangeLog::query()->where('esb_bom_id', 55)->sole();
    expect($log->source)->toBe(RndBomChangeLogSource::BomAdjustment)
        ->and($log->status)->toBe(RndBomChangeLogStatus::Success)
        ->and($log->reason)->toBe('Naikkan porsi butter untuk batch besar');

    $catalog = RndBomCatalog::query()->where('esb_bom_id', 55)->sole();
    expect($catalog->component_count)->toBe(1)
        ->and($catalog->is_active)->toBeTrue()
        ->and(data_get($catalog->detail_snapshot, 'bomDetails.0.qty'))->toBe(175);
});

it('stops the update and shows an inline error when editedDate conflicts', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::sequence()
            ->push(['status' => 'ok', 'result' => editorBomDetail()])
            ->push(['status' => 'ok', 'result' => editorBomDetail(['editedDate' => '2026-08-01T00:00:00+07:00'])]),
    ]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->set('reason', 'Update')
        ->call('openPreview')
        ->call('submit')
        ->assertHasErrors(['draft.bomDetails']);

    expect(RndBomChangeLog::query()->count())->toBe(0);
});

it('shows a danger notification without redirecting when ESB rejects the mutation', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::sequence()
            ->push(['status' => 'ok', 'result' => editorBomDetail()])
            ->push(['status' => 'ok', 'result' => editorBomDetail()])
            ->push(['status' => 'fail', 'errors' => [['message' => 'Quantity tidak valid']]], 422),
    ]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->set('reason', 'Update')
        ->call('openPreview')
        ->call('submit')
        ->assertNotified('BOM gagal diperbarui')
        ->assertNoRedirect();

    $log = RndBomChangeLog::query()->where('esb_bom_id', 55)->sole();
    expect($log->status)->toBe(RndBomChangeLogStatus::Failed);
});

it('rejects removing components below the minimum of one', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::response(['status' => 'ok', 'result' => editorBomDetail()]),
    ]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->call('removeComponent', 0)
        ->assertHasErrors(['draft.bomDetails']);
});

it('shows a change timeline for this BOM with an expandable component diff', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::response(['status' => 'ok', 'result' => editorBomDetail()]),
    ]);

    $actor = User::factory()->create(['username' => 'kasih.budi']);
    RndBomChangeLog::factory()->create([
        'esb_bom_id' => 55,
        'reason' => 'Naikkan porsi butter',
        'changed_by' => $actor->id,
        'changes' => [
            'has_changes' => true,
            'components_added' => [['productCode' => 'SGR', 'productName' => 'Sugar', 'uomName' => 'GR', 'qty' => 20]],
            'components_removed' => [],
            'components_changed' => [],
        ],
    ]);
    RndBomChangeLog::factory()->create(['esb_bom_id' => 99999]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->assertSee('Riwayat Perubahan')
        ->assertSee('kasih.budi')
        ->assertSee('Naikkan porsi butter')
        ->assertSee('Sugar')
        ->assertSee('Added');
});

it('shows an empty state when a BOM has no change history yet', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/55' => Http::response(['status' => 'ok', 'result' => editorBomDetail()]),
    ]);

    Livewire::test(EditBomAdjustmentPage::class, ['bomId' => 55])
        ->assertSee('Belum ada riwayat perubahan untuk BOM ini.');
});
