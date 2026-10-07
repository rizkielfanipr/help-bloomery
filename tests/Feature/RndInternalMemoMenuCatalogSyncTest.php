<?php

use App\Exceptions\Rnd\InternalMemoCatalogUnavailableException;
use App\Jobs\SyncInternalMemoMenuCatalogJob;
use App\Models\Brand;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoBranch;
use App\Models\RndInternalMemoCatalogSync;
use App\Models\RndInternalMemoMenuCatalog;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoCatalogContext;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogQuery;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * docs/rnd-internal-memo-brand-prd.md §11.4, §14, §23.5: one global BLSS catalog, refreshed by one
 * idempotent, locked job. The branchCode the ESB Master Menu endpoint needs is discovered from
 * ESB's own BLSS branch list (sorted by code) — there is no env/config value and no Brand input.
 */
beforeEach(function () {
    config()->set('esb.tokens.BLSS', 'static-token');
    config()->set('esb.base_url', 'https://esb.test');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'blss-user', 'password' => 'blss-secret']);
    Cache::put('esb_core.access_token.BLSS', 'core-token');
});

function runCatalogSync(?InternalMemoMenuCatalogService $service = null, ?int $triggeredBy = null): void
{
    (new SyncInternalMemoMenuCatalogJob($triggeredBy))->handle($service ?? app(InternalMemoMenuCatalogService::class), app(InternalMemoCatalogContext::class));
}

/**
 * @param  list<string>  $branchCodes  ESB BLSS branches, in the order ESB returns them
 * @param  array<string, list<array<string, mixed>>>  $menusByBranch
 */
function fakeBlssEsb(array $branchCodes, array $menusByBranch): void
{
    Http::fake(function (Request $request) use ($branchCodes, $menusByBranch) {
        if (str_starts_with($request->url(), 'https://esb.test/core/branch')) {
            return Http::response(['status' => 'ok', 'result' => array_map(fn (string $code): array => ['branchID' => crc32($code), 'branchCode' => $code, 'branchName' => 'Cabang '.$code], $branchCodes)]);
        }

        $menus = $menusByBranch[$request['branchCode']] ?? null;
        if ($menus === null) {
            return Http::response(['status' => 'error', 'message' => 'Branch tidak dikenal'], 500);
        }

        return Http::response(['status' => 'ok', 'result' => [
            'data' => array_values(array_slice($menus, ((int) $request['page'] - 1) * 2, 2)),
            'limit' => 2,
            'count' => count($menus),
        ]]);
    });
}

it('discovers the BLSS branch from ESB by a fixed rule, syncs every page, and records the branch used', function () {
    RndInternalMemo::factory()->forBrand(Brand::factory()->create(['name' => 'Brand Alpha']))->create();
    fakeBlssEsb(['BLS', 'blev', 'BLP'], ['BLEV' => [
        ['menuID' => 1, 'menuCode' => 'M-1', 'menuName' => 'Menu 1', 'bomID' => 100, 'flagActive' => 1],
        ['menuID' => 2, 'menuCode' => 'M-2', 'menuName' => 'Menu 2', 'bomID' => 0, 'flagActive' => 1],
        ['menuID' => 3, 'menuCode' => 'M-3', 'menuName' => 'Menu 3', 'bomID' => 300, 'flagActive' => 1],
    ]]);

    runCatalogSync();

    expect(RndInternalMemoMenuCatalog::query()->where('company_code', 'BLSS')->where('branch_code', 'BLEV')->count())->toBe(3);
    $state = RndInternalMemoCatalogSync::query()->sole();
    expect($state->only(['company_code', 'technical_branch_code', 'status', 'menu_count']))
        ->toBe(['company_code' => 'BLSS', 'technical_branch_code' => 'BLEV', 'status' => 'success', 'menu_count' => 3])
        ->and(app(InternalMemoCatalogContext::class)->catalogBranchCode())->toBe('BLEV');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/corev1/master/get-menu')
        && $request->hasHeader('Authorization', 'Bearer static-token')
        && $request['branchCode'] === 'BLEV'
        && array_keys($request->data()) === ['page', 'limit', 'branchCode', 'Boolean']);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url().$request->body(), 'Brand Alpha'));
});

it('falls back to the next branch in order when the first answers with an error or no Menus', function () {
    fakeBlssEsb(['BLA', 'BLB', 'BLC'], [
        'BLB' => [],
        'BLC' => [['menuID' => 9, 'menuName' => 'Menu 9', 'bomID' => 90, 'flagActive' => 1]],
    ]);

    runCatalogSync();

    expect(RndInternalMemoCatalogSync::query()->sole()->technical_branch_code)->toBe('BLC')
        ->and(RndInternalMemoMenuCatalog::query()->pluck('branch_code')->all())->toBe(['BLC']);
});

it('upserts idempotently on retry and drops only Menus ESB no longer returns', function () {
    fakeBlssEsb(['BLS'], []);
    $menu = fn (int $id, string $name): array => ['menuID' => $id, 'menuCode' => 'M-'.$id, 'menuName' => $name, 'categoryDetail' => null, 'bomID' => 100, 'bomName' => null, 'flagActive' => true, 'hasBom' => true, 'raw' => []];
    $service = Mockery::mock(InternalMemoMenuCatalogService::class);
    $service->shouldReceive('allForContext')->with('BLSS', 'BLS')->times(3)->andReturn(
        [$menu(1, 'Menu 1'), $menu(2, 'Menu 2')],
        [$menu(1, 'Menu 1'), $menu(2, 'Menu 2')],
        [$menu(1, 'Menu 1 v2')],
    );

    runCatalogSync($service);
    $this->travel(2)->seconds();
    runCatalogSync($service);

    expect(RndInternalMemoMenuCatalog::query()->count())->toBe(2);

    $this->travel(2)->seconds();
    runCatalogSync($service);

    expect(RndInternalMemoMenuCatalog::query()->pluck('menu_name', 'menu_id')->all())->toBe([1 => 'Menu 1 v2']);
});

