<?php

use App\Filament\Casual\Pages\StockCardEntryPage;
use App\Filament\Helpdesk\Pages\StockCardSettingsPage;
use App\Filament\Helpdesk\Resources\StockCards\Pages\ViewStockCard;
use App\Models\Branch;
use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Models\StockCardSetting;
use App\Models\User;
use App\Services\EsbStockMovementService;
use App\Services\StockCardCategoryFilter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StockCardSettingSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config(['esb.core.companies' => []]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->branch->esbCodes()->create(['esb_comcode' => 'COM01', 'esb_branch_code' => 'B01', 'is_active' => true]);
    $this->branch->esbCodes()->create(['esb_comcode' => 'COM02', 'esb_branch_code' => 'B02', 'is_active' => true]);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->givePermissionTo(['access backoffice', 'view stock card settings', 'edit stock card settings']);
    $this->staff = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->staff->assignRole('STORE_STAFF');
    Cache::put('stock-movement.categories.COM01', ['RAW' => 'Bahan Baku', 'WIP' => 'Barang WIP'], now()->addHour());
    Cache::put('stock-movement.categories.COM02', ['RAW' => 'Packaging'], now()->addHour());
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

it('shows settings in the inventory sidebar only for permitted users', function () {
    $this->actingAs($this->admin)->get(StockCardSettingsPage::getUrl())
        ->assertSuccessful()->assertSee('Stock Card Settings')->assertSee('Product Category Visibility');
    $this->actingAs($this->staff)->get(StockCardSettingsPage::getUrl())->assertForbidden();
});

it('merges all companies by normalized category name and saves global settings', function () {
    $this->actingAs($this->admin);
    Cache::put('stock-movement.categories.COM02', ['RAW' => '  BAHAN   BAKU ', 'PKG' => 'Packaging'], now()->addHour());
    StockCardSetting::factory()->create(['company_code' => 'COM02', 'categories' => ['Packaging'], 'all_categories' => false]);
    Livewire::test(StockCardSettingsPage::class)->assertDontSee('stock-card-company')
        ->call('loadCategories')->assertSet('categoryOptions', ['Bahan Baku', 'Barang WIP', 'Packaging'])
        ->set('allCategories', false)->set('selectedCategories', ['Bahan Baku'])
        ->set('showUncategorized', false)->call('save')->assertHasNoErrors();
    expect(StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first()->categories)->toBe(['Bahan Baku']);
    expect(StockCardSetting::where('company_code', 'COM02')->first()->categories)->toBe(['Packaging']);
});

it('allows viewing settings but forbids saving without edit permission', function () {
    $this->admin->revokePermissionTo('edit stock card settings');
    $this->actingAs($this->admin);
    Livewire::test(StockCardSettingsPage::class)->call('loadCategories')
        ->assertDontSee('Save Settings')->call('save')->assertForbidden();
    expect(StockCardSetting::count())->toBe(0);
});

it('rejects invalid or empty selected categories', function () {
    $this->actingAs($this->admin);
    Livewire::test(StockCardSettingsPage::class)->call('loadCategories')
        ->set('allCategories', false)->set('selectedCategories', [])->call('save')->assertHasErrors(['selectedCategories']);
    Livewire::test(StockCardSettingsPage::class)->call('loadCategories')
        ->set('allCategories', false)->set('selectedCategories', ['Unknown Category'])->call('save')->assertHasErrors(['selectedCategories.0']);
    expect(StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first()->all_categories)->toBeTrue();
});

it('refreshes all category caches and retains failed company categories and saved selections', function () {
    $this->actingAs($this->admin);
    $setting = StockCardSetting::factory()->create([
        'company_code' => StockCardSetting::GLOBAL_COMPANY, 'all_categories' => false, 'categories' => ['Bahan Baku'],
        'category_sources' => ['COM01' => ['Bahan Baku'], 'COM02' => ['Old Packaging']],
    ]);
    $this->mock(EsbStockMovementService::class, function ($mock) {
        $mock->shouldReceive('categories')->with('COM01')->once()->andThrow(new RuntimeException('ESB unavailable'));
        $mock->shouldReceive('categories')->with('COM02')->once()->andReturn(['PKG' => 'Packaging']);
    });
    Livewire::test(StockCardSettingsPage::class)->call('refreshCategories')
        ->assertSet('categoryOptions', ['Bahan Baku', 'Packaging'])->assertSet('failedCompanies', ['COM01'])
        ->assertSee('Kategori gagal diperbarui')->call('save')->assertHasNoErrors();
    expect($setting->fresh()->categories)->toBe(['Bahan Baku']);
    expect($setting->fresh()->category_sources)->toBe(['COM01' => ['Bahan Baku'], 'COM02' => ['Packaging']]);
    expect(Cache::has('stock-movement.categories.COM01'))->toBeFalse();
    expect(Cache::has('stock-movement.categories.COM02'))->toBeFalse();
});

it('filters category sources per company including shared product codes and uncategorized products', function () {
    $products = [
        ['product_code' => 'SHARED', 'category_sources' => ['COM01' => 'Barang WIP', 'COM02' => 'Packaging']],
        ['product_code' => 'RAW', 'category_sources' => ['COM01' => 'Bahan Baku']],
        ['product_code' => 'WIP', 'category_sources' => ['COM01' => 'Barang WIP']],
        ['product_code' => 'NONE', 'category_sources' => ['COM01' => '']],
    ];
    $snapshot = [
        'COM01' => ['all_categories' => false, 'categories' => ['Bahan Baku'], 'show_uncategorized' => false],
        'COM02' => ['all_categories' => false, 'categories' => ['Packaging'], 'show_uncategorized' => false],
    ];
    $filter = app(StockCardCategoryFilter::class);
    expect(array_column($filter->filter($products, $snapshot), 'product_code'))->toBe(['SHARED', 'RAW']);
    $snapshot['COM01']['show_uncategorized'] = true;
    expect(array_column($filter->filter($products, $snapshot), 'product_code'))->toBe(['SHARED', 'RAW', 'NONE']);
    expect($filter->filter($products, []))->toBe($products);
});

function cacheSettingsTestCatalog(Branch $branch): void
{
    app(EsbStockMovementService::class)->cacheStockCardCatalog($branch, now()->toDateString(), 'stockUnit', [
        'products' => [
            ['product_code' => 'RAW', 'product_name' => 'Raw Material', 'category' => 'Bahan Baku', 'category_sources' => ['COM01' => 'Bahan Baku'], 'unit' => 'KG'],
            ['product_code' => 'WIP', 'product_name' => 'WIP Material', 'category' => 'Barang WIP', 'category_sources' => ['COM01' => 'Barang WIP'], 'unit' => 'KG'],
        ],
        'period_from' => now()->toDateString(), 'period_to' => now()->toDateString(), 'failed_requests' => 0,
    ]);
}

it('snapshots rules for new reports and does not change them when settings change', function () {
    $setting = StockCardSetting::factory()->create(['company_code' => StockCardSetting::GLOBAL_COMPANY, 'all_categories' => false, 'categories' => ['Bahan Baku']]);
    cacheSettingsTestCatalog($this->branch);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);
    Livewire::test(StockCardEntryPage::class)->call('loadProductCatalog')->assertCount('rows', 1)
        ->assertSet('rows.0.product_code', 'RAW')->set('rows.0.actual_qty', '5');
    $card = StockCard::firstOrFail();
    expect($card->category_settings_snapshot['COM01']['categories'])->toBe(['Bahan Baku']);
    $setting->update(['categories' => ['Barang WIP']]);
    Livewire::test(StockCardEntryPage::class)->call('loadProductCatalog')->assertCount('rows', 1)
        ->assertSet('rows.0.product_code', 'RAW')->assertSet('rows.0.actual_qty', '5');
    expect($card->fresh()->category_settings_snapshot['COM01']['categories'])->toBe(['Bahan Baku']);
});

