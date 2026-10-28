<?php

use App\Actions\Kitchen\StoreProductionOrderDetailAction;
use App\Actions\Kitchen\StoreProductionOrderHeadersAction;
use App\Enums\EsbProductionOrderSyncMode;
use App\Enums\EsbProductionOrderSyncStatus;
use App\Exceptions\Kitchen\EsbProductionOrderException;
use App\Jobs\Kitchen\SyncEsbProductionOrderDetailJob;
use App\Jobs\Kitchen\SyncEsbProductionOrdersJob;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderDetail;
use App\Models\EsbProductionOrderSyncRun;
use App\Services\Kitchen\EsbProductionOrderNormalizer;
use App\Services\Kitchen\ProductionOrderSyncPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Cache::flush();
    $this->travelTo('2026-10-14 10:00:00');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('kitchen.production_orders.global_credential_companies', []);
    config()->set('esb.core.companies.BLSS', ['username' => 'user', 'password' => 'secret']);
    config()->set('esb.core.companies.BLO6', ['username' => 'user-6', 'password' => 'secret-6']);
    Cache::put('esb_core.access_token.BLSS', 'token-blss');
    Cache::put('esb_core.access_token.BLO6', 'token-blo6');
});

function kitchenEsbMapping(array $overrides = [], ?Branch $branch = null): BranchEsbCode
{
    return ($branch ?? Branch::factory()->kitchen()->create(['is_active' => true]))->esbCodes()->create([
        'esb_comcode' => 'BLSS',
        'esb_branch_code' => 'KTJ',
        'esb_branch_id' => 501,
        'is_active' => true,
        ...$overrides,
    ])->load('branch');
}

function queuedProductionOrderRun(BranchEsbCode $mapping, string $from = '2026-10-07', string $to = '2026-10-28', EsbProductionOrderSyncMode $mode = EsbProductionOrderSyncMode::Incremental): EsbProductionOrderSyncRun
{
    $key = EsbProductionOrderSyncRun::windowKey($mapping, $mode, $from, $to);

    return EsbProductionOrderSyncRun::query()->create([
        'idempotency_key' => $key, 'active_key' => $key, 'branch_esb_code_id' => $mapping->id, 'branch_id' => $mapping->branch_id,
        'company_code' => $mapping->esb_comcode, 'esb_branch_id' => $mapping->esb_branch_id, 'mode' => $mode,
        'date_from' => $from, 'date_to' => $to, 'status' => EsbProductionOrderSyncStatus::Queued,
    ]);
}

/**
 * Fakes the list endpoint page by page and the detail endpoint per order number.
 *
 * @param  list<array<string, mixed>>  $pages  list responses in page order
 * @param  array<string, array<string, mixed>|int>  $details  order number => detail response or HTTP status
 */
function fakeProductionOrderEsb(array $pages, array $details = []): void
{
    Http::fake(function (Request $request) use ($pages, $details) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if (str_ends_with($path, '/production/production-order')) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response($pages[(int) $query['page'] - 1] ?? esbProductionOrderListResponse([]));
        }

        $number = rawurldecode(basename($path));
        $detail = $details[$number] ?? esbProductionOrderDetailResponse(['productionOrderNum' => $number]);

        return is_int($detail) ? Http::response(['status' => 'fail', 'errors' => [['message' => 'gagal']]], $detail) : Http::response($detail);
    });
}

function storeHeaders(EsbProductionOrderSyncRun $run, BranchEsbCode $mapping, array $rows): array
{
    $normalizer = app(EsbProductionOrderNormalizer::class);

    return app(StoreProductionOrderHeadersAction::class)->execute($run, $mapping, array_map(fn (array $row): array => $normalizer->header($row), $rows));
}

// ── Schema and snapshot ─────────────────────────────────────────────────────

