<?php

use App\Enums\EsbProductionOrderErrorKind;
use App\Exceptions\Kitchen\EsbProductionOrderException;
use App\Services\EsbProductionOrderService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('kitchen.production_orders.global_credential_companies', []);
    config()->set('esb.core.companies.BLSS', ['username' => 'user', 'password' => 'secret']);
    config()->set('esb.core.companies.BLO6', ['username' => 'user-6', 'password' => 'secret-6']);
    Cache::put('esb_core.access_token.BLSS', 'token-blss');
    Cache::put('esb_core.access_token.BLO6', 'token-blo6');
});

function productionOrderError(Closure $callback): EsbProductionOrderException
{
    try {
        $callback();
    } catch (EsbProductionOrderException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected an EsbProductionOrderException.');
}

it('requests the list for the ESB Branch ID, window, and page with the company token', function () {
    Http::fake(['https://esb.test/core/production/production-order*' => Http::response(esbProductionOrderListResponse([esbProductionOrderRow()]))]);

    $page = app(EsbProductionOrderService::class)->listPage('blo6', 501, '2026-10-07', '2026-10-28', 2, 100);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://esb.test/core/production/production-order?')
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer token-blo6')
            && $query === ['branchID' => '501', 'dateFrom' => '2026-10-07', 'dateTo' => '2026-10-28', 'page' => '2', 'limit' => '100', 'sort' => 'productionOrderDate'];
    });
    expect($page['orders'])->toHaveCount(1)
        ->and($page['orders'][0])->toMatchArray([
            'production_order_num' => 'PO-0001',
            'production_order_date' => '2026-10-14',
            'esb_branch_id' => 501,
            'status_id' => '2',
            'status_name' => 'Authorized',
            'bom_type_name' => 'Assembly',
            'esb_authorized_at' => null,
        ])
        ->and($page['orders'][0]['payload_hash'])->toHaveLength(64)
        ->and($page['has_next'])->toBeFalse();
});

it('reports the next page and the total count', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(esbProductionOrderListResponse([esbProductionOrderRow()], 1, 150, 'next-page'))]);

    $page = app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100);

    expect($page['has_next'])->toBeTrue()->and($page['count'])->toBe(150);
});

it('returns an empty page as a successful read', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(esbProductionOrderListResponse([], 1, 0, 'ignored'))]);

    $page = app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100);

    expect($page['orders'])->toBe([])->and($page['has_next'])->toBeFalse();
});

it('keeps nullable header fields as null', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(esbProductionOrderListResponse([esbProductionOrderRow([
        'branchName' => null, 'bomTypeID' => null, 'statusName' => '', 'additionalInfo' => null, 'authorizedBy' => null, 'authorizedDate' => null,
    ])]))]);

    $order = app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100)['orders'][0];

    expect($order)->toMatchArray(['esb_branch_name' => null, 'bom_type_id' => null, 'status_name' => null, 'additional_info' => null, 'authorized_by_name' => null, 'esb_authorized_at' => null]);
});

it('reads one order with its details and materials as decimal strings', function () {
    Http::fake(['https://esb.test/core/production/production-order/PO-0001' => Http::response(esbProductionOrderDetailResponse())]);

    $order = app(EsbProductionOrderService::class)->order('BLSS', 501, 'PO-0001');

    expect($order['header']['production_order_num'])->toBe('PO-0001')
        ->and($order['details'])->toHaveCount(1)
        ->and($order['details'][0])->toMatchArray([
            'external_detail_id' => '9001',
            'product_code' => 'WIP-0001',
            'quantity' => '12.5',
            'uom_name' => 'Resep@500GR',
            'is_finished' => false,
            'sequence' => 1,
        ])
        ->and($order['details'][0]['materials'])->toBe([
            ['product_code' => 'RAW-0001', 'product_name' => 'Tepung Contoh', 'quantity' => '7.25', 'uom_name' => 'KG'],
            ['product_code' => 'RAW-0002', 'product_name' => 'Gula Contoh', 'quantity' => '1.5', 'uom_name' => 'KG'],
        ]);
});

it('refreshes the token once after a 401 and then succeeds', function () {
    $calls = 0;
    Http::fake(function (Request $request) use (&$calls) {
        if (str_ends_with($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]);
        }

        return ++$calls === 1
            ? Http::response(['status' => 'fail'], 401)
            : Http::response(esbProductionOrderListResponse([]));
    });

    $page = app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100);

    expect($page['orders'])->toBe([])->and($calls)->toBe(2);
});

it('classifies a repeated 401 as an authentication failure', function () {
    Http::fake(function (Request $request) {
        return str_ends_with($request->url(), '/auth/login')
            ? Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']])
            : Http::response(['status' => 'fail'], 401);
    });

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100));

    expect($error->kind)->toBe(EsbProductionOrderErrorKind::Authentication)->and($error->isRetryable())->toBeFalse();
});

it('classifies HTTP failures', function (int $status, EsbProductionOrderErrorKind $kind) {
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'fail', 'errors' => [['message' => 'branchID tidak valid']]], $status)]);

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100));

    expect($error->kind)->toBe($kind)->and($error->httpStatus)->toBe($status);
})->with([
    '400 validation' => [400, EsbProductionOrderErrorKind::Validation],
    '422 validation' => [422, EsbProductionOrderErrorKind::Validation],
    '429 retryable' => [429, EsbProductionOrderErrorKind::Retryable],
    '500 retryable' => [500, EsbProductionOrderErrorKind::Retryable],
    '503 retryable' => [503, EsbProductionOrderErrorKind::Retryable],
]);