it('keeps legacy reports unfiltered and preserves existing staff entries', function () {
    StockCardSetting::factory()->create(['company_code' => StockCardSetting::GLOBAL_COMPANY, 'all_categories' => false, 'categories' => ['Bahan Baku']]);
    $card = StockCard::factory()->create(['branch_id' => $this->branch->id, 'report_date' => now()->toDateString()]);
    StockCardEntry::factory()->create(['stock_card_id' => $card->id, 'product_code' => 'OLD', 'product_name' => 'Saved Product', 'actual_qty' => 7]);
    cacheSettingsTestCatalog($this->branch);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);
    Livewire::test(StockCardEntryPage::class)->call('loadProductCatalog')->assertCount('rows', 3);
    expect($card->fresh()->category_settings_snapshot)->toBeNull();
    expect($card->entries()->where('product_code', 'OLD')->first()->actual_qty)->toBe('7.0000');
});

it('uses all categories by default for new reports', function () {
    cacheSettingsTestCatalog($this->branch);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);
    Livewire::test(StockCardEntryPage::class)->call('loadProductCatalog')->assertCount('rows', 2);
    expect(StockCard::firstOrFail()->category_settings_snapshot['COM01']['all_categories'])->toBeTrue();
});

it('registers settings permissions without changing role assignments or removing existing permissions', function () {
    $permissions = $this->admin->getAllPermissions()->pluck('name')->sort()->values()->all();
    $count = Permission::count();
    $this->seed(StockCardSettingSeeder::class);
    $this->seed(StockCardSettingSeeder::class);
    expect(Permission::count())->toBe($count);
    expect($this->admin->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe($permissions);
});

it('includes configured companies without branch mappings and searches merged categories', function () {
    config(['esb.core.companies' => ['COM03' => ['username' => 'test', 'password' => 'test']]]);
    Cache::put('stock-movement.categories.COM03', ['OTHER' => 'Other Category'], now()->addHour());
    $this->actingAs($this->admin);
    $page = Livewire::test(StockCardSettingsPage::class)->call('loadCategories')
        ->assertSet('categoryOptions', ['Bahan Baku', 'Barang WIP', 'Other Category', 'Packaging'])
        ->set('allCategories', false)->set('selectedCategories', ['Packaging'])->set('categorySearch', 'bahan');
    expect($page->instance()->visibleCategories())->toBe(['Bahan Baku']);
    $page->set('categorySearch', 'OTHER');
    expect($page->instance()->visibleCategories())->toBe(['Other Category']);
    $page->set('categorySearch', '')->call('save')->assertHasNoErrors();
    expect(StockCardSetting::where('company_code', StockCardSetting::GLOBAL_COMPANY)->first()->categories)->toBe(['Packaging']);
});

it('applies global normalized categories to every company while preserving historical exact matching', function () {
    StockCardSetting::factory()->create(['company_code' => StockCardSetting::GLOBAL_COMPANY, 'all_categories' => false, 'categories' => ['Bahan Baku'], 'show_uncategorized' => false]);
    $filter = app(StockCardCategoryFilter::class);
    $snapshot = $filter->snapshot($this->branch);
    $products = [
        ['product_code' => 'A', 'category_sources' => ['COM01' => 'BAHAN   BAKU']],
        ['product_code' => 'B', 'category_sources' => ['COM02' => ' bahan baku ']],
        ['product_code' => 'C', 'category_sources' => ['COM02' => 'Packaging']],
    ];
    expect(array_column($filter->filter($products, $snapshot), 'product_code'))->toBe(['A', 'B']);
    unset($snapshot['COM01']['normalize_names'], $snapshot['COM02']['normalize_names']);
    expect($filter->filter($products, $snapshot))->toBe([]);
});

it('restores old per-company report snapshots even after switching settings to global', function () {
    StockCardSetting::factory()->create(['company_code' => StockCardSetting::GLOBAL_COMPANY, 'all_categories' => false, 'categories' => ['Bahan Baku']]);
    StockCard::factory()->create([
        'branch_id' => $this->branch->id, 'report_date' => now()->toDateString(),
        'category_settings_snapshot' => ['COM01' => ['all_categories' => false, 'categories' => ['Barang WIP'], 'show_uncategorized' => false]],
    ]);
    cacheSettingsTestCatalog($this->branch);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);
    Livewire::test(StockCardEntryPage::class)->call('loadProductCatalog')->assertCount('rows', 1)->assertSet('rows.0.product_code', 'WIP');
});

