<?php

use App\Actions\Ordering\ExportOrderingRecommendationsXlsxAction;
use App\Filament\Helpdesk\Pages\OrderingRecommendationPage;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\OrderingDailyMetric;
use App\Models\OrderingForecastItem;
use App\Models\OrderingForecastRun;
use App\Models\OrderingSyncRun;
use App\Models\User;
use App\Services\Ordering\OrderingReasonCodes;
use App\Services\Ordering\OrderingRecommendationQuery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Http::fake();

    $this->alpha = Branch::factory()->create(['name' => 'Branch Alpha']);
    $this->beta = Branch::factory()->create(['name' => 'Branch Beta']);
    foreach ([$this->alpha, $this->beta] as $branch) {
        $branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'B'.$branch->id, 'is_active' => true]);
    }

    $this->alphaRun = OrderingForecastRun::factory()->create(['branch_id' => $this->alpha->id, 'completeness' => ['pairs' => [['company_code' => 'BLSS', 'usage_valid_days' => 56, 'usage_missing_days' => 0, 'failed_runs' => 0]], 'warnings' => ['stock_location_unconfirmed']]]);
    $this->betaRun = OrderingForecastRun::factory()->create(['branch_id' => $this->beta->id]);
    $this->alphaItem = OrderingForecastItem::factory()->create(['ordering_forecast_run_id' => $this->alphaRun->id, 'product_code' => '000123', 'product_name' => 'Croissant Dough', 'priority_score' => 0.9]);
    $this->alphaBlocked = OrderingForecastItem::factory()->blocked('stock_location_unconfirmed')->create(['ordering_forecast_run_id' => $this->alphaRun->id, 'product_name' => 'Kopi Susu Base', 'uom' => 'GR', 'explanation' => 'Tidak dapat dihitung: Lokasi stok branch belum dipilih.']);
    $this->betaItem = OrderingForecastItem::factory()->create(['ordering_forecast_run_id' => $this->betaRun->id, 'product_name' => 'Beta Only Product']);

    $this->viewer = User::factory()->create(['is_active' => true, 'branch_id' => $this->alpha->id]);
    $this->viewer->givePermissionTo(['access backoffice', 'view ordering recommendations']);
    $this->viewer->syncBranchAccess([$this->alpha->id], $this->alpha->id);
    $this->analyst = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->analyst->givePermissionTo(['view ordering recommendations', 'export ordering recommendations']);
});

function orderingWorkbook($response): array
{
    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();

    return $sheets;
}

it('is reachable only with the view permission and appears under Analytics', function () {
    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->givePermissionTo('access backoffice');
    $this->actingAs($outsider)->get(OrderingRecommendationPage::getUrl())->assertForbidden();

    $this->actingAs($this->viewer)->get(OrderingRecommendationPage::getUrl())
        ->assertOk()
        ->assertSee('Ordering Recommendation')
        ->assertSee('Analytics');
    Http::assertNothingSent();
});

it('shows only the branches a limited user may access, even when the branch filter is manipulated', function () {
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)
        ->assertSee('Croissant Dough')
        ->assertDontSee('Beta Only Product')
        ->set('branchIds', [$this->beta->id])
        ->call('applyFilters')
        ->assertSet('applied.branch_ids', [$this->alpha->id])
        ->assertDontSee('Beta Only Product')
        ->call('openDetail', $this->betaItem->id)
        ->assertNotFound();
});

it('uses searchable brand and branch selectors with removable selections', function () {
    $patisserie = Brand::factory()->create(['name' => 'Patisserie Filter']);
    $kitchen = Brand::factory()->create(['name' => 'Kitchen Filter']);
    $this->alpha->update(['brand_id' => $patisserie->id]);
    $this->beta->update(['brand_id' => $kitchen->id]);
    $this->actingAs($this->analyst);

    $page = Livewire::test(OrderingRecommendationPage::class)
        ->assertSeeHtml('id="ordering-brand-search"')
        ->assertSeeHtml('id="ordering-branch-search"')
        ->call('toggleBrandId', $patisserie->id)
        ->assertSet('brandIds', [$patisserie->id])
        ->assertSet('branchIds', []);

    expect($page->viewData('branchOptions')->modelKeys())->toBe([$this->alpha->id]);

    $page->call('toggleBranchId', $this->alpha->id)
        ->assertSet('branchIds', [$this->alpha->id])
        ->assertSee('Branch Alpha')
        ->call('toggleBranchId', $this->alpha->id)
        ->assertSet('branchIds', [])
        ->call('toggleBrandId', $patisserie->id)
        ->assertSet('brandIds', []);
});

it('does not allow the branch selector action to add an inaccessible branch', function () {
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)
        ->call('toggleBranchId', $this->beta->id)
        ->assertSet('branchIds', [$this->alpha->id]);
});

