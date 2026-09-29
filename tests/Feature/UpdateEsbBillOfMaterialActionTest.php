<?php

use App\Actions\Rnd\Bom\UpdateEsbBillOfMaterialAction;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Exceptions\Rnd\BomConflictException;
use App\Exceptions\Rnd\BomInvariantException;
use App\Models\RndBomChangeLog;
use App\Models\User;
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

function actionBomDetail(array $overrides = []): array
{
    return array_merge([
        'bomID' => 42,
        'bomTypeID' => 1,
        'bomTypeName' => 'Assembly',
        'bomName' => 'Croissant Assembly',
        'bomCode' => 'BOM-CRS',
        'productDetailID' => 100,
        'productName' => 'Croissant',
        'productCode' => 'CRS',
        'uomName' => 'PCS',
        'bomCostTotal' => 0,
        'notes' => 'Test BOM',
        'accessType' => 0,
        'editedDate' => '2026-07-30T10:00:00+07:00',
        'bomDetails' => [[
            'ID' => 7, 'productID' => 2, 'productDetailID' => 200, 'productName' => 'Butter',
            'productCode' => 'BTR', 'uomName' => 'GRAM', 'qty' => 100, 'lastHpp' => 125,
            'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '',
        ]],
    ], $overrides);
}

function actionDraft(array $overrides = []): array
{
    return array_merge([
        'productDetailID' => 100,
        'productName' => 'Croissant',
        'productCode' => 'CRS',
        'uomName' => 'PCS',
        'bomDetails' => [[
            'ID' => 7, 'productDetailID' => 200, 'productCode' => 'BTR', 'productName' => 'Butter',
            'uomName' => 'GRAM', 'lastHPP' => 125, 'qty' => 150, 'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '',
        ]],
    ], $overrides);
}

function fakeBomLoginAnd(int $bomId, mixed $response): void
{
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        "https://core-esb.test/product/bom/{$bomId}" => $response,
    ]);
}

it('updates a BOM successfully and records a verified after snapshot', function () {
    $actor = User::factory()->create();
    fakeBomLoginAnd(42, Http::sequence()
        ->push(['status' => 'ok', 'result' => actionBomDetail()])
        ->push(['status' => 'ok', 'result' => null])
        ->push(['status' => 'ok', 'result' => actionBomDetail(['editedDate' => '2026-07-30T11:00:00+07:00', 'bomDetails' => [[
            'ID' => 7, 'productID' => 2, 'productDetailID' => 200, 'productName' => 'Butter',
            'productCode' => 'BTR', 'uomName' => 'GRAM', 'qty' => 150, 'lastHpp' => 125,
            'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '',
        ]]])]));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(
        bomId: 42,
        draft: actionDraft(),
        loadedEditedDate: '2026-07-30T10:00:00+07:00',
        reason: 'Naikkan porsi butter',
        source: RndBomChangeLogSource::BomAdjustment,
        actor: $actor,
    );

    expect($log->status)->toBe(RndBomChangeLogStatus::Success)
        ->and($log->reason)->toBe('Naikkan porsi butter')
        ->and($log->source)->toBe(RndBomChangeLogSource::BomAdjustment)
        ->and($log->changed_by)->toBe($actor->id)
        ->and($log->esb_edited_at_before)->toBe('2026-07-30T10:00:00+07:00')
        ->and($log->esb_edited_at_after)->toBe('2026-07-30T11:00:00+07:00')
        ->and((float) data_get($log->changes, 'components_changed.0.after_qty'))->toBe(150.0)
        ->and($log->after_snapshot['bomDetails'][0]['qty'])->toBe(150);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && data_get($request->data(), 'bomDetails.0.qty') === 150.0);
});

it('stops before any mutation when editedDate conflicts', function () {
    fakeBomLoginAnd(42, Http::response(['status' => 'ok', 'result' => actionBomDetail(['editedDate' => '2026-08-01T00:00:00+07:00'])]));

    expect(fn () => app(UpdateEsbBillOfMaterialAction::class)->execute(
        bomId: 42,
        draft: actionDraft(),
        loadedEditedDate: '2026-07-30T10:00:00+07:00',
        reason: null,
        source: RndBomChangeLogSource::BomAdjustment,
        actor: null,
    ))->toThrow(BomConflictException::class);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
    expect(RndBomChangeLog::query()->count())->toBe(0);
});

it('rejects a draft with no components before creating any attempt record', function () {
    fakeBomLoginAnd(42, Http::response(['status' => 'ok', 'result' => actionBomDetail()]));

    expect(fn () => app(UpdateEsbBillOfMaterialAction::class)->execute(
        bomId: 42,
        draft: actionDraft(['bomDetails' => []]),
        loadedEditedDate: null,
        reason: null,
        source: RndBomChangeLogSource::BomAdjustment,
        actor: null,
    ))->toThrow(BomInvariantException::class);

    expect(RndBomChangeLog::query()->count())->toBe(0);
});