it('filters back office detail using global settings for legacy reports while preserving staff input', function () {
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);
    StockCardSetting::factory()->create(['company_code' => StockCardSetting::GLOBAL_COMPANY, 'all_categories' => false, 'categories' => ['Bahan Baku'], 'show_uncategorized' => false]);
    $card = StockCard::factory()->create([
        'branch_id' => $this->branch->id,
        'movement_snapshot' => [
            'rows' => [
                ['productCode' => 'RAW', 'productName' => 'Raw Product', 'unit' => 'KG', 'totalQty' => 10, 'companies' => ['COM01']],
                ['productCode' => 'WIP', 'productName' => 'Excluded WIP', 'unit' => 'KG', 'totalQty' => 8, 'companies' => ['COM01']],
            ], 'types' => ['Beginning'], 'transactions' => [], 'units' => [],
        ],
    ]);
    StockCardEntry::factory()->create(['stock_card_id' => $card->id, 'product_code' => 'WIP', 'product_category' => 'Barang WIP', 'actual_qty' => null, 'reported_qty' => null, 'notes' => null, 'supervisor_notes' => null]);
    StockCardEntry::factory()->create(['stock_card_id' => $card->id, 'product_code' => 'OLD', 'product_name' => 'Saved Staff Product', 'actual_qty' => 0, 'reported_qty' => 0]);
    $page = Livewire::test(ViewStockCard::class, ['record' => $card])
        ->assertSee('Raw Product')->assertSee('Saved Staff Product')->assertDontSee('Excluded WIP');
    expect($page->instance()->filteredMovementBalances()->pluck('productCode')->all())->toBe(['RAW', 'OLD']);
    expect($card->fresh()->category_settings_snapshot)->toBeNull();
    expect($card->entries()->count())->toBe(2);
});

