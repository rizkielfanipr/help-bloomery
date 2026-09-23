<?php

use App\Services\EsbCoreClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'user', 'password' => 'secret']);
});

it('uses a cached company token without logging in again', function () {
    Cache::put('esb_core.access_token.BLSS', 'cached-token');
    Http::fake([
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => []]),
    ]);

    $response = app(EsbCoreClient::class)->request('blss', 'get', '/branch');

    expect($response->successful())->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer cached-token'));
});

it('logs in and caches a company token when none exists', function () {
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
        'https://esb.test/core/branch' => Http::response(['status' => 'ok', 'result' => []]),
    ]);

    app(EsbCoreClient::class)->request('BLSS', 'get', '/branch');

    expect(Cache::get('esb_core.access_token.BLSS'))->toBe('fresh-token');
    Http::assertSentCount(2);
});

it('refreshes the token once after an unauthorized response', function () {
    $loginCount = 0;
    $branchCount = 0;
    Http::fake(function (Request $request) use (&$loginCount, &$branchCount) {
        if (str_ends_with($request->url(), '/auth/login')) {
            $loginCount++;

            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token-'.$loginCount]]);
        }

        $branchCount++;

        return $branchCount === 1
            ? Http::response(['status' => 'fail', 'message' => 'Unauthorized'], 401)
            : Http::response(['status' => 'ok', 'result' => []]);
    });

    $response = app(EsbCoreClient::class)->request('BLSS', 'get', '/branch');

    expect($response->successful())->toBeTrue()
        ->and($loginCount)->toBe(2)
        ->and($branchCount)->toBe(2)
        ->and(Cache::get('esb_core.access_token.BLSS'))->toBe('token-2');
});

it('rejects an unconfigured company before sending a request', function () {
    config()->set('esb.core.companies.BLSS', ['username' => null, 'password' => null]);
    Http::fake();

    expect(fn () => app(EsbCoreClient::class)->request('BLSS', 'get', '/branch'))
        ->toThrow(RuntimeException::class, 'Credential ESB Core BLSS belum dikonfigurasi.');

    Http::assertNothingSent();
});

it('adds the company and endpoint context to an ESB failure', function () {
    Cache::put('esb_core.access_token.BLSS', 'token');
    Http::fake([
        'https://esb.test/core/purpose*' => Http::response([
            'status' => 'fail', 'message' => 'Purpose tidak tersedia',
        ], 422),
    ]);
    $client = app(EsbCoreClient::class);
    $response = $client->request('BLSS', 'get', '/purpose');

    expect(fn () => $client->successfulResult($response, 'mengambil purpose', 'BLSS', '/purpose'))
        ->toThrow(RuntimeException::class, 'Gagal mengambil purpose [BLSS /purpose]: Purpose tidak tersedia');
});

it('converts a connection failure into an error with request context', function () {
    Cache::put('esb_core.access_token.BLSS', 'token');
    Http::fake([
        'https://esb.test/core/branch' => Http::failedConnection('origin unavailable'),
    ]);

    expect(fn () => app(EsbCoreClient::class)->request('BLSS', 'get', '/branch'))
        ->toThrow(RuntimeException::class, 'Gagal menghubungi ESB Core [BLSS /branch]');
});

it('rebuilds multipart attachments after refreshing an unauthorized token', function () {
    $loginCount = 0;
    $uploadCount = 0;
    Http::fake(function (Request $request) use (&$loginCount, &$uploadCount) {
        if (str_ends_with($request->url(), '/auth/login')) {
            $loginCount++;

            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token-'.$loginCount]]);
        }

        $uploadCount++;

        return $uploadCount === 1
            ? Http::response(['status' => 'fail', 'message' => 'Unauthorized'], 401)
            : Http::response(['status' => 'ok', 'result' => ['urls' => ['https://files.test/qc.jpg']]]);
    });

    $response = app(EsbCoreClient::class)->multipart('BLSS', 'patch', '/inventory/item-journal/IU-1/attachment', [
        ['name' => 'qc.jpg', 'contents' => 'image', 'mime' => 'image/jpeg'],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($loginCount)->toBe(2)
        ->and($uploadCount)->toBe(2);
});
