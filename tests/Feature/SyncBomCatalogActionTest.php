<?php

use App\Actions\Rnd\Bom\SyncBomCatalogAction;
use App\Enums\RndBomCatalogSyncStatus;
use App\Enums\RndBomChangeLogEvent;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Jobs\Rnd\SyncBomCatalogJob;
use App\Models\RndBomCatalog;
use App\Models\RndBomChangeLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'global-user',
        'esb.core.password' => 'global-password',
    ]);
});

function bomBrowseRow(int $id, int $bomTypeId = 1, string $bomTypeName = 'Assembly'): array
{
    return ['bomID' => $id, 'bomTypeID' => $bomTypeId, 'bomTypeName' => $bomTypeName, 'bomCode' => "BOM-{$id}", 'bomName' => "BOM {$id}"];
}

function bomDetailFor(int $id, array $overrides = []): array
{
    return array_merge([
        'bomID' => $id,
        'bomTypeID' => 1,
        'bomTypeName' => 'Assembly',
        'bomCode' => "BOM-{$id}",
        'bomName' => "BOM {$id}",
        'productDetailID' => 900 + $id,
        'productCode' => "PRD-{$id}",
        'productName' => "Product {$id}",
        'uomName' => 'PCS',
        'editedDate' => '2026-07-30T10:00:00+07:00',
        'bomDetails' => [
            ['ID' => 1, 'productDetailID' => 200, 'productCode' => 'BTR', 'productName' => 'Butter', 'uomName' => 'GRAM', 'qty' => 100, 'lastHpp' => 10],
        ],
    ], $overrides);
}

function fakeBomBrowseAndDetail(array $activeRows, array $inactiveRows, array $detailByBomId): void
{
    Http::fake(function (Request $request) use ($activeRows, $inactiveRows, $detailByBomId) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }
        if (preg_match('#/product/bom/(\d+)$#', $request->url(), $matches)) {
            $bomId = (int) $matches[1];

            return Http::response(['status' => 'ok', 'result' => $detailByBomId[$bomId] ?? []]);
        }
        if (str_contains($request->url(), '/product/bom')) {
            $rows = ((int) ($request['flagActive'] ?? 1)) === 1 ? $activeRows : $inactiveRows;

            return Http::response(['status' => 'ok', 'result' => [
                'page' => 1, 'limit' => 100, 'count' => count($rows), 'data' => $rows, 'prev' => '', 'next' => '',
            ]]);
        }

        return Http::response(['status' => 'ok', 'result' => []]);
    });
}

it('stores only Assembly BOMs and tags active/inactive from the browse pass', function () {
    fakeBomBrowseAndDetail(
        activeRows: [bomBrowseRow(10), bomBrowseRow(20, 3, 'Menu')],
        inactiveRows: [bomBrowseRow(30)],
        detailByBomId: [10 => bomDetailFor(10), 30 => bomDetailFor(30)],
    );

    $result = app(SyncBomCatalogAction::class)->execute();

    expect($result)->toMatchArray(['status' => 'completed', 'processed' => 2, 'succeeded' => 2, 'failed' => 0]);
    expect(RndBomCatalog::query()->count())->toBe(2);

    $active = RndBomCatalog::query()->where('esb_bom_id', 10)->sole();
    expect($active->is_active)->toBeTrue()
        ->and($active->component_count)->toBe(1)
        ->and($active->sync_status)->toBe(RndBomCatalogSyncStatus::Synced)
        ->and($active->esb_edited_at)->toBe('2026-07-30T10:00:00+07:00');

    $inactive = RndBomCatalog::query()->where('esb_bom_id', 30)->sole();
    expect($inactive->is_active)->toBeFalse();

    expect(RndBomCatalog::query()->where('esb_bom_id', 20)->exists())->toBeFalse();
});

it('records an external change log when a snapshot drifts without a matching local mutation', function () {
    RndBomCatalog::factory()->create([
        'esb_bom_id' => 10,
        'is_active' => true,
        'esb_edited_at' => '2026-07-01T00:00:00+07:00',
        'detail_snapshot' => bomDetailFor(10, ['editedDate' => '2026-07-01T00:00:00+07:00']),
    ]);

    fakeBomBrowseAndDetail(
        activeRows: [bomBrowseRow(10)],
        inactiveRows: [],
        detailByBomId: [10 => bomDetailFor(10, [
            'editedDate' => '2026-08-01T00:00:00+07:00',
            'bomDetails' => [
                ['ID' => 1, 'productDetailID' => 200, 'productCode' => 'BTR', 'productName' => 'Butter', 'uomName' => 'GRAM', 'qty' => 150, 'lastHpp' => 10],
            ],
        ])],
    );

    app(SyncBomCatalogAction::class)->execute();

    $log = RndBomChangeLog::query()->where('esb_bom_id', 10)->sole();
    expect($log->source)->toBe(RndBomChangeLogSource::ExternalEsb)
        ->and($log->event)->toBe(RndBomChangeLogEvent::ExternalChangeDetected)
        ->and($log->status)->toBe(RndBomChangeLogStatus::Success)
        ->and($log->changed_by)->toBeNull()
        ->and($log->esb_edited_at_before)->toBe('2026-07-01T00:00:00+07:00')
        ->and($log->esb_edited_at_after)->toBe('2026-08-01T00:00:00+07:00')
        ->and((float) data_get($log->changes, 'components_changed.0.after_qty'))->toBe(150.0);
});

