<?php

use App\Enums\OrderingConfidence;
use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use App\Jobs\Ordering\GenerateOrderingForecastJob;
use App\Jobs\Ordering\SyncOrderingSourceJob;
use App\Models\Branch;
use App\Models\OrderingDailyMetric;
use App\Models\OrderingForecastRun;
use App\Models\OrderingSyncRun;
use App\Services\Ordering\OrderingDailyUsageSource;
use App\Services\Ordering\OrderingForecastGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00:00'));
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.core.base_url' => 'https://stock-esb.test',
        'esb.tokens.BLSS' => 'secret-blss-token',
        'esb.tokens.BLSK' => 'secret-blsk-token',
        'ordering_recommendation.stock_location_scope' => ['Store'],
    ]);
    Cache::put('esb_core.access_token.BLSS', 'core-blss', 300);
    Cache::put('esb_core.access_token.BLSK', 'core-blsk', 300);
    $this->branch = Branch::factory()->create();
    $this->pair = $this->branch->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'BPL', 'is_active' => true]);
    $this->kitchenPair = $this->branch->esbCodes()->create(['esb_comcode' => 'BLSK', 'esb_branch_code' => 'BPK', 'is_active' => true]);
});

function usageResponse(string $branchCode, array $rows): array
{
    return array_map(fn (array $row): array => ['branchCode' => $branchCode, 'salesDate' => '2026-10-18', 'productName' => 'Croissant Dough', 'unit' => 'PCS', ...$row], $rows);
}

function fakeStock(array $rows, array $categories = ['0001' => 'Barang WIP']): void
{
    Http::fake([
        'https://stock-esb.test/report/stock-movement*' => Http::response(['status' => 'ok', 'result' => ['count' => count($rows), 'data' => $rows]]),
        'https://stock-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => ['count' => 1, 'data' => collect($categories)->map(fn ($category, $code) => ['productCode' => (string) $code, 'categoryName' => $category])->values()->all()]]),
    ]);
}

it('distinguishes complete, empty, retryable, and permanent Daily Usage outcomes without leaking the token', function (int $status, mixed $body, string $expected, bool $retryable) {
    Http::fake(['https://sales-esb.test/*' => Http::response($body, $status)]);

    $result = app(OrderingDailyUsageSource::class)->fetch($this->pair, '2026-10-18', 'stockUnit');

    expect($result->status->value)->toBe($expected)
        ->and($result->retryable)->toBe($retryable)
        ->and((string) $result->error)->not->toContain('secret-blss-token');
})->with([
    'complete' => [200, [['branchCode' => 'BPL', 'salesDate' => '2026-10-18', 'productCode' => '0001', 'productName' => 'Dough', 'totalQty' => '5', 'unit' => 'pcs']], 'completed', false],
    'empty success' => [200, [], 'completed', false],
    'rate limited' => [429, ['message' => 'slow down'], 'failed', true],
    'server error' => [503, ['message' => 'down'], 'failed', true],
    'client error' => [400, ['message' => 'bad'], 'failed', false],
    'invalid json' => [200, 'not json', 'failed', false],
    'other branch' => [200, [['branchCode' => 'XXX', 'productCode' => '0001', 'totalQty' => '5', 'unit' => 'PCS']], 'failed', false],
]);

it('treats a timeout as a retryable failure', function () {
    Http::fake(['https://sales-esb.test/*' => Http::failedConnection('timed out')]);

    $result = app(OrderingDailyUsageSource::class)->fetch($this->pair, '2026-10-18', 'stockUnit');

    expect($result->status)->toBe(OrderingSyncStatus::Failed)->and($result->retryable)->toBeTrue();
});