it('uses historical category snapshots in back office even when global settings differ', function () {
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);
    StockCardSetting::factory()->create(['company_code' => StockCardSetting::GLOBAL_COMPANY, 'all_categories' => false, 'categories' => ['Bahan Baku']]);
    $card = StockCard::factory()->create([
        'branch_id' => $this->branch->id,
        'category_settings_snapshot' => ['COM01' => ['all_categories' => false, 'categories' => ['Barang WIP'], 'show_uncategorized' => false]],
        'movement_snapshot' => [
            'rows' => [
                ['productCode' => 'RAW', 'productName' => 'Excluded Raw Product', 'unit' => 'KG', 'totalQty' => 10, 'companies' => ['COM01']],
                ['productCode' => 'WIP', 'productName' => 'Historical WIP', 'unit' => 'KG', 'totalQty' => 8, 'companies' => ['COM01']],
            ], 'types' => ['Beginning'], 'transactions' => [], 'units' => [],
        ],
    ]);
    Livewire::test(ViewStockCard::class, ['record' => $card])
        ->assertSee('Historical WIP')->assertDontSee('Excluded Raw Product');
});

it('reports category lookup failure in detail without displaying unclassified movement products', function () {
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);
    $this->mock(EsbStockMovementService::class, function ($mock) {
        $mock->shouldReceive('categories')->with('COM01')->once()->andThrow(new RuntimeException('Unavailable'));
        $mock->shouldReceive('transactionTypes')->andReturn(['Beginning']);
    });
    $card = StockCard::factory()->create([
        'branch_id' => $this->branch->id,
        'category_settings_snapshot' => ['COM01' => ['all_categories' => false, 'categories' => ['Bahan Baku'], 'show_uncategorized' => true]],
        'movement_snapshot' => ['rows' => [['productCode' => 'RAW', 'productName' => 'Unclassified Product', 'unit' => 'KG', 'totalQty' => 10, 'companies' => ['COM01']]], 'types' => ['Beginning'], 'transactions' => [], 'units' => []],
    ]);
    StockCardEntry::factory()->create(['stock_card_id' => $card->id, 'product_code' => 'OLD', 'product_name' => 'Saved Staff Product', 'actual_qty' => 5]);
    Livewire::test(ViewStockCard::class, ['record' => $card])
        ->assertSet('categoryMappingFailures', ['COM01'])->assertSee('Kategori produk gagal dimuat')
        ->assertDontSee('Unclassified Product')->assertSee('Saved Staff Product');
});

