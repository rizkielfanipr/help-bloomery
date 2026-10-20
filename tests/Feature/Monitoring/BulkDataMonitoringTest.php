<?php

use App\Actions\SubmitBulkProductAction;
use App\Enums\MonitoringTransactionStatus;
use App\Models\BulkProductSubmission;
use App\Models\MonitoringTransaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Cache::flush();
    config([
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.companies.BLSS' => ['username' => 'blss-user', 'password' => 'blss-secret'],
        'esb.core.companies.BLO7' => ['username' => 'blo7-user', 'password' => 'blo7-secret'],
    ]);
});

function monitoredBulkPayload(float $qty = 1000): array
{
    return [
        'categoryID' => 10, 'subCategoryID' => 11, 'productCode' => 'PRD-MON', 'productName' => 'Produk Monitoring',
        'productDetails' => [
            ['uomID' => 5, 'basePrice' => 1, 'sku' => 'PRD-MON-GR', 'qty' => 1, 'isBase' => true, 'isStock' => true, 'flagActive' => true, 'productDetailIDs' => ['BLSS' => 601, 'BLO7' => 701]],
            ['uomID' => 9, 'basePrice' => 1, 'sku' => 'PRD-MON-KG', 'qty' => $qty, 'isBase' => false, 'isStock' => false, 'flagActive' => true, 'productDetailIDs' => ['BLSS' => 602, 'BLO7' => 702]],
        ],
    ];
}

function fakeBulkCreate(Closure $productResponder): void
{
    Http::fake(function (Request $request) use ($productResponder) {
        if ($request->url() === 'https://core-esb.test/auth/login') {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => $request['username'].'-token']]);
        }

        return $productResponder($request, $request->hasHeader('Authorization', 'Bearer blss-user-token') ? 'BLSS' : 'BLO7');
    });
}

it('records one parent per submission with one attempt per comcode and a partial result', function () {
    fakeBulkCreate(fn (Request $request, string $comcode) => $comcode === 'BLSS'
        ? Http::response(['status' => 'ok', 'result' => ['productID' => 101]])
        : Http::response(['status' => 'fail', 'message' => 'Product code already registered'], 422));
    $submission = BulkProductSubmission::factory()->create(['target_comcodes' => ['BLSS', 'BLO7'], 'payload' => monitoredBulkPayload()]);

    app(SubmitBulkProductAction::class)->execute($submission);

    expect($submission->refresh()->status)->toBe('partial');
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Partial)
        ->and($parent->source_module)->toBe('bulk_product')
        ->and($parent->local_model_id)->toBe($submission->id)
        ->and($parent->attempts->mapWithKeys(fn ($attempt) => [$attempt->company_code => $attempt->status->value])->all())
        ->toEqualCanonicalizing(['BLSS' => 'succeeded', 'BLO7' => 'failed']);
});

it('retries only the failed comcode and appends the attempt to the same parent', function () {
    $blo7Calls = 0;
    fakeBulkCreate(function (Request $request, string $comcode) use (&$blo7Calls) {
        if ($comcode === 'BLSS') {
            return Http::response(['status' => 'ok', 'result' => ['productID' => 101]]);
        }

        return ++$blo7Calls === 1
            ? Http::response(['status' => 'fail', 'message' => 'Temporary validation error'], 422)
            : Http::response(['status' => 'ok', 'result' => ['productID' => 202]]);
    });
    $submission = BulkProductSubmission::factory()->create(['target_comcodes' => ['BLSS', 'BLO7'], 'payload' => monitoredBulkPayload()]);
    $action = app(SubmitBulkProductAction::class);
    $action->execute($submission);

    $action->retry($submission->items()->where('comcode', 'BLO7')->sole());

    expect($submission->refresh()->status)->toBe('succeeded');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/product'))->count())->toBe(3);
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->attempts)->toHaveCount(3)
        ->and($parent->attempts->where('company_code', 'BLO7')->pluck('attempt_count')->all())->toBe([1, 2]);
});

it('keeps an uncertain comcode unknown and never sends it again', function () {
    fakeBulkCreate(fn () => Http::failedConnection('connection reset after send'));
    $submission = BulkProductSubmission::factory()->create(['target_comcodes' => ['BLSS'], 'payload' => monitoredBulkPayload()]);
    $action = app(SubmitBulkProductAction::class);

    $action->execute($submission);
    $action->execute($submission->refresh());
    expect(fn () => $action->retry($submission->items()->sole()))->toThrow(HttpException::class);

    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/product'))->count())->toBe(1);
    expect(MonitoringTransaction::query()->parents()->sole()->status)->toBe(MonitoringTransactionStatus::Unknown);
});

it('replaces a changed conversion factor instead of changing the existing Product Detail ID', function () {
    $detail = fn (int $id, int $uomId, float $qty, bool $active = true): array => ['productDetailID' => $id, 'uomID' => $uomId, 'qty' => $qty, 'sku' => 'SKU-'.$id, 'isBase' => $uomId === 5, 'isStock' => $uomId === 5, 'flagActive' => $active];
    $state = 'initial';
    Http::fake(function (Request $request) use (&$state, $detail) {
        if ($request->url() === 'https://core-esb.test/auth/login') {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'blss-token']]);
        }
        if ($request->method() === 'PUT') {
            $state = $state === 'initial' ? 'deactivated' : 'replaced';

            return Http::response(['status' => 'ok', 'result' => null]);
        }

        return Http::response(['status' => 'ok', 'result' => ['productID' => 501, 'productDetails' => match ($state) {
            'initial' => [$detail(601, 5, 1), $detail(602, 9, 1000)],
            'deactivated' => [$detail(601, 5, 1), $detail(602, 9, 1000, false)],
            'replaced' => [$detail(601, 5, 1), $detail(602, 9, 1000, false), $detail(603, 9, 500)],
        }]]);
    });
    $submission = BulkProductSubmission::factory()->create([
        'operation' => 'update', 'target_comcodes' => ['BLSS'], 'remote_product_ids' => ['BLSS' => 501], 'payload' => monitoredBulkPayload(500),
    ]);

    app(SubmitBulkProductAction::class)->execute($submission);

    expect($submission->refresh()->status)->toBe('succeeded');
    $puts = Http::recorded(fn (Request $request): bool => $request->method() === 'PUT')->values();
    expect($puts)->toHaveCount(2);
    $deactivation = collect($puts[0][0]->data()['productDetails'])->firstWhere('productDetailID', 602);
    expect($deactivation['flagActive'])->toBeFalse()
        ->and((float) $deactivation['qty'])->toBe(1000.0);
    $replacementRows = collect($puts[1][0]->data()['productDetails']);
    expect($replacementRows->contains(fn (array $row): bool => ($row['productDetailID'] ?? null) === 602 && (float) $row['qty'] === 500.0))->toBeFalse()
        ->and($replacementRows->contains(fn (array $row): bool => ! isset($row['productDetailID']) && (float) $row['qty'] === 500.0))->toBeTrue();

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->attempts->pluck('target_key')->all())->toBe(['BLSS:deactivate_product_detail', 'BLSS:create_replacement_detail'])
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Succeeded);
});
