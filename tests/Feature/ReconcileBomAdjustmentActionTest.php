<?php

use App\Actions\Rnd\Bom\ReconcileBomAdjustmentAction;
use App\Enums\RndBomChangeLogStatus;
use App\Models\RndBomChangeLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

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

it('promotes a needs-reconciliation record to Success when ESB already applied the requested change', function () {
    $reconciler = User::factory()->create();
    $changeLog = RndBomChangeLog::factory()->create([
        'esb_bom_id' => 42,
        'status' => RndBomChangeLogStatus::NeedsReconciliation,
        'before_snapshot' => ['productDetailID' => 100, 'uomName' => 'PCS', 'bomDetails' => [
            ['productDetailID' => 200, 'qty' => 100],
        ]],
        'requested_snapshot' => ['productDetailID' => 100, 'bomDetails' => [
            ['productDetailID' => 200, 'qty' => 150],
        ]],
    ]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/42' => Http::response(['status' => 'ok', 'result' => [
            'productDetailID' => 100, 'uomName' => 'PCS', 'editedDate' => '2026-08-01T00:00:00+07:00',
            'bomDetails' => [['productDetailID' => 200, 'qty' => 150]],
        ]]),
    ]);

    $result = app(ReconcileBomAdjustmentAction::class)->execute($changeLog, $reconciler);

    expect($result->status)->toBe(RndBomChangeLogStatus::Success)
        ->and($result->reconciled_by)->toBe($reconciler->id)
        ->and($result->reconciled_at)->not->toBeNull()
        ->and($result->esb_edited_at_after)->toBe('2026-08-01T00:00:00+07:00');
});

it('marks a needs-reconciliation record Failed when ESB never applied the requested change', function () {
    $reconciler = User::factory()->create();
    $changeLog = RndBomChangeLog::factory()->create([
        'esb_bom_id' => 42,
        'status' => RndBomChangeLogStatus::NeedsReconciliation,
        'before_snapshot' => ['productDetailID' => 100, 'uomName' => 'PCS', 'bomDetails' => [
            ['productDetailID' => 200, 'qty' => 100],
        ]],
        'requested_snapshot' => ['productDetailID' => 100, 'bomDetails' => [
            ['productDetailID' => 200, 'qty' => 150],
        ]],
    ]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/42' => Http::response(['status' => 'ok', 'result' => [
            'productDetailID' => 100, 'uomName' => 'PCS', 'editedDate' => '2026-07-01T00:00:00+07:00',
            'bomDetails' => [['productDetailID' => 200, 'qty' => 100]],
        ]]),
    ]);

    $result = app(ReconcileBomAdjustmentAction::class)->execute($changeLog, $reconciler);

    expect($result->status)->toBe(RndBomChangeLogStatus::Failed);
});

it('refuses to reconcile a record that is not Needs Reconciliation', function () {
    $changeLog = RndBomChangeLog::factory()->create(['status' => RndBomChangeLogStatus::Success]);

    expect(fn () => app(ReconcileBomAdjustmentAction::class)->execute($changeLog, User::factory()->create()))
        ->toThrow(ValidationException::class);
});

it('never re-sends the mutation while reconciling', function () {
    $changeLog = RndBomChangeLog::factory()->create([
        'esb_bom_id' => 42,
        'status' => RndBomChangeLogStatus::NeedsReconciliation,
        'before_snapshot' => ['productDetailID' => 100, 'bomDetails' => []],
        'requested_snapshot' => ['productDetailID' => 100, 'bomDetails' => []],
    ]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/42' => Http::response(['status' => 'ok', 'result' => ['productDetailID' => 100, 'bomDetails' => []]]),
    ]);

    app(ReconcileBomAdjustmentAction::class)->execute($changeLog, User::factory()->create());

    Http::assertNotSent(fn ($request): bool => $request->method() === 'PUT');
});