it('keeps every branch separate for an all-branch user with an overview and per-branch ranking', function () {
    $this->actingAs($this->analyst);

    Livewire::test(OrderingRecommendationPage::class)
        ->set('branchIds', [$this->alpha->id, $this->beta->id])
        ->call('applyFilters')
        ->assertSee('Ringkasan per Branch')
        ->assertSee('Lihat Detail Branch')
        ->assertSee('Beta Only Product')
        ->assertSet('activeBranchId', $this->alpha->id)
        ->call('selectBranch', $this->beta->id)
        ->assertSet('activeBranchId', $this->beta->id)
        ->assertSee('Produk Teratas: Branch Beta');
});

it('counts KPIs per product without summing quantities across UOMs and labels blocked rows', function () {
    $this->actingAs($this->viewer);

    $kpis = app(OrderingRecommendationQuery::class)->kpis([$this->alphaRun->id], []);
    $page = Livewire::test(OrderingRecommendationPage::class);
    // Both rows trend up (the blocked one still has a demand trend); quantities are never totalled.
    expect($kpis)->toBe(['products' => 2, 'need_order' => 1, 'stockout' => 1, 'trend_up' => 2, 'high_waste' => 0, 'incomplete' => 1]);
    $page->assertSee('Tidak dapat dihitung')
        ->assertSee('Data perlu diperbarui')
        ->assertSee('Data terakhir diambil dari ESB lebih dari 48 jam yang lalu')
        ->assertSee('Perlu diperhatikan')
        ->assertSee('Lokasi stok untuk branch ini belum dipilih di Ordering Recommendation Settings. Karena itu jumlah yang perlu dipesan belum bisa dihitung.')
        ->assertDontSee('Stale')
        ->assertDontSee('Branch dianalisis')
        ->assertDontSee('UOM grafik')
        ->assertDontSeeHtml('total_quantity');
});

it('shows ranking as an actionable table with its forecast period and order deadline', function () {
    $this->alphaRun->update([
        'forecast_from' => '2026-10-20',
        'forecast_to' => '2026-10-26',
    ]);
    $this->alphaItem->update([
        'available_stock' => 5,
        'predicted_stockout_date' => '2026-10-23',
    ]);
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)
        ->assertSee('10 produk teratas berdasarkan Prioritas Order untuk')
        ->assertSee('20 Oct 2026 sampai 26 Oct 2026')
        ->assertSee('Prediksi Ordering Produk')
        ->assertSeeHtml('fi-pagination-overview')
        ->assertSee('Tren')
        ->assertSee('Waste')
        ->assertSee('Coverage')
        ->assertSeeHtml('data-testid="ordering-recommendation-products-table"')
        ->assertSee('Stok Aman Hingga')
        ->assertSee('Order Sebelum')
        ->assertSee('22 Oct 2026')
        ->assertSee('Batas 23 Oct 2026')
        ->set('rankingType', 'top_demand')
        ->assertSee('10 produk teratas berdasarkan Top Demand untuk');
});

it('labels an empty stock as out of stock and its order as urgent', function () {
    $this->alphaItem->update(['available_stock' => 0]);
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)
        ->assertSee('Stok Habis')
        ->assertSee('Order Segera')
        ->assertDontSee('Sudah tidak aman');
});

it('filters on submit, resets pagination, and explains the formula in the detail', function () {
    $this->alphaItem->update([
        'weekday_baseline' => [
            1 => [
                'value' => 8.5,
                'fallback' => false,
                'samples' => [
                    ['date' => '2026-10-19', 'usage' => 9, 'weight' => 1],
                    ['date' => '2026-10-12', 'usage' => 8, 'weight' => 0.85],
                ],
            ],
        ],
        'daily_breakdown' => [
            ['date' => '2026-10-22', 'forecast' => 8.5, 'cumulative' => 8.5, 'projected_stock' => -2.5],
        ],
    ]);
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)
        ->set('paginators.itemsPage', 2)
        ->set('confidence', 'blocked')
        ->assertSee('Croissant Dough')
        ->call('applyFilters')
        ->assertSet('paginators.itemsPage', 1)
        ->assertDontSee('Croissant Dough')
        ->set('confidence', null)
        ->call('applyFilters')
        ->call('openDetail', $this->alphaItem->id)
        ->assertSet('detailItemId', $this->alphaItem->id)
        ->assertSeeHtml('data-testid="ordering-recommendation-detail-content"')
        ->assertSeeHtml('fi-modal-has-sticky-header')
        ->assertSeeHtml('fi-width-7xl')
        ->assertSeeHtml('max-height: calc(100dvh - 2rem); overflow-y: auto; overscroll-behavior: contain;')
        ->assertSee('Identitas dan Periode')
        ->assertSee('Baseline per Hari')
        ->assertSee('Komponen Perhitungan')
        ->assertSee('Hasil Rekomendasi')
        ->assertSee('Model dan Kualitas Prediksi')
        ->assertSee('Proyeksi Harian')
        ->assertSee('19/10')
        ->assertSee('Gross = forecast + expected waste + buffer')
        ->assertSee('Inbound belum diperhitungkan')
        ->assertSee('000123');
});

it('shows an empty state when the branch has no completed forecast', function () {
    $this->alphaRun->update(['status' => 'failed']);
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)->assertSee('Belum ada snapshot forecast');
});