it('stores Daily Usage per company pair, keeps the leading zero, and is idempotent', function () {
    Http::fake([
        'https://sales-esb.test/*' => function ($request) {
            return Http::response($request['branchCode'] === 'BPL'
                ? usageResponse('BPL', [['productCode' => '0001', 'totalQty' => '10']])
                : usageResponse('BPK', [['productCode' => '0001', 'totalQty' => '3']]));
        },
    ]);

    SyncOrderingSourceJob::dispatchSync('daily_usage', $this->pair->id, '2026-10-18');
    SyncOrderingSourceJob::dispatchSync('daily_usage', $this->kitchenPair->id, '2026-10-18');
    SyncOrderingSourceJob::dispatchSync('daily_usage', $this->pair->id, '2026-10-18', true);

    $metrics = OrderingDailyMetric::query()->orderBy('company_code')->get();
    expect($metrics)->toHaveCount(2)
        ->and($metrics->pluck('company_code')->all())->toBe(['BLSK', 'BLSS'])
        ->and($metrics->pluck('product_code')->unique()->all())->toBe(['0001'])
        ->and((float) $metrics->firstWhere('company_code', 'BLSS')->sales_driven_usage)->toBe(10.0)
        ->and((float) $metrics->firstWhere('company_code', 'BLSK')->sales_driven_usage)->toBe(3.0)
        ->and(OrderingSyncRun::query()->where('source', 'daily_usage')->count())->toBe(2);
});

it('keeps waste and POS Sales apart from demand and takes the latest balance per in-scope location', function () {
    $row = ['branchCode' => 'BPL', 'productCode' => '0001', 'productName' => 'Croissant Dough', 'UOM' => 'PCS', 'qtyIn' => 0];
    fakeStock([
        $row + ['transactionType' => 'POS Sales', 'qtyOut' => 8, 'qtyBalance' => 12, 'location' => 'Store', 'documentDate' => '2026-10-18', 'createdDate' => '2026-10-18 21:00:00'],
        $row + ['transactionType' => 'POS Sales', 'qtyOut' => 2, 'qtyBalance' => 20, 'location' => 'Store', 'documentDate' => '2026-10-18', 'createdDate' => '2026-10-18 09:00:00'],
        $row + ['transactionType' => 'Waste', 'qtyOut' => 1.5, 'qtyBalance' => 10.5, 'location' => 'Store', 'documentDate' => '2026-10-18', 'createdDate' => '2026-10-18 22:00:00'],
        $row + ['transactionType' => 'Goods Receipt', 'qtyIn' => 50, 'qtyOut' => 0, 'qtyBalance' => 50, 'location' => 'Gudang', 'documentDate' => '2026-10-18', 'createdDate' => '2026-10-18 23:00:00'],
    ]);
    OrderingDailyMetric::factory()->create(['branch_id' => $this->branch->id, 'metric_date' => '2026-10-18', 'product_code' => '0001', 'sales_driven_usage' => 9]);

    SyncOrderingSourceJob::dispatchSync('stock_movement', $this->pair->id, '2026-10-18');

    $metric = OrderingDailyMetric::query()->sole();
    expect((float) $metric->sales_driven_usage)->toBe(9.0)
        ->and((float) $metric->pos_sales_usage)->toBe(10.0)
        ->and((float) $metric->waste_quantity)->toBe(1.5)
        ->and((float) $metric->ending_stock)->toBe(10.5)
        ->and($metric->goods_in_quantity)->toBeNull()
        ->and($metric->category_name)->toBe('Barang WIP');
});

it('leaves available stock unavailable while the Store stock location is not confirmed', function () {
    config()->set('ordering_recommendation.stock_location_scope', null);
    fakeStock([['branchCode' => 'BPL', 'productCode' => '0001', 'UOM' => 'PCS', 'transactionType' => 'POS Sales', 'qtyOut' => 1, 'qtyBalance' => 5, 'location' => 'Store', 'documentDate' => '2026-10-18']]);

    SyncOrderingSourceJob::dispatchSync('stock_movement', $this->pair->id, '2026-10-18');

    expect(OrderingDailyMetric::query()->sole()->ending_stock)->toBeNull();
});

