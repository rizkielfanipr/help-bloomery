<?php

use App\Enums\OrderingStockLocationMode;
use App\Filament\Helpdesk\Pages\OrderingRecommendationSettingsPage;
use App\Jobs\Ordering\GenerateOrderingForecastJob;
use App\Jobs\Ordering\SyncOrderingSourceJob;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 10:00:00'));
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    config()->set([
        'esb.core.base_url' => 'https://stock-esb.test',
        'ordering_recommendation.history_weeks' => 8,
        'ordering_recommendation.horizons' => [7, 14],
        'ordering_recommendation.flag_unit' => 'stockUnit',
    ]);
    Cache::put('esb_core.access_token.BLSS', 'core-blss-token', 300);

    $this->branch = Branch::factory()->create(['name' => 'Bloomery Istana Buah']);
    $this->pair = $this->branch->esbCodes()->create([
        'esb_comcode' => 'BLSS',
        'esb_branch_code' => 'BIB',
        'is_active' => true,
    ]);
    $this->manager = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->manager->givePermissionTo(['access backoffice', 'manage ordering recommendation settings']);
});

it('restricts the settings page to its dedicated permission', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['access backoffice', 'view ordering recommendations']);

    $this->actingAs($viewer)
        ->get(OrderingRecommendationSettingsPage::getUrl())
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->get(OrderingRecommendationSettingsPage::getUrl())
        ->assertOk()
        ->assertSee('Lokasi Stok per Branch')
        ->assertSee('Bloomery Istana Buah');
});

it('discovers ESB locations and stores selected locations before scheduling a safe resync', function () {
    Bus::fake();
    Http::fake([
        'https://stock-esb.test/report/stock-movement*' => Http::response([
            'status' => 'ok',
            'result' => [
                'count' => 3,
                'data' => [
                    ['branchCode' => 'BIB', 'locationID' => 17, 'location' => 'Store'],
                    ['branchCode' => 'BIB', 'locationID' => 29, 'location' => 'Gudang'],
                    ['branchCode' => 'BIB', 'locationId' => 17, 'locationName' => 'Store'],
                ],
            ],
        ]),
    ]);
    $storeFingerprint = hash('sha256', '17|store');
    $this->actingAs($this->manager);

    Livewire::test(OrderingRecommendationSettingsPage::class)
        ->call('configure', $this->pair->id)
        ->call('selectMode', OrderingStockLocationMode::Selected->value)
        ->assertSet('mode', OrderingStockLocationMode::Selected->value)
        ->assertSee('Ambil Lokasi dari ESB')
        ->call('discoverLocations')
        ->assertSet('availableLocations.0.name', 'Gudang')
        ->assertSet('availableLocations.1.name', 'Store')
        ->set('selectedLocationFingerprints', [$storeFingerprint])
        ->call('save')
        ->assertHasNoErrors();

    $pair = $this->pair->refresh();
    expect($pair->ordering_stock_location_mode)->toBe(OrderingStockLocationMode::Selected)
        ->and($pair->ordering_stock_locations)->toBe([['key' => '17', 'name' => 'Store']])
        ->and($pair->ordering_stock_locations_updated_at)->not->toBeNull();

    $expectedChain = [];
    for ($day = 0; $day < 56; $day++) {
        $expectedChain[] = fn (SyncOrderingSourceJob $job): bool => $job->source === 'daily_usage' && $job->force === false;
        $expectedChain[] = fn (SyncOrderingSourceJob $job): bool => $job->source === 'stock_movement' && $job->force === true;
    }
    $expectedChain[] = GenerateOrderingForecastJob::class;
    $expectedChain[] = GenerateOrderingForecastJob::class;
    Bus::assertChained($expectedChain);
    Http::assertSentCount(1);
});

it('can explicitly include all locations without storing a location list', function () {
    Bus::fake();
    $this->actingAs($this->manager);

    Livewire::test(OrderingRecommendationSettingsPage::class)
        ->call('configure', $this->pair->id)
        ->set('mode', OrderingStockLocationMode::All->value)
        ->call('save')
        ->assertHasNoErrors();

    $pair = $this->pair->refresh();
    expect($pair->ordering_stock_location_mode)->toBe(OrderingStockLocationMode::All)
        ->and($pair->ordering_stock_locations)->toBeNull()
        ->and($pair->orderingStockLocationScope())->toBe('all');
});

it('does not overwrite saved locations when ESB discovery fails', function () {
    $this->pair->update([
        'ordering_stock_location_mode' => OrderingStockLocationMode::Selected,
        'ordering_stock_locations' => [['key' => '17', 'name' => 'Store']],
    ]);
    Http::fake(['https://stock-esb.test/*' => Http::response(['message' => 'unavailable'], 503)]);
    $this->actingAs($this->manager);

    Livewire::test(OrderingRecommendationSettingsPage::class)
        ->call('configure', $this->pair->id)
        ->call('discoverLocations')
        ->assertSet('selectedLocationFingerprints', [hash('sha256', '17|store')]);

    expect($this->pair->refresh()->ordering_stock_locations)->toBe([['key' => '17', 'name' => 'Store']]);
});