it('reads the latest completed run and never an older or failed one', function () {
    $newer = OrderingForecastRun::factory()->create(['branch_id' => $this->alpha->id]);
    OrderingForecastItem::factory()->create(['ordering_forecast_run_id' => $newer->id, 'product_name' => 'Newer Run Product']);
    OrderingForecastRun::factory()->failed()->create(['branch_id' => $this->alpha->id]);
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)->assertSee('Newer Run Product')->assertDontSee('Croissant Dough');
});

it('requires the export permission', function () {
    $this->actingAs($this->viewer);

    Livewire::test(OrderingRecommendationPage::class)->call('export')->assertForbidden();
    expect(fn () => app(ExportOrderingRecommendationsXlsxAction::class)->execute($this->viewer, [$this->alpha->id], 7, []))
        ->toThrow(AuthorizationException::class);
});

it('exports four scoped sheets with string codes and sanitized text', function () {
    $this->alphaItem->update(['product_name' => '=cmd()']);
    OrderingDailyMetric::factory()->create(['branch_id' => $this->alpha->id, 'product_code' => '000123', 'metric_date' => $this->alphaRun->history_to]);
    OrderingSyncRun::factory()->failed()->create(['branch_id' => $this->alpha->id, 'period_from' => $this->alphaRun->history_to, 'period_to' => $this->alphaRun->history_to]);
    $limited = User::factory()->create(['is_active' => true]);
    $limited->givePermissionTo('export ordering recommendations');
    $limited->syncBranchAccess([$this->alpha->id], $this->alpha->id);

    $sheets = orderingWorkbook(app(ExportOrderingRecommendationsXlsxAction::class)->execute($limited, [$this->alpha->id, $this->beta->id], 7, []));

    expect(array_keys($sheets))->toBe(['Summary', 'Recommendations', 'Daily History', 'Data Quality'])
        ->and($sheets['Recommendations'][0])->toBe(ExportOrderingRecommendationsXlsxAction::RECOMMENDATION_HEADERS)
        ->and($sheets['Recommendations'])->toHaveCount(3)
        ->and(collect($sheets['Recommendations'])->pluck(5))->toContain("'=cmd()")
        ->and(collect($sheets['Recommendations'])->pluck(4))->toContain('000123')
        ->and(collect($sheets['Recommendations'])->pluck(5))->not->toContain('Beta Only Product')
        ->and(collect($sheets['Recommendations'])->pluck(20))->toContain('Not available')
        ->and($sheets['Daily History'])->toHaveCount(2)
        ->and($sheets['Data Quality'][1][5])->toBe('Failed');
});

it('exports a valid header-only workbook when nothing matches', function () {
    $sheets = orderingWorkbook(app(ExportOrderingRecommendationsXlsxAction::class)->execute($this->analyst, [$this->alpha->id], 7, ['search' => 'no such product']));

    expect($sheets['Recommendations'])->toBe([ExportOrderingRecommendationsXlsxAction::RECOMMENDATION_HEADERS]);
});

it('exports more than one chunk without losing rows and keeps each UOM on its own row', function () {
    OrderingForecastItem::factory()->count(520)->create(['ordering_forecast_run_id' => $this->betaRun->id]);
    OrderingForecastItem::factory()->create(['ordering_forecast_run_id' => $this->betaRun->id, 'product_code' => 'MIX', 'uom' => 'KG']);
    OrderingForecastItem::factory()->create(['ordering_forecast_run_id' => $this->betaRun->id, 'product_code' => 'MIX', 'uom' => 'PCS']);

    $rows = orderingWorkbook(app(ExportOrderingRecommendationsXlsxAction::class)->execute($this->analyst, [$this->beta->id], 7, []))['Recommendations'];

    expect($rows)->toHaveCount(1 + 1 + 520 + 2)
        ->and(collect($rows)->filter(fn (array $row): bool => $row[4] === 'MIX')->pluck(7)->sort()->values()->all())->toBe(['KG', 'PCS']);
});

it('keeps the render query count independent of the number of products (no N+1)', function () {
    $this->actingAs($this->viewer);
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(OrderingRecommendationPage::class);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $count(); // warm-up: permission and config caches load on the first render only
    $small = $count();
    OrderingForecastItem::factory()->count(50)->create(['ordering_forecast_run_id' => $this->alphaRun->id]);
    $large = $count();

    expect($large)->toBe($small)->and($small)->toBeLessThan(40);
});

it('explains data completeness and branch notes in plain sentences', function () {
    expect(OrderingReasonCodes::note('category_unconfirmed'))
        ->toBe('Daftar kategori produk yang dipesan Store belum ditentukan. Karena itu semua produk ikut dihitung dan tingkat keyakinan rekomendasi paling tinggi Low.')
        ->and(OrderingReasonCodes::note('stock_stale'))->toBe('Data stok sudah terlalu lama tidak diperbarui.')
        ->and(OrderingRecommendationQuery::stateText('fresh'))->toBe([
            'label' => 'Data lengkap',
            'description' => 'Data penjualan dan stok dari ESB sudah lengkap dan diperbarui dalam 48 jam terakhir.',
        ]);
});
