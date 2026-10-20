<?php

use App\Enums\MonitoringTransactionStatus;
use App\Models\MonitoringTransaction;
use App\Models\User;
use App\Services\Monitoring\EsbAttempt;
use App\Services\Monitoring\EsbMutation;
use App\Services\Monitoring\EsbTransactionRecorder;
use App\Services\Monitoring\EsbTransactionScope;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function recorder(): EsbTransactionRecorder
{
    return app(EsbTransactionRecorder::class);
}

function postProduct(string $url = 'https://esb.test/product'): array
{
    $response = Http::asJson()->post($url, ['productName' => 'Tepung', 'password' => 'never-stored']);
    if ($response->failed() || $response->json('status') !== 'ok') {
        throw new RuntimeException('ESB menolak permintaan.');
    }

    return (array) $response->json('result');
}

function productMutation(?string $target = null): EsbMutation
{
    return new EsbMutation('POST', '/product', 'create_product', 'master_product', ['productName' => 'Tepung', 'password' => 'never-stored'], 'BLSS', $target);
}

it('records a parent and one succeeded attempt sharing one correlation id', function () {
    Http::fake(['https://esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 81]])]);
    $user = User::factory()->create();
    $this->actingAs($user);

    $result = recorder()->transaction('rnd_product_release', 'sync_material', 'create_product', function (EsbTransactionScope $scope) {
        return recorder()->attempt(productMutation(), function (EsbAttempt $attempt): array {
            $result = postProduct();
            $attempt->reference($result['productID']);

            return $result;
        });
    }, ['company_code' => 'BLSS', 'idempotency_key' => 'rnd-material:1']);

    expect($result)->toBe(['productID' => 81]);
    Http::assertSentCount(1);

    $parent = MonitoringTransaction::query()->parents()->sole();
    $attempt = $parent->attempts()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->actor_id)->toBe($user->id)
        ->and($parent->external_reference)->toBe('81')
        ->and($parent->attempt_count)->toBe(1)
        ->and($attempt->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($attempt->correlation_id)->toBe($parent->correlation_id)
        ->and($attempt->http_status)->toBe(200)
        ->and($attempt->method)->toBe('POST')
        ->and($attempt->request_summary)->toBe(['productName' => 'Tepung'])
        ->and($attempt->response_summary)->toBe(['status' => 'ok', 'result' => ['productID' => 81]])
        ->and(json_encode($attempt->getAttributes()))->not->toContain('never-stored');
});

it('marks a connection reset after sending as unknown and rethrows the original exception', function () {
    Http::fake(['https://esb.test/product' => Http::failedConnection('connection reset after send')]);

    expect(fn () => recorder()->transaction('bulk_product', 'submit', 'create_product', fn () => recorder()->attempt(productMutation(), fn () => postProduct())))
        ->toThrow(ConnectionException::class);

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Unknown)
        ->and($parent->attempts()->sole()->error_code)->toBe('connection_lost')
        ->and($parent->attempts()->sole()->mutation_sent)->toBeTrue();
    Http::assertSentCount(1);
});

it('marks an explicit ESB rejection as failed', function () {
    Http::fake(['https://esb.test/product' => Http::response(['status' => 'fail', 'message' => 'Product code already registered'], 422)]);

    expect(fn () => recorder()->transaction('bulk_product', 'submit', 'create_product', fn () => recorder()->attempt(productMutation(), fn () => postProduct())))
        ->toThrow(RuntimeException::class, 'ESB menolak permintaan.');

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Failed)
        ->and($parent->attempts()->sole()->error_code)->toBe('http_422')
        ->and($parent->attempts()->sole()->response_summary['message'])->toBe('Product code already registered');
});

it('derives partial from independent targets and keeps every target result', function () {
    Http::fake([
        'https://esb.test/blss/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 1]]),
        'https://esb.test/blo7/product' => Http::response(['status' => 'fail'], 422),
    ]);

    recorder()->transaction('bulk_product', 'submit', 'create_product', function () {
        foreach (['blss' => 'BLSS', 'blo7' => 'BLO7'] as $path => $comcode) {
            try {
                recorder()->attempt(productMutation($comcode), fn () => postProduct("https://esb.test/{$path}/product"));
            } catch (RuntimeException) {
                // the domain keeps per-target results itself
            }
        }
    });

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Partial)
        ->and($parent->attempts->pluck('status', 'target_key')->map->value->all())->toBe(['BLSS' => 'succeeded', 'BLO7' => 'failed']);
});