it('groups and sorts products by category on the staff entry page', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);

    $page = Livewire::test(StockCardEntryPage::class)
        ->set('rows', [
            ['product_code' => 'PKG-01', 'product_name' => 'Zebra Box', 'product_category' => 'Packaging', 'system_unit' => 'PCS', 'actual_qty' => '', 'notes' => ''],
            ['product_code' => 'RAW-02', 'product_name' => 'Banana', 'product_category' => 'Bahan Baku', 'system_unit' => 'KG', 'actual_qty' => '', 'notes' => ''],
            ['product_code' => 'RAW-01', 'product_name' => 'Apple', 'product_category' => 'Bahan Baku', 'system_unit' => 'KG', 'actual_qty' => '', 'notes' => ''],
        ])
        ->set('rows.2.actual_qty', '7')
        ->set('rows.2.notes', 'Sudah dihitung')
        ->assertSet('rows.2.actual_qty', '7')
        ->assertSet('rows.2.notes', 'Sudah dihitung')
        ->assertSee('aria-label="Kategori sebelumnya"', escape: false)
        ->assertSee('aria-label="Kategori selanjutnya"', escape: false);

    expect($page->html())
        ->toMatch('/data-stock-category-page="0".*data-stock-category="Bahan Baku".*Apple.*Banana.*data-stock-category-page="1".*data-stock-category="Packaging".*Zebra Box/s');
});

it('groups and sorts products by category on the back office detail page', function () {
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);

    $card = StockCard::factory()->create([
        'branch_id' => $this->branch->id,
        'movement_snapshot' => [
            'rows' => [
                ['productCode' => 'PKG-01', 'productName' => 'Zebra Box', 'unit' => 'PCS', 'totalQty' => 2],
                ['productCode' => 'RAW-02', 'productName' => 'Banana', 'unit' => 'KG', 'totalQty' => 3],
                ['productCode' => 'RAW-01', 'productName' => 'Apple', 'unit' => 'KG', 'totalQty' => 4],
            ],
            'types' => [],
            'transactions' => [],
            'units' => [],
        ],
    ]);

    foreach ([
        ['code' => 'PKG-01', 'name' => 'Zebra Box', 'category' => 'Packaging'],
        ['code' => 'RAW-02', 'name' => 'Banana', 'category' => 'Bahan Baku'],
        ['code' => 'RAW-01', 'name' => 'Apple', 'category' => 'Bahan Baku'],
    ] as $product) {
        StockCardEntry::factory()->create([
            'stock_card_id' => $card->id,
            'product_code' => $product['code'],
            'product_name' => $product['name'],
            'product_category' => $product['category'],
        ]);
    }

    Livewire::test(ViewStockCard::class, ['record' => $card])
        ->assertSeeInOrder([
            'data-stock-category-row="Bahan Baku"',
            'Apple',
            'Banana',
            'data-stock-category-row="Packaging"',
            'Zebra Box',
        ], escape: false);
});