it('keeps order numbers unique per company but allows the same number in another company', function () {
    $branch = Branch::factory()->kitchen()->create();
    EsbProductionOrder::factory()->create(['branch_id' => $branch->id, 'company_code' => 'BLSS', 'production_order_num' => 'PO-1']);
    EsbProductionOrder::factory()->create(['branch_id' => $branch->id, 'company_code' => 'BLO6', 'production_order_num' => 'PO-1']);

    expect(fn () => EsbProductionOrder::factory()->create(['branch_id' => $branch->id, 'company_code' => 'BLSS', 'production_order_num' => 'PO-1']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('upserts the same header idempotently and updates a status change in place', function () {
    $mapping = kitchenEsbMapping();
    $run = queuedProductionOrderRun($mapping);

    expect(storeHeaders($run, $mapping, [esbProductionOrderRow()]))->toHaveCount(1);
    $order = EsbProductionOrder::query()->sole();
    app(StoreProductionOrderDetailAction::class)->execute($order, app(EsbProductionOrderNormalizer::class)->order(esbProductionOrderDetailResponse()['result']));

    expect(storeHeaders($run, $mapping, [esbProductionOrderRow()]))->toBe([])
        ->and(storeHeaders($run, $mapping, [esbProductionOrderRow(['statusID' => 3, 'statusName' => 'Finished'])]))->toBe([$order->id])
        ->and(EsbProductionOrder::query()->count())->toBe(1)
        ->and($order->refresh()->status_name)->toBe('Finished')
        ->and($order->company_code)->toBe('BLSS')
        ->and($order->branch_id)->toBe($mapping->branch_id)
        ->and($run->refresh()->only(['headers_received', 'headers_created', 'headers_updated', 'headers_unchanged']))
        ->toBe(['headers_received' => 3, 'headers_created' => 1, 'headers_updated' => 1, 'headers_unchanged' => 1]);
});

it('never moves an order owned by another Branch mapping and counts it as a conflict', function () {
    $first = kitchenEsbMapping();
    $second = kitchenEsbMapping(['esb_branch_code' => 'KC2']);
    storeHeaders(queuedProductionOrderRun($first), $first, [esbProductionOrderRow()]);
    $run = queuedProductionOrderRun($second);

    expect(storeHeaders($run, $second, [esbProductionOrderRow(['statusName' => 'Changed'])]))->toBe([])
        ->and(EsbProductionOrder::query()->sole()->branch_id)->toBe($first->branch_id)
        ->and($run->refresh()->conflicts)->toBe(1);
});

it('stores details idempotently with exact decimal quantities and materials', function () {
    $order = EsbProductionOrder::factory()->withoutDetails()->create(['production_order_num' => 'PO-0001']);
    $payload = app(EsbProductionOrderNormalizer::class)->order(esbProductionOrderDetailResponse([], [
        esbProductionOrderDetailRow(['productionOrderQty' => '1234567.1234']),
        esbProductionOrderDetailRow(['ID' => 9002, 'productionOrderQty' => 0.3, 'productionOrderMaterials' => []]),
    ])['result']);

    app(StoreProductionOrderDetailAction::class)->execute($order, $payload);
    app(StoreProductionOrderDetailAction::class)->execute($order, $payload);

    $details = $order->details()->get();
    expect($details)->toHaveCount(2)
        ->and($details->pluck('quantity')->all())->toBe(['1234567.1234', '0.3000'])
        ->and($details->first()->materials[0])->toBe(['product_code' => 'RAW-0001', 'product_name' => 'Tepung Contoh', 'quantity' => '7.25', 'uom_name' => 'KG'])
        ->and($order->refresh()->details_synced_at)->not->toBeNull()
        ->and($order->authorized_by_name)->toBe('Supervisor Contoh');
});

it('keeps snapshot labels readable after the mapping is deleted', function () {
    $mapping = kitchenEsbMapping();
    $order = EsbProductionOrder::factory()->forMapping($mapping)->create(['status_name' => 'Authorized', 'esb_branch_name' => 'Kitchen Contoh']);

    $mapping->delete();

    expect($order->refresh()->branch_esb_code_id)->toBeNull()
        ->and($order->status_name)->toBe('Authorized')
        ->and($order->esb_branch_name)->toBe('Kitchen Contoh')
        ->and($order->branch_id)->toBe($mapping->branch_id);
});

// ── Planner ─────────────────────────────────────────────────────────────────

it('syncs every Kitchen mapping from Master Branch, each with its own company context (K20)', function () {
    expect(config('kitchen.production_orders'))->not->toHaveKeys(['company_codes', 'canonical_mappings'])
        ->and(config('esb.core.companies'))->toHaveKey('BLHJ');
    $jateng = kitchenEsbMapping(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'KTJ', 'esb_branch_id' => 4]);
    $surabaya = kitchenEsbMapping(['esb_comcode' => 'BLO13', 'esb_branch_code' => 'HOF', 'esb_branch_id' => 11]);
    $jakarta = kitchenEsbMapping(['esb_comcode' => 'BLHJ', 'esb_branch_code' => 'TCHL', 'esb_branch_id' => 21]);
    $planner = app(ProductionOrderSyncPlanner::class);

    $plan = $planner->mappings();
    $units = $planner->units($plan['eligible'], EsbProductionOrderSyncMode::Incremental, ...$planner->incrementalRange());

    expect($plan['eligible']->modelKeys())->toBe([$jateng->id, $surabaya->id, $jakarta->id])
        ->and($plan['skipped'])->toBe([])
        ->and(collect($units)->map(fn (array $unit): string => $unit['mapping']->esb_comcode.'/'.$unit['mapping']->esb_branch_id)->all())
        ->toBe(['BLSS/4', 'BLO13/11', 'BLHJ/21']);
});

it('picks up a new Kitchen Branch added in Master Branch without any configuration change', function () {
    $planner = app(ProductionOrderSyncPlanner::class);
    kitchenEsbMapping();
    expect($planner->mappings()['eligible'])->toHaveCount(1);

    $newKitchen = Branch::factory()->kitchen()->create(['name' => 'Kitchen Bandung', 'is_active' => true]);
    $mapping = $newKitchen->esbCodes()->create(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'BDG', 'esb_branch_id' => 31, 'is_active' => true]);

    expect($planner->mappings()['eligible']->modelKeys())->toContain($mapping->id);
});

it('explains a Kitchen mapping whose company is not registered in the ESB configuration', function () {
    $mapping = kitchenEsbMapping(['esb_comcode' => 'ZZNEW', 'esb_branch_code' => 'NEW', 'esb_branch_id' => 9]);

    $plan = app(ProductionOrderSyncPlanner::class)->mappings();

    expect($plan['eligible'])->toBeEmpty()
        ->and($plan['skipped'][0]['mapping']->id)->toBe($mapping->id)
        ->and($plan['skipped'][0]['reason'])->toBe('Company Code ESB belum terdaftar di konfigurasi.');
});

it('never plans a non-Kitchen Branch, even when it is requested explicitly', function () {
    $store = Branch::factory()->create(['is_active' => true]);
    $mapping = $store->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'KTJ', 'esb_branch_id' => 4, 'is_active' => true]);
    $planner = app(ProductionOrderSyncPlanner::class);

    expect($planner->mappings()['eligible'])->toBeEmpty()
        ->and($planner->mappings([$store->id])['eligible'])->toBeEmpty()
        ->and($planner->mappings([], [$mapping->id])['eligible'])->toBeEmpty()
        ->and($planner->skipReason($mapping->load('branch')))->toBe('Branch bukan Brand Kitchen.');
});