it('keeps the ESB validation message without leaking the token', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'fail', 'errors' => [['message' => 'branchID tidak valid']]], 400)]);

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100));

    expect($error->getMessage())->toContain('branchID tidak valid')->not->toContain('token-blss');
});

it('classifies a timeout as retryable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100));

    expect($error->kind)->toBe(EsbProductionOrderErrorKind::Retryable);
});

it('classifies a missing company credential as configuration', function () {
    config()->set('esb.core.companies.BLO7', ['username' => '', 'password' => '']);
    Http::fake();

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->listPage('BLO7', 501, '2026-10-01', '2026-10-31', 1, 100));

    expect($error->kind)->toBe(EsbProductionOrderErrorKind::Configuration);
    Http::assertNothingSent();
});

it('rejects malformed successful responses', function (array $payload) {
    Http::fake(['https://esb.test/core/*' => Http::response($payload)]);

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100));

    expect($error->kind)->toBe(EsbProductionOrderErrorKind::Malformed);
})->with([
    'no envelope' => [['data' => []]],
    'no result' => [['status' => 'ok']],
    'no data list' => [['status' => 'ok', 'result' => ['page' => 1]]],
    'row without number' => [esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderNum' => ''])])],
    'invalid date' => [esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderDate' => '2026-02-30'])])],
    'other Branch' => [esbProductionOrderListResponse([esbProductionOrderRow(['branchID' => 999])])],
    'date outside window' => [esbProductionOrderListResponse([esbProductionOrderRow(['productionOrderDate' => '2026-11-02'])])],
]);

it('rejects a detail that belongs to another order, Branch, or has invalid quantities', function (array $payload) {
    Http::fake(['https://esb.test/core/*' => Http::response($payload)]);

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->order('BLSS', 501, 'PO-0001'));

    expect($error->kind)->toBe(EsbProductionOrderErrorKind::Malformed);
})->with([
    'other number' => [esbProductionOrderDetailResponse(['productionOrderNum' => 'PO-0002'])],
    'other Branch' => [esbProductionOrderDetailResponse(['branchID' => 999])],
    'no details list' => [['status' => 'ok', 'result' => esbProductionOrderRow()]],
    'text quantity' => [esbProductionOrderDetailResponse([], [esbProductionOrderDetailRow(['productionOrderQty' => 'banyak'])])],
    'duplicate detail ID' => [esbProductionOrderDetailResponse([], [esbProductionOrderDetailRow(), esbProductionOrderDetailRow()])],
]);

it('classifies an unknown order number as a validation failure', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'fail', 'errors' => [['message' => 'Not found']]], 404)]);

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->order('BLSS', 501, 'PO-404'));

    expect($error->kind)->toBe(EsbProductionOrderErrorKind::Validation);
});

it('encodes the order number in the detail path', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(esbProductionOrderDetailResponse(['productionOrderNum' => 'PO/10 A']))]);

    app(EsbProductionOrderService::class)->order('BLSS', 501, 'PO/10 A');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://esb.test/core/production/production-order/PO%2F10%20A');
});

it('reads companies configured for the global credential with the global ESB account', function () {
    config()->set('kitchen.production_orders.global_credential_companies', ['BLSS']);
    config()->set('esb.core.username', 'global-user');
    config()->set('esb.core.password', 'global-secret');
    Cache::put('esb_core.access_token', 'token-global');
    Http::fake(['https://esb.test/core/*' => Http::response(esbProductionOrderListResponse([]))]);

    app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100);
    app(EsbProductionOrderService::class)->listPage('BLO6', 501, '2026-10-01', '2026-10-31', 1, 100);

    $tokens = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Authorization')[0] ?? '')->all();
    expect($tokens)->toBe(['Bearer token-global', 'Bearer token-blo6']);
});

it('classifies a 403 as missing access, not a retry', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'fail', 'message' => 'You did not have access to this resource', 'errors' => []], 403)]);

    $error = productionOrderError(fn () => app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-01', '2026-10-31', 1, 100));

    expect($error->kind)->toBe(EsbProductionOrderErrorKind::Authentication)
        ->and($error->getMessage())->toContain('tidak mempunyai akses');
});

it('takes the production quantity from productionOrderQty and actor names from fullName', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(esbProductionOrderDetailResponse())]);

    $order = app(EsbProductionOrderService::class)->order('BLSS', 501, 'PO-0001');

    expect($order['details'][0]['quantity'])->toBe('12.5')
        ->and($order['details'][0]['uom_name'])->toBe('Resep@500GR')
        ->and($order['details'][0]['is_finished'])->toBeFalse()
        ->and($order['header']['created_by_name'])->toBe('Pengguna Contoh')
        ->and($order['header']['edited_by_name'])->toBeNull()
        ->and($order['header']['esb_created_at'])->toBe('2026-10-13 08:00:00')
        ->and($order['header']['production_order_date'])->toBe('2026-10-14');
});

it('reads an empty window sent as data null with count zero', function () {
    Http::fake(['https://esb.test/core/*' => Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => 0, 'data' => null, 'prev' => '', 'next' => '']])]);

    $page = app(EsbProductionOrderService::class)->listPage('BLSS', 501, '2026-10-30', '2026-11-10', 1, 100);

    expect($page['orders'])->toBe([])->and($page['has_next'])->toBeFalse();
});
