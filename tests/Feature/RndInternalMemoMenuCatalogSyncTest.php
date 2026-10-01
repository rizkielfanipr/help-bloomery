<?php

use App\Jobs\SyncInternalMemoMenuCatalogJob;
use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoBranch;
use App\Models\RndInternalMemoMenuCatalog;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogQuery;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use Illuminate\Support\Facades\Http;

function memoBranchContext(RndInternalMemo $memo, string $companyCode, string $branchCode, string $name): RndInternalMemoBranch
{
    return RndInternalMemoBranch::factory()->create([
        'rnd_internal_memo_id' => $memo->id,
        'branch_id' => Branch::factory()->create(['name' => $name])->id,
        'company_code_snapshot' => $companyCode,
        'branch_code_snapshot' => $branchCode,
        'branch_name_snapshot' => $name,
        'catalog_sync_status' => 'pending',
    ]);
}

it('syncs every Master Menu page into an isolated company and branch snapshot', function () {
    $memo = RndInternalMemo::factory()->create();
    $context = memoBranchContext($memo, 'BLSS', 'BLS', 'Bloomery Pabelan');
    config()->set('esb.tokens.BLSS', 'static-token');
    config()->set('esb.base_url', 'https://esb.test');

    Http::fake(function ($request) {
        $page = (int) $request['page'];

        return Http::response([
            'status' => 'ok',
            'result' => [
                'data' => [[
                    'menuID' => $page,
                    'menuCode' => 'M-'.$page,
                    'menuName' => 'Menu '.$page,
                    'bomID' => $page === 1 ? 100 : 0,
                    'flagActive' => 1,
                ]],
                'limit' => 1,
                'count' => 2,
            ],
        ]);
    });

    (new SyncInternalMemoMenuCatalogJob('BLSS', 'BLS'))->handle(app(InternalMemoMenuCatalogService::class));

    expect(RndInternalMemoMenuCatalog::query()->count())->toBe(2)
        ->and($context->refresh()->catalog_sync_status)->toBe('synced')
        ->and($context->catalog_synced_at)->not->toBeNull();

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer static-token')
        && $request['branchCode'] === 'BLS');
});

it('keeps the previous successful snapshot and records a branch-level error when refresh fails', function () {
    $memo = RndInternalMemo::factory()->create();
    $context = memoBranchContext($memo, 'BLSS', 'BLS', 'Bloomery Pabelan');
    RndInternalMemoMenuCatalog::factory()->create([
        'company_code' => 'BLSS',
        'branch_code' => 'BLS',
        'menu_id' => 77,
    ]);

    $service = Mockery::mock(InternalMemoMenuCatalogService::class);
    $service->shouldReceive('allForContext')->once()->andThrow(new RuntimeException('ESB unavailable'));

    expect(fn () => (new SyncInternalMemoMenuCatalogJob('BLSS', 'BLS'))->handle($service))
        ->toThrow(RuntimeException::class, 'ESB unavailable');

    expect(RndInternalMemoMenuCatalog::query()->where('menu_id', 77)->exists())->toBeTrue()
        ->and($context->refresh()->catalog_sync_status)->toBe('failed')
        ->and($context->catalog_sync_error)->toContain('ESB unavailable');
});

it('merges the same company Menu across Memo branches while keeping branch availability', function () {
    $memo = RndInternalMemo::factory()->create();
    memoBranchContext($memo, 'BLSS', 'BLS', 'Bloomery Pabelan');
    memoBranchContext($memo, 'BLSS', 'BLP', 'Bloomery Pakuwon');

    foreach (['BLS', 'BLP'] as $branchCode) {
        RndInternalMemoMenuCatalog::factory()->create([
            'company_code' => 'BLSS',
            'branch_code' => $branchCode,
            'menu_id' => 10,
            'menu_code' => 'MENU-10',
            'menu_name' => 'Croissant Butter',
        ]);
    }

    $query = app(InternalMemoMenuCatalogQuery::class);
    $result = $query->paginate($memo);

    expect($result->total())->toBe(1)
        ->and($query->branchNamesForMenu($memo, 'BLSS', 10))->toEqualCanonicalizing([
            'Bloomery Pabelan',
            'Bloomery Pakuwon',
        ]);
});

it('does not expose catalog rows outside the Memo branch contexts', function () {
    $memo = RndInternalMemo::factory()->create();
    memoBranchContext($memo, 'BLSS', 'BLS', 'Bloomery Pabelan');
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 1]);
    RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLO6', 'branch_code' => 'BLE', 'menu_id' => 2]);

    $result = app(InternalMemoMenuCatalogQuery::class)->paginate($memo);

    expect($result->total())->toBe(1)
        ->and($result->first()->menu_id)->toBe(1);
});