it('writes nothing when no mutation is attempted', function () {
    $result = recorder()->transaction('goods_receipt', 'submit', 'create_goods_receipt', fn () => 'no ESB call');

    expect($result)->toBe('no ESB call')
        ->and(MonitoringTransaction::query()->count())->toBe(0);
});

it('keeps the monitoring evidence when the domain database transaction rolls back', function () {
    Http::fake(['https://esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 5]])]);

    expect(fn () => recorder()->transaction('rnd_product_release', 'update_material', 'update_product', function () {
        DB::transaction(function () {
            recorder()->attempt(productMutation(), fn () => postProduct());

            throw new RuntimeException('Local update failed after ESB accepted the mutation.');
        });
    }))->toThrow(RuntimeException::class);

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Unknown)
        ->and($parent->error_code)->toBe('post_mutation_failure')
        ->and($parent->attempts()->sole()->status)->toBe(MonitoringTransactionStatus::Succeeded);
});

it('never fails or repeats the business mutation when monitoring storage is unavailable', function () {
    Http::fake(['https://esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 9]])]);
    Schema::drop('monitoring_transactions');

    $result = recorder()->transaction('bulk_product', 'submit', 'create_product', fn () => recorder()->attempt(productMutation(), fn () => postProduct()));

    expect($result)->toBe(['productID' => 9]);
    Http::assertSentCount(1);
});

it('records an implicit system parent for attempts outside a user action', function () {
    Http::fake(['https://esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 3]])]);

    recorder()->attempt(productMutation(), fn () => postProduct());

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->source_module)->toBe('system')
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->attempts)->toHaveCount(1);
});

it('appends retry attempts to the original parent and keeps the failed attempt', function () {
    Http::fakeSequence('https://esb.test/blo7/product')
        ->push(['status' => 'fail'], 422)
        ->push(['status' => 'ok', 'result' => ['productID' => 2]]);
    Http::fake(['https://esb.test/blss/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 1]])]);

    recorder()->transaction('bulk_product', 'submit', 'create_product', function () {
        recorder()->attempt(productMutation('BLSS'), fn () => postProduct('https://esb.test/blss/product'));
        try {
            recorder()->attempt(productMutation('BLO7'), fn () => postProduct('https://esb.test/blo7/product'));
        } catch (RuntimeException) {
        }
    });
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Partial);

    recorder()->resume($parent, fn () => recorder()->attempt(productMutation('BLO7'), fn () => postProduct('https://esb.test/blo7/product')));

    $parent->refresh();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->attempts)->toHaveCount(3)
        ->and($parent->attempts->where('target_key', 'BLO7')->pluck('attempt_count')->all())->toBe([1, 2])
        ->and($parent->attempts->where('target_key', 'BLO7')->first()->status)->toBe(MonitoringTransactionStatus::Failed);
});

it('lets the domain declare an unverified outcome', function () {
    Http::fake(['https://esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 4]])]);

    recorder()->transaction('bom_adjustment', 'update_bom', 'update_bom', function (EsbTransactionScope $scope) {
        recorder()->attempt(productMutation(), fn () => postProduct());
        $scope->timeline('verify_after_update', 'failed')->markUnknown('Verifikasi BOM setelah update gagal.');
    });

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Unknown)
        ->and($parent->safe_error_message)->toBe('Verifikasi BOM setelah update gagal.')
        ->and($parent->timeline[0]['step'])->toBe('verify_after_update');
});

it('passes through without recording when transaction monitoring is disabled', function () {
    config()->set('monitoring.esb_transactions.enabled', false);
    Http::fake(['https://esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 6]])]);

    $result = recorder()->transaction('bulk_product', 'submit', 'create_product', fn () => recorder()->attempt(productMutation(), fn () => postProduct()));

    expect($result)->toBe(['productID' => 6])
        ->and(MonitoringTransaction::query()->count())->toBe(0);
});

it('reconciles an unknown parent once and keeps its attempts', function () {
    $parent = MonitoringTransaction::factory()->status(MonitoringTransactionStatus::Unknown)->create();
    $attempt = MonitoringTransaction::factory()->attemptOf($parent)->status(MonitoringTransactionStatus::Unknown)->create();
    $user = User::factory()->create();

    expect(recorder()->reconcile($parent, 'confirmed_applied', 'Sudah dicek di ESB', $user))->toBeTrue()
        ->and(recorder()->reconcile($parent->refresh(), 'confirmed_applied', null, $user))->toBeFalse();

    expect($parent->status)->toBe(MonitoringTransactionStatus::Reconciled)
        ->and($parent->reconciled_by)->toBe($user->id)
        ->and($attempt->refresh()->status)->toBe(MonitoringTransactionStatus::Reconciled);
});