it('rejects a Product Result used as its own component', function () {
    fakeBomLoginAnd(42, Http::response(['status' => 'ok', 'result' => actionBomDetail()]));

    try {
        app(UpdateEsbBillOfMaterialAction::class)->execute(
            bomId: 42,
            draft: actionDraft(['productDetailID' => 200]),
            loadedEditedDate: null,
            reason: null,
            source: RndBomChangeLogSource::BomAdjustment,
            actor: null,
        );
        $this->fail('Expected BomInvariantException');
    } catch (BomInvariantException $exception) {
        expect($exception->field)->toBe('productDetailID');
    }
});

it('rejects duplicate Product Detail components', function () {
    fakeBomLoginAnd(42, Http::response(['status' => 'ok', 'result' => actionBomDetail()]));

    $draft = actionDraft(['bomDetails' => [
        ['ID' => 7, 'productDetailID' => 200, 'lastHPP' => 1, 'qty' => 10, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => ''],
        ['ID' => 8, 'productDetailID' => 200, 'lastHPP' => 1, 'qty' => 5, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => ''],
    ]]);

    expect(fn () => app(UpdateEsbBillOfMaterialAction::class)->execute(42, $draft, null, null, RndBomChangeLogSource::BomAdjustment, null))
        ->toThrow(BomInvariantException::class);
});

it('rejects a zero or negative component quantity', function () {
    fakeBomLoginAnd(42, Http::response(['status' => 'ok', 'result' => actionBomDetail()]));

    $draft = actionDraft(['bomDetails' => [
        ['ID' => 7, 'productDetailID' => 200, 'lastHPP' => 1, 'qty' => 0, 'yieldPercent' => 0, 'tolerancePercent' => 0, 'printGroup' => ''],
    ]]);

    expect(fn () => app(UpdateEsbBillOfMaterialAction::class)->execute(42, $draft, null, null, RndBomChangeLogSource::BomAdjustment, null))
        ->toThrow(BomInvariantException::class);
});

it('allows a Menu draft to reuse its own productDetailID since Menu has no self-reference concept', function () {
    fakeBomLoginAnd(42, Http::sequence()
        ->push(['status' => 'ok', 'result' => actionBomDetail(['bomTypeID' => 3, 'bomTypeName' => 'Menu'])])
        ->push(['status' => 'ok', 'result' => null])
        ->push(['status' => 'ok', 'result' => actionBomDetail(['bomTypeID' => 3, 'bomTypeName' => 'Menu'])]));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(
        bomId: 42,
        draft: actionDraft(['productDetailID' => 200]),
        loadedEditedDate: null,
        reason: null,
        source: RndBomChangeLogSource::Project,
        actor: null,
    );

    expect($log->status)->toBe(RndBomChangeLogStatus::Success);
});

it('marks the attempt Failed without retry when ESB rejects the mutation', function () {
    fakeBomLoginAnd(42, Http::sequence()
        ->push(['status' => 'ok', 'result' => actionBomDetail()])
        ->push(['status' => 'fail', 'errors' => [['message' => 'Quantity tidak valid']]], 422));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, actionDraft(), null, null, RndBomChangeLogSource::BomAdjustment, null);

    expect($log->status)->toBe(RndBomChangeLogStatus::Failed)
        ->and($log->error_message)->toContain('Quantity tidak valid');
    expect(Http::recorded(fn ($request): bool => $request->method() === 'PUT'))->toHaveCount(1);
});

it('marks the attempt Needs Reconciliation without retry on a connection failure during mutation', function () {
    fakeBomLoginAnd(42, Http::sequence()
        ->push(['status' => 'ok', 'result' => actionBomDetail()])
        ->pushFailedConnection('connection reset'));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, actionDraft(), null, null, RndBomChangeLogSource::BomAdjustment, null);

    expect($log->status)->toBe(RndBomChangeLogStatus::NeedsReconciliation);
    expect(Http::recorded(fn ($request): bool => $request->method() === 'PUT'))->toHaveCount(1);
});

it('marks the attempt Needs Reconciliation without retry on a timeout during mutation', function () {
    fakeBomLoginAnd(42, Http::sequence()
        ->push(['status' => 'ok', 'result' => actionBomDetail()])
        ->pushFailedConnection('cURL error 28: Operation timed out after 60000 milliseconds'));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, actionDraft(), null, null, RndBomChangeLogSource::BomAdjustment, null);

    expect($log->status)->toBe(RndBomChangeLogStatus::NeedsReconciliation);
    expect(Http::recorded(fn ($request): bool => $request->method() === 'PUT'))->toHaveCount(1);
});

it('marks the attempt Needs Reconciliation when the mutation succeeds but the verification refetch fails', function () {
    fakeBomLoginAnd(42, Http::sequence()
        ->push(['status' => 'ok', 'result' => actionBomDetail()])
        ->push(['status' => 'ok', 'result' => null])
        ->pushFailedConnection('connection reset'));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, actionDraft(), null, null, RndBomChangeLogSource::BomAdjustment, null);

    expect($log->status)->toBe(RndBomChangeLogStatus::NeedsReconciliation)
        ->and($log->after_snapshot)->toBeNull();
    expect(Http::recorded(fn ($request): bool => $request->method() === 'PUT'))->toHaveCount(1);
});