it('stops a queued run whose Branch left the Kitchen Brand without calling ESB', function () {
    Http::fake();
    $mapping = kitchenEsbMapping();
    $run = queuedProductionOrderRun($mapping);
    $mapping->branch->update(['brand_id' => null]);

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    expect($run->refresh()->status)->toBe(EsbProductionOrderSyncStatus::Failed)
        ->and($run->error_class)->toBe('configuration')
        ->and($run->error_summary)->toBe('Branch bukan Brand Kitchen.');
    Http::assertNothingSent();
});

it('plans only active, complete mappings of active Kitchen Branches and explains skips', function () {
    $eligible = kitchenEsbMapping();
    $other = kitchenEsbMapping(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'K6', 'esb_branch_id' => 601], $eligible->branch);
    $inactive = kitchenEsbMapping(['is_active' => false, 'esb_branch_code' => 'OFF']);
    $missingId = kitchenEsbMapping(['esb_branch_id' => null, 'esb_branch_code' => 'NOID']);
    $unknownCompany = kitchenEsbMapping(['esb_comcode' => 'ZZZZ', 'esb_branch_code' => 'UNK']);
    $inactiveBranch = kitchenEsbMapping(['esb_branch_code' => 'IB'], Branch::factory()->kitchen()->create(['is_active' => false]));
    $nonKitchen = Branch::factory()->create(['is_active' => true])->esbCodes()->create(['esb_comcode' => 'BLSS', 'esb_branch_code' => 'NK', 'esb_branch_id' => 700, 'is_active' => true]);

    $plan = app(ProductionOrderSyncPlanner::class)->mappings();

    expect($plan['eligible']->modelKeys())->toBe([$eligible->id, $other->id])
        ->and(collect($plan['skipped'])->pluck('mapping.id'))->not->toContain($nonKitchen->id)
        ->and(collect($plan['skipped'])->mapWithKeys(fn (array $row): array => [$row['mapping']->id => $row['reason']])->all())->toBe([
            $inactive->id => 'Mapping ESB tidak aktif.',
            $missingId->id => 'Branch ID ESB belum terisi.',
            $unknownCompany->id => 'Company Code ESB belum terdaftar di konfigurasi.',
            $inactiveBranch->id => 'Branch tidak aktif.',
        ]);
});

