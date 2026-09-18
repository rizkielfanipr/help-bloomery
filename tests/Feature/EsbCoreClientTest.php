<?php

use App\Services\Esb\EsbCompanyProductService;
use App\Services\Esb\EsbCoreClient;
use App\Services\Esb\EsbCoreService;
use App\Services\Esb\EsbItemJournalService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'user-a', 'password' => 'password-a']);
    config()->set('esb.core.companies.BLO6', ['username' => 'user-b', 'password' => 'password-b']);
    Cache::flush();
    Http::preventStrayRequests();
});

it('shares company authentication across services and isolates other companies', function () {
    Http::fake([
        '*/auth/login' => Http::sequence()->push(['status' => 'ok', 'result' => ['accessToken' => 'token-a']])->push(['status' => 'ok', 'result' => ['accessToken' => 'token-b']]),
        '*/branch' => Http::response(['status' => 'ok', 'result' => []]),
        '*/product/list*' => Http::response(['status' => 'ok', 'result' => ['data' => [], 'count' => 0, 'next' => '']]),
    ]);
    app(EsbItemJournalService::class)->branches('BLSS');
    app(EsbCompanyProductService::class)->taxonomy('BLSS');
    app(EsbItemJournalService::class)->branches('BLO6');
    Http::assertSentCount(5);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/product/list') && $request->hasHeader('Authorization', 'Bearer token-a'));
    expect(Cache::get('esb_core.access_token.BLSS'))->toBe('token-a')->and(Cache::get('esb_core.access_token.BLO6'))->toBe('token-b');
});

it('bounds unauthorized multipart refresh to one retry', function () {
    Cache::put('esb_core.access_token.BLSS', 'expired');
    Http::fake([
        '*/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh']]),
        '*/attachment' => Http::response(['status' => 'fail', 'message' => 'Unauthorized'], 401),
    ]);
    expect(fn () => app(EsbItemJournalService::class)->uploadAttachments('BLSS', 'IJ001', [['name' => 'photo.jpg', 'contents' => 'image', 'mime' => 'image/jpeg']]))->toThrow(RuntimeException::class);
    Http::assertSentCount(3);
});

it('does not retry an uncertain mutation after a connection failure', function () {
    Cache::put('esb_core.access_token.BLSS', 'token');
    Http::fake(['*' => Http::failedConnection()]);
    expect(fn () => app(EsbCoreClient::class)->request('BLSS', 'post', '/inventory/item-journal', []))->toThrow(ConnectionException::class);
    Http::assertSentCount(1);
});

it('does not cache partial paginated master data', function (string $service, string $method, string $path, string $key) {
    Cache::put('esb_core.access_token.BLSS', 'token');
    Http::fake([$path => Http::sequence()
        ->push(['status' => 'ok', 'result' => ['data' => [['purposeID' => 1, 'categoryID' => 1]], 'next' => 'page=2', 'count' => 101]])
        ->push(['status' => 'ok', 'result' => ['data' => [], 'next' => 'page=3', 'count' => 101]])]);
    expect(fn () => app($service)->{$method}('BLSS'))->toThrow(RuntimeException::class);
    expect(Cache::has($key))->toBeFalse();
    Http::assertSentCount(2);
})->with([
    [EsbItemJournalService::class, 'purposes', '*/purpose*', 'esb_item_journal.purposes.BLSS'],
    [EsbCompanyProductService::class, 'taxonomy', '*/product/list*', 'esb_core.product_taxonomy.v3.BLSS'],
]);

it('rejects failed login envelopes even if they contain a token', function () {
    Http::fake(['*/auth/login' => Http::response(['status' => 'fail', 'message' => 'Invalid credentials', 'result' => ['accessToken' => 'invalid']])]);
    expect(fn () => app(EsbCoreClient::class)->request('BLSS', 'get', '/branch'))->toThrow(RuntimeException::class);
    expect(Cache::has('esb_core.access_token.BLSS'))->toBeFalse();
    Http::assertSentCount(1);
});

it('collects default Core taxonomy through the shared client without caching a failed page', function () {
    config()->set('esb.core.username', 'default-user');
    config()->set('esb.core.password', 'default-secret');
    Cache::put('esb_core.access_token', 'default-token');
    Http::fake(['*/product/list*' => Http::sequence()
        ->push(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => 101, 'prev' => '', 'next' => 'page=2', 'data' => [['categoryID' => 1, 'categoryName' => 'Bahan Baku']]]])
        ->push(['status' => 'fail', 'message' => 'Service unavailable'], 503)]);
    expect(fn () => app(EsbCoreService::class)->getProductTaxonomy())->toThrow(RuntimeException::class);
    expect(Cache::has('esb_core.product_taxonomy'))->toBeFalse();
    Http::assertSentCount(2);
});