it('records a permanent failure without retrying and keeps earlier good metrics', function () {
    $good = OrderingDailyMetric::factory()->create(['branch_id' => $this->branch->id, 'metric_date' => '2026-10-18', 'product_code' => '0001', 'sales_driven_usage' => 7]);
    Http::fake(['https://sales-esb.test/*' => Http::response(['message' => 'bad request'], 400)]);

    SyncOrderingSourceJob::dispatchSync('daily_usage', $this->pair->id, '2026-10-18');

    $run = OrderingSyncRun::query()->sole();
    expect($run->status)->toBe(OrderingSyncStatus::Failed)
        ->and($run->error_summary)->toBe('ESB Daily Usage HTTP 400.')
        ->and((float) $good->refresh()->sales_driven_usage)->toBe(7.0);
});

it('rethrows a retryable failure so the queue retries it and marks the run failed after the last attempt', function () {
    Http::fake(['https://sales-esb.test/*' => Http::response(['message' => 'down'], 503)]);
    $job = new SyncOrderingSourceJob('daily_usage', $this->pair->id, '2026-10-18');

    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('ESB Daily Usage HTTP 503.'));

    expect(OrderingSyncRun::query()->sole())->status->toBe(OrderingSyncStatus::Failed)->attempts->toBe(1)
        ->and(OrderingDailyMetric::query()->count())->toBe(0);
});

it('skips a completed unit on rerun (resume)', function () {
    Http::fake(['https://sales-esb.test/*' => Http::response(usageResponse('BPL', [['productCode' => '0001', 'totalQty' => '4']]))]);
    SyncOrderingSourceJob::dispatchSync('daily_usage', $this->pair->id, '2026-10-18');
    SyncOrderingSourceJob::dispatchSync('daily_usage', $this->pair->id, '2026-10-18');

    Http::assertSentCount(1);
    expect(OrderingSyncRun::query()->sole()->attempts)->toBe(1);
});

/** Completed usage + stock runs and metrics for 56 days, except the dates in $failedDays. */
function seedOrderingHistory(Branch $branch, string $company, string $esbBranch, float $usage, array $failedDays = [], string $uom = 'PCS', ?float $stock = 30): void
{
    $cutoff = CarbonImmutable::parse('2026-10-18');
    for ($i = 0; $i < 56; $i++) {
        $date = $cutoff->subDays($i)->toDateString();
        foreach (['daily_usage', 'stock_movement'] as $source) {
            OrderingSyncRun::factory()->create([
                'idempotency_key' => "{$source}:{$branch->id}:{$company}:{$esbBranch}:{$date}", 'source' => $source, 'branch_id' => $branch->id,
                'company_code' => $company, 'esb_branch_code' => $esbBranch, 'period_from' => $date, 'period_to' => $date,
                'status' => $source === 'daily_usage' && in_array($date, $failedDays, true) ? OrderingSyncStatus::Failed : OrderingSyncStatus::Completed,
                'finished_at' => '2026-10-19 04:10:00',
            ]);
        }
        if (! in_array($date, $failedDays, true)) {
            OrderingDailyMetric::factory()->create([
                'branch_id' => $branch->id, 'company_code' => $company, 'esb_branch_code' => $esbBranch, 'metric_date' => $date,
                'product_code' => '0001', 'uom' => $uom, 'sales_driven_usage' => $usage, 'waste_quantity' => 0,
                'ending_stock' => $i === 0 ? $stock : null, 'stock_observed_at' => $i === 0 ? '2026-10-18 21:00:00' : null,
            ]);
        }
    }
}

it('generates one recommendation per company pair, excludes failed days instead of counting zero, and is idempotent', function () {
    seedOrderingHistory($this->branch, 'BLSS', 'BPL', 10, ['2026-10-10', '2026-10-11']);
    seedOrderingHistory($this->branch, 'BLSK', 'BPK', 4);

    $run = app(OrderingForecastGenerator::class)->generate($this->branch, '2026-10-18', 7);
    $again = app(OrderingForecastGenerator::class)->generate($this->branch, '2026-10-18', 7);

    $items = $run->items()->orderBy('company_code')->get();
    $store = $items->firstWhere('company_code', 'BLSS');
    expect($run->status)->toBe(OrderingSyncStatus::Completed)
        ->and($again->id)->toBe($run->id)
        ->and(OrderingForecastRun::query()->count())->toBe(1)
        ->and($items)->toHaveCount(2)
        ->and($store->valid_days)->toBe(54)
        ->and((float) $store->average_daily_demand)->toBe(10.0)
        ->and((float) $store->forecast_demand)->toBe(70.0)
        ->and($store->reason_codes)->toContain('usage_partial')
        ->and($store->reason_codes)->toContain('category_unconfirmed')
        ->and((float) $items->firstWhere('company_code', 'BLSK')->forecast_demand)->toBe(28.0)
        ->and(collect($run->completeness['pairs'])->firstWhere('company_code', 'BLSS')['usage_missing_days'])->toBe(2);
});