it('splits long ranges into bounded windows per mapping', function () {
    $mapping = kitchenEsbMapping();

    $units = app(ProductionOrderSyncPlanner::class)->units([$mapping], EsbProductionOrderSyncMode::Backfill, CarbonImmutable::parse('2026-07-16'), CarbonImmutable::parse('2026-10-28'));

    expect(array_map(fn (array $unit): string => "{$unit['date_from']}→{$unit['date_to']}", $units))->toBe([
        '2026-07-16→2026-08-15', '2026-08-16→2026-09-15', '2026-09-16→2026-10-16', '2026-10-17→2026-10-28',
    ]);
});

it('refuses a second active run for the same window and reopens a stuck one', function () {
    Queue::fake();
    $mapping = kitchenEsbMapping();
    $planner = app(ProductionOrderSyncPlanner::class);
    $units = $planner->units([$mapping], EsbProductionOrderSyncMode::Incremental, ...$planner->incrementalRange());

    expect($planner->dispatch($units, EsbProductionOrderSyncMode::Incremental))->toBe(['dispatched' => 1, 'already_active' => 0])
        ->and($planner->dispatch($units, EsbProductionOrderSyncMode::Incremental))->toBe(['dispatched' => 0, 'already_active' => 1]);
    Queue::assertPushed(SyncEsbProductionOrdersJob::class, 1);

    $this->travel(3)->hours();

    expect($planner->dispatch($units, EsbProductionOrderSyncMode::Incremental))->toBe(['dispatched' => 1, 'already_active' => 0])
        ->and(EsbProductionOrderSyncRun::query()->oldest('id')->first()->status)->toBe(EsbProductionOrderSyncStatus::Failed)
        ->and(EsbProductionOrderSyncRun::query()->whereNotNull('active_key')->count())->toBe(1);
});

// ── Jobs ────────────────────────────────────────────────────────────────────

it('syncs every page, hydrates details, and closes the run as succeeded', function () {
    $mapping = kitchenEsbMapping();
    fakeProductionOrderEsb([
        esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderNum' => 'PO-1'])], 1, 2, 'next'),
        esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderNum' => 'PO-2', 'productionOrderDate' => '2026-10-20'])], 2, 2),
    ]);
    config()->set('kitchen.production_orders.page_limit', 1);
    $run = queuedProductionOrderRun($mapping);

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    $run->refresh();
    expect($run->status)->toBe(EsbProductionOrderSyncStatus::Succeeded)
        ->and($run->active_key)->toBeNull()
        ->and($run->only(['pages_fetched', 'last_page', 'headers_created', 'details_expected', 'details_succeeded', 'details_failed']))
        ->toBe(['pages_fetched' => 2, 'last_page' => 2, 'headers_created' => 2, 'details_expected' => 2, 'details_succeeded' => 2, 'details_failed' => 0])
        ->and($run->list_completed_at)->not->toBeNull()
        ->and(EsbProductionOrderDetail::query()->count())->toBe(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'branchID=501') && $request->hasHeader('Authorization', 'Bearer token-blss'));
});