it('does not log an external change when a matching local mutation already explains it', function () {
    RndBomCatalog::factory()->create([
        'esb_bom_id' => 10,
        'is_active' => true,
        'esb_edited_at' => '2026-07-01T00:00:00+07:00',
        'detail_snapshot' => bomDetailFor(10, ['editedDate' => '2026-07-01T00:00:00+07:00']),
    ]);
    RndBomChangeLog::factory()->create([
        'esb_bom_id' => 10,
        'status' => RndBomChangeLogStatus::Success,
        'esb_edited_at_after' => '2026-08-01T00:00:00+07:00',
    ]);

    fakeBomBrowseAndDetail(
        activeRows: [bomBrowseRow(10)],
        inactiveRows: [],
        detailByBomId: [10 => bomDetailFor(10, ['editedDate' => '2026-08-01T00:00:00+07:00'])],
    );

    app(SyncBomCatalogAction::class)->execute();

    expect(RndBomChangeLog::query()->where('esb_bom_id', 10)->where('source', RndBomChangeLogSource::ExternalEsb)->count())->toBe(0);
});

it('does not sync a second time while a sync is already holding the lock', function () {
    fakeBomBrowseAndDetail(activeRows: [bomBrowseRow(10)], inactiveRows: [], detailByBomId: [10 => bomDetailFor(10)]);

    $lock = Cache::lock('rnd_bom_catalog_sync', 600);
    $lock->get();

    $result = app(SyncBomCatalogAction::class)->execute();

    expect($result)->toBe(['status' => 'already_running']);
    Http::assertNothingSent();

    $lock->release();
});

it('records progress that can be polled without blocking the caller', function () {
    fakeBomBrowseAndDetail(activeRows: [bomBrowseRow(10)], inactiveRows: [], detailByBomId: [10 => bomDetailFor(10)]);

    app(SyncBomCatalogAction::class)->execute();

    $progress = SyncBomCatalogAction::progress();
    expect($progress)->not->toBeNull()
        ->and($progress['status'])->toBe('completed')
        ->and($progress['succeeded'])->toBe(1)
        ->and($progress['failed'])->toBe(0)
        ->and($progress['scanned'])->toBe(1)
        ->and($progress['total'])->toBe(1);
});

it('continues past a failing BOM and reports it without aborting the whole sync', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }
        if (str_ends_with($request->url(), '/product/bom/10')) {
            return Http::response(['status' => 'ok', 'result' => bomDetailFor(10)]);
        }
        if (str_ends_with($request->url(), '/product/bom/20')) {
            return Http::response(['status' => 'fail', 'message' => 'BOM not found'], 404);
        }
        if (str_contains($request->url(), '/product/bom')) {
            $rows = ((int) ($request['flagActive'] ?? 1)) === 1 ? [bomBrowseRow(10), bomBrowseRow(20)] : [];

            return Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => count($rows), 'data' => $rows, 'prev' => '', 'next' => '']]);
        }

        return Http::response(['status' => 'ok', 'result' => []]);
    });

    $result = app(SyncBomCatalogAction::class)->execute();

    expect($result)->toMatchArray(['processed' => 2, 'succeeded' => 1, 'failed' => 1]);
    expect(RndBomCatalog::query()->where('esb_bom_id', 10)->exists())->toBeTrue()
        ->and(RndBomCatalog::query()->where('esb_bom_id', 20)->exists())->toBeFalse();
});

it('dispatches through the job to the same action', function () {
    fakeBomBrowseAndDetail(activeRows: [bomBrowseRow(10)], inactiveRows: [], detailByBomId: [10 => bomDetailFor(10)]);

    (new SyncBomCatalogJob(triggeredBy: 7))->handle(app(SyncBomCatalogAction::class));

    expect(RndBomCatalog::query()->where('esb_bom_id', 10)->exists())->toBeTrue();
});