it('blocks a product whose stock exists only in another UOM and keeps old runs immutable', function () {
    seedOrderingHistory($this->branch, 'BLSS', 'BPL', 10, stock: null);
    OrderingDailyMetric::factory()->create(['branch_id' => $this->branch->id, 'metric_date' => '2026-10-18', 'product_code' => '0001', 'uom' => 'KG', 'sales_driven_usage' => 0, 'ending_stock' => 2]);

    $first = app(OrderingForecastGenerator::class)->generate($this->branch, '2026-10-18', 7);
    $blocked = $first->items()->where('uom', 'PCS')->sole();
    expect($blocked->confidence)->toBe(OrderingConfidence::Blocked)
        ->and($blocked->reason_codes)->toContain('uom_conflict')
        ->and($blocked->final_recommendation)->toBeNull();

    config()->set('ordering_recommendation.safety_buffer_percent', 0.2);
    $second = app(OrderingForecastGenerator::class)->generate($this->branch, '2026-10-18', 7);

    expect($second->id)->not->toBe($first->id)
        ->and($first->refresh()->items()->where('uom', 'PCS')->sole()->buffer_percent)->toEqual(0.1)
        ->and($second->configuration_snapshot['safety_buffer_percent'])->toBe(0.2);
});

it('previews a backfill without writing and rejects an invalid range or branch', function () {
    $this->artisan('ordering:backfill', ['--from' => '2026-10-17', '--to' => '2026-10-18'])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();
    expect(OrderingSyncRun::query()->count())->toBe(0);

    $this->artisan('ordering:backfill', ['--from' => '2026-10-18', '--to' => '2026-10-17'])->assertFailed();
    $this->artisan('ordering:backfill', ['--from' => '2026-10-18', '--to' => '2026-10-19'])->assertFailed();
    $this->artisan('ordering:backfill', ['--from' => '2026-10-17', '--to' => '2026-10-18', '--branch' => [999999]])->assertFailed();
    $this->artisan('ordering:backfill', ['--from' => '2026-10-17', '--to' => '2026-10-18', '--source' => ['sales']])->assertFailed();
});

it('dispatches only units that are not completed, chained before the forecast', function () {
    Bus::fake();
    OrderingSyncRun::factory()->create(['idempotency_key' => OrderingSyncRun::idempotencyKey(OrderingSource::DailyUsage, $this->pair, '2026-10-18'), 'branch_id' => $this->branch->id]);

    $this->artisan('ordering:backfill', ['--from' => '2026-10-18', '--to' => '2026-10-18', '--execute' => true, '--force' => true, '--forecast' => true])
        ->expectsOutputToContain('Dispatched 3 sync job(s)')
        ->assertSuccessful();

    Bus::assertChained([SyncOrderingSourceJob::class, SyncOrderingSourceJob::class, SyncOrderingSourceJob::class, GenerateOrderingForecastJob::class, GenerateOrderingForecastJob::class]);
});

it('dispatches the previous day from the scheduled sync command', function () {
    Bus::fake();

    $this->artisan('ordering:sync')->expectsOutputToContain('cutoff 2026-10-18')->assertSuccessful();

    Bus::assertChained([
        fn (SyncOrderingSourceJob $job): bool => $job->date === '2026-10-18',
        SyncOrderingSourceJob::class, SyncOrderingSourceJob::class, SyncOrderingSourceJob::class,
        GenerateOrderingForecastJob::class, GenerateOrderingForecastJob::class,
    ]);
});