it('re-running the same payload creates no duplicates and reads no unchanged detail', function () {
    $mapping = kitchenEsbMapping();
    fakeProductionOrderEsb([esbProductionOrderListResponse([esbProductionOrderRow()])]);

    SyncEsbProductionOrdersJob::dispatchSync(queuedProductionOrderRun($mapping)->id);
    $this->travel(1)->hours();
    $second = queuedProductionOrderRun($mapping);
    SyncEsbProductionOrdersJob::dispatchSync($second->id);

    expect(EsbProductionOrder::query()->count())->toBe(1)
        ->and(EsbProductionOrderDetail::query()->count())->toBe(1)
        ->and($second->refresh()->only(['headers_unchanged', 'details_expected', 'status']))->toBe(['headers_unchanged' => 1, 'details_expected' => 0, 'status' => EsbProductionOrderSyncStatus::Succeeded]);
    Http::assertSentCount(3);
});

it('isolates companies and Branches per mapping', function () {
    $blss = kitchenEsbMapping();
    $blo6 = kitchenEsbMapping(['esb_comcode' => 'BLO6', 'esb_branch_code' => 'K6', 'esb_branch_id' => 601]);
    Http::fake(function (Request $request) {
        $isBlo6 = $request->hasHeader('Authorization', 'Bearer token-blo6');
        $branchId = $isBlo6 ? 601 : 501;
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return str_ends_with($path, '/production/production-order')
            ? Http::response(esbProductionOrderListResponse([esbProductionOrderRow(['branchID' => $branchId])]))
            : Http::response(esbProductionOrderDetailResponse(['branchID' => $branchId]));
    });

    SyncEsbProductionOrdersJob::dispatchSync(queuedProductionOrderRun($blss)->id);
    SyncEsbProductionOrdersJob::dispatchSync(queuedProductionOrderRun($blo6)->id);

    expect(EsbProductionOrder::query()->orderBy('company_code')->get(['company_code', 'production_order_num', 'branch_id', 'esb_branch_id'])->toArray())->toBe([
        ['company_code' => 'BLO6', 'production_order_num' => 'PO-0001', 'branch_id' => $blo6->branch_id, 'esb_branch_id' => 601],
        ['company_code' => 'BLSS', 'production_order_num' => 'PO-0001', 'branch_id' => $blss->branch_id, 'esb_branch_id' => 501],
    ]);
});

it('marks the run partial when one detail fails and keeps every valid header', function () {
    $mapping = kitchenEsbMapping();
    fakeProductionOrderEsb(
        [esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderNum' => 'PO-1']), esbProductionOrderRow(['productionOrderNum' => 'PO-2'])])],
        ['PO-2' => 400],
    );
    $run = queuedProductionOrderRun($mapping);

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    expect($run->refresh()->status)->toBe(EsbProductionOrderSyncStatus::Partial)
        ->and($run->only(['details_succeeded', 'details_failed']))->toBe(['details_succeeded' => 1, 'details_failed' => 1])
        ->and(EsbProductionOrder::query()->pluck('details_synced_at', 'production_order_num')->map(fn ($value): bool => $value !== null)->all())
        ->toBe(['PO-1' => true, 'PO-2' => false]);
});

it('fails the run without retry on validation errors and keeps earlier snapshots', function () {
    $mapping = kitchenEsbMapping();
    $existing = EsbProductionOrder::factory()->forMapping($mapping)->create();
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'fail', 'errors' => [['message' => 'branchID tidak valid']]], 400)]);
    $run = queuedProductionOrderRun($mapping);

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    expect($run->refresh()->status)->toBe(EsbProductionOrderSyncStatus::Failed)
        ->and($run->error_class)->toBe('validation')
        ->and($run->error_code)->toBe('400')
        ->and($run->error_summary)->toContain('branchID tidak valid')
        ->and($run->active_key)->toBeNull()
        ->and($existing->fresh())->not->toBeNull();
});