it('keeps the last-known-good snapshot and records a global failure when every branch fails or ESB has no branch', function () {
    markInternalMemoCatalogSynced('BLS', now()->subHour());
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 77, 'flag_active' => true]);

    fakeBlssEsb(['BLS', 'BLP'], []);
    expect(fn () => runCatalogSync())->toThrow(RuntimeException::class);

    $state = RndInternalMemoCatalogSync::query()->sole();
    expect(RndInternalMemoMenuCatalog::query()->where('menu_id', 77)->exists())->toBeTrue()
        ->and($state->status)->toBe('failed')
        ->and($state->technical_branch_code)->toBe('BLS')
        ->and($state->last_error)->not->toContain('static-token')
        ->and(collect(app(InternalMemoMenuCatalogQuery::class)->paginate()->items())->pluck('menu_id')->all())->toBe([77]);
});

it('fails in a controlled way and keeps the snapshot when ESB returns no BLSS branch', function () {
    markInternalMemoCatalogSynced('BLS', now()->subHour());
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 77, 'flag_active' => true]);
    fakeBlssEsb([], []);

    expect(fn () => runCatalogSync())->toThrow(InternalMemoCatalogUnavailableException::class, 'tidak mengembalikan cabang BLSS');

    expect(RndInternalMemoMenuCatalog::query()->where('menu_id', 77)->exists())->toBeTrue()
        ->and(RndInternalMemoCatalogSync::query()->sole()->status)->toBe('failed');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'get-menu'));
});

it('needs no configuration and shows nothing selectable before the first successful sync', function () {
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 1, 'flag_active' => true]);

    expect(app(InternalMemoCatalogContext::class)->catalogBranchCode())->toBeNull()
        ->and(app(InternalMemoMenuCatalogQuery::class)->paginate()->total())->toBe(0)
        ->and(app(InternalMemoMenuCatalogQuery::class)->findSelectable(1))->toBeNull();
});

it('queues one global sync for a stale snapshot and none while one is in progress or fresh', function () {
    Queue::fake();
    $context = app(InternalMemoCatalogContext::class);
    $userId = User::factory()->create()->id;

    expect($context->requestSync($userId))->toBeTrue()
        ->and($context->requestSync($userId))->toBeFalse();
    Queue::assertPushed(SyncInternalMemoMenuCatalogJob::class, 1);
    expect($context->state()->status)->toBe('queued');

    // The first job "finished": its state is success and its unique lock is released.
    $context->state()->update(['status' => 'success', 'last_synced_at' => now()]);
    (new UniqueLock(app('cache.store')))->release(new SyncInternalMemoMenuCatalogJob);
    expect($context->requestSync($userId))->toBeFalse()
        ->and($context->requestSync($userId, force: true))->toBeTrue();
    Queue::assertPushed(SyncInternalMemoMenuCatalogJob::class, 2);
});

it('is locked per company and keeps its timeout below the queue retry_after', function () {
    $job = new SyncInternalMemoMenuCatalogJob(7);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((new SyncInternalMemoMenuCatalogJob(9))->uniqueId())
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->timeout)->toBeLessThan((int) config('queue.connections.database.retry_after'))
        ->and($job->tries)->toBeGreaterThan(1)
        ->and($job->backoff)->not->toBeEmpty();
});

it('serves one shared catalog to every Memo regardless of Brand, without Branch or company filters', function () {
    markInternalMemoCatalogSynced('BLS');
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 1, 'menu_name' => 'Croissant', 'flag_active' => true]);
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 2, 'menu_name' => 'Nonaktif', 'flag_active' => false]);
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLO6', 'branch_code' => 'BLE', 'menu_id' => 3, 'menu_name' => 'Menu BLO6', 'flag_active' => true]);
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLP', 'menu_id' => 4, 'menu_name' => 'Konteks Lain', 'flag_active' => true]);
    $legacyMemo = RndInternalMemo::factory()->create();
    RndInternalMemoBranch::factory()->create(['rnd_internal_memo_id' => $legacyMemo->id, 'company_code_snapshot' => 'BLO6', 'branch_code_snapshot' => 'BLE']);

    $names = collect(app(InternalMemoMenuCatalogQuery::class)->paginate()->items())->pluck('menu_name')->all();

    expect($names)->toBe(['Croissant'])
        ->and(collect((new ReflectionMethod(InternalMemoMenuCatalogQuery::class, 'paginate'))->getParameters())->map->getName()->all())
        ->toBe(['perPage', 'name', 'code', 'page']);
});

it('stores only the light Menu identity fields, not the heavy ESB nested data', function () {
    fakeBlssEsb(['BLS'], ['BLS' => [[
        'menuID' => 1, 'menuCode' => 'M-1', 'menuName' => 'Menu 1', 'bomID' => 100, 'flagActive' => 1,
        'menuTemplates' => array_fill(0, 50, ['price' => 1000]), 'menuImage' => str_repeat('x', 5000), 'menuExtras' => [['id' => 1]],
    ]]]);

    runCatalogSync();

    expect(RndInternalMemoMenuCatalog::query()->sole()->raw_snapshot)
        ->toBe(['menuID' => 1, 'menuCode' => 'M-1', 'menuName' => 'Menu 1', 'bomID' => 100, 'flagActive' => 1]);
});