it('rethrows retryable errors while attempts remain and fails on the last attempt', function () {
    $mapping = kitchenEsbMapping();
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'fail'], 503)]);
    $run = queuedProductionOrderRun($mapping);

    $job = (new SyncEsbProductionOrdersJob($run->id))->withFakeQueueInteractions();
    expect(fn () => app()->call([$job, 'handle']))->toThrow(EsbProductionOrderException::class);
    expect($run->refresh()->status)->toBe(EsbProductionOrderSyncStatus::Running)->and($run->error_class)->toBe('retryable');

    $job = (new SyncEsbProductionOrdersJob($run->id))->withFakeQueueInteractions();
    $job->job->attempts = 3;
    app()->call([$job, 'handle']);

    expect($run->refresh()->status)->toBe(EsbProductionOrderSyncStatus::Failed)->and($run->error_code)->toBe('503');
});

it('resumes after the last stored page on retry', function () {
    $mapping = kitchenEsbMapping();
    config()->set('kitchen.production_orders.page_limit', 1);
    $run = queuedProductionOrderRun($mapping);
    $run->update(['last_page' => 1, 'pages_fetched' => 1]);
    fakeProductionOrderEsb([
        esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderNum' => 'PO-1'])], 1, 2, 'next'),
        esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderNum' => 'PO-2'])], 2, 2),
    ]);

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    expect(EsbProductionOrder::query()->pluck('production_order_num')->all())->toBe(['PO-2'])
        ->and($run->refresh()->pages_fetched)->toBe(2);
});

it('stops at the maximum page guard instead of looping', function () {
    $mapping = kitchenEsbMapping();
    config()->set('kitchen.production_orders.page_limit', 1);
    config()->set('kitchen.production_orders.max_pages', 3);
    Http::fake(function (Request $request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_ends_with($path, '/production/production-order')
            ? Http::response(esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderNum' => 'PO-'.$query['page']])], (int) $query['page'], 999, 'next'))
            : Http::response(esbProductionOrderDetailResponse(['productionOrderNum' => basename($path)]));
    });
    $run = queuedProductionOrderRun($mapping);

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    expect($run->refresh()->status)->toBe(EsbProductionOrderSyncStatus::Failed)
        ->and($run->error_class)->toBe('malformed')
        ->and($run->pages_fetched)->toBe(3)
        ->and(EsbProductionOrder::query()->count())->toBe(3);
});

it('fails a run whose mapping became incomplete without calling ESB', function () {
    Http::fake();
    $mapping = kitchenEsbMapping();
    $run = queuedProductionOrderRun($mapping);
    $mapping->update(['is_active' => false]);

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    expect($run->refresh()->status)->toBe(EsbProductionOrderSyncStatus::Failed)
        ->and($run->error_class)->toBe('configuration')
        ->and($run->error_summary)->toBe('Mapping ESB tidak aktif.');
    Http::assertNothingSent();
});

it('ignores a run that already finished', function () {
    Http::fake();
    $mapping = kitchenEsbMapping();
    $run = EsbProductionOrderSyncRun::factory()->forMapping($mapping)->create();

    SyncEsbProductionOrdersJob::dispatchSync($run->id);

    Http::assertNothingSent();
});

it('reports a detail failure once after its retries are exhausted', function () {
    $mapping = kitchenEsbMapping();
    $run = queuedProductionOrderRun($mapping);
    $run->update(['status' => EsbProductionOrderSyncStatus::Running, 'details_expected' => 1, 'list_completed_at' => now()]);
    $order = EsbProductionOrder::factory()->forMapping($mapping)->withoutDetails()->create(['production_order_num' => 'PO-0001']);
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'fail'], 500)]);

    $job = (new SyncEsbProductionOrderDetailJob($order->id, $run->id))->withFakeQueueInteractions();
    expect(fn () => app()->call([$job, 'handle']))->toThrow(EsbProductionOrderException::class);
    expect($run->refresh()->details_failed)->toBe(0);

    $job->failed(new RuntimeException('exhausted'));

    expect($run->refresh()->details_failed)->toBe(1)->and($run->status)->toBe(EsbProductionOrderSyncStatus::Partial);
});

it('keeps queue payloads to identifiers only', function () {
    Queue::fake();
    $mapping = kitchenEsbMapping();
    $planner = app(ProductionOrderSyncPlanner::class);
    $planner->dispatch($planner->units([$mapping], EsbProductionOrderSyncMode::Incremental, ...$planner->incrementalRange()), EsbProductionOrderSyncMode::Incremental);

    Queue::assertPushed(SyncEsbProductionOrdersJob::class, fn (SyncEsbProductionOrdersJob $job): bool => array_keys(get_object_vars($job)) !== [] && ! str_contains(serialize($job), 'token'));
});

// ── Commands and scheduler ─────────────────────────────────────────────────

it('previews the incremental sync without dispatching on dry-run', function () {
    Queue::fake();
    kitchenEsbMapping();
    kitchenEsbMapping(['is_active' => false, 'esb_branch_code' => 'OFF']);

    $this->artisan('kitchen:sync-production-orders', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN (no change) — incremental 2026-10-07 → 2026-10-28, 1 mapping(s), 1 unit(s).')
        ->expectsOutputToContain('Mapping ESB tidak aktif.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
    expect(EsbProductionOrderSyncRun::query()->count())->toBe(0);
});

it('dispatches the incremental window per mapping', function () {
    Queue::fake();
    kitchenEsbMapping();

    $this->artisan('kitchen:sync-production-orders')->assertSuccessful();

    Queue::assertPushed(SyncEsbProductionOrdersJob::class, 1);
    expect(EsbProductionOrderSyncRun::query()->sole()->only(['mode', 'status']))->toBe(['mode' => EsbProductionOrderSyncMode::Incremental, 'status' => EsbProductionOrderSyncStatus::Queued])
        ->and(EsbProductionOrderSyncRun::query()->sole()->date_from->toDateString())->toBe('2026-10-07');
});

it('guards the backfill range and dispatches only with --execute', function () {
    Queue::fake();
    kitchenEsbMapping();

    $this->artisan('kitchen:backfill-production-orders', ['--from' => '2026-10-10', '--to' => '2026-10-01'])->assertFailed();
    $this->artisan('kitchen:backfill-production-orders', ['--from' => '2026-01-01', '--to' => '2026-10-28'])
        ->expectsOutputToContain('melebihi batas 120 hari')
        ->assertFailed();
    $this->artisan('kitchen:backfill-production-orders', ['--from' => '2026-07-16', '--to' => '2026-10-28'])->assertSuccessful();
    Queue::assertNothingPushed();

    $this->artisan('kitchen:backfill-production-orders', ['--from' => '2026-07-16', '--to' => '2026-10-28', '--execute' => true, '--force' => true])->assertSuccessful();
    Queue::assertPushed(SyncEsbProductionOrdersJob::class, 4);
    expect(EsbProductionOrderSyncRun::query()->pluck('mode')->unique()->all())->toBe([EsbProductionOrderSyncMode::Backfill]);

    $this->artisan('kitchen:backfill-production-orders', ['--from' => '2026-01-01', '--to' => '2026-10-28', '--allow-long-range' => true])->assertSuccessful();
});

it('schedules only the incremental command and only while the sync toggle is on', function () {
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains($event->command, 'kitchen:'));

    expect($event->command)->toContain('kitchen:sync-production-orders')
        ->and($event->expression)->toBe('0 * * * *')
        ->and(collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_contains($event->command, 'kitchen:backfill'))->isEmpty())->toBeTrue();

    config()->set('kitchen.production_orders.sync_enabled', false);
    expect($event->filtersPass($this->app))->toBeFalse();

    config()->set('kitchen.production_orders.sync_enabled', true);
    expect($event->filtersPass($this->app))->toBeTrue();
});

it('prunes finished run history after the retention period but never snapshots', function () {
    $mapping = kitchenEsbMapping();
    $order = EsbProductionOrder::factory()->forMapping($mapping)->create();
    $old = EsbProductionOrderSyncRun::factory()->forMapping($mapping)->create(['created_at' => now()->subDays(91)]);
    $recent = EsbProductionOrderSyncRun::factory()->forMapping($mapping)->create();

    $this->artisan('model:prune', ['--model' => [EsbProductionOrderSyncRun::class]])->assertSuccessful();

    expect(EsbProductionOrderSyncRun::query()->pluck('id')->all())->toBe([$recent->id])
        ->and($old->fresh())->toBeNull()
        ->and($order->fresh())->not->toBeNull();
});
