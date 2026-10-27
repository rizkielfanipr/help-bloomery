<?php

use App\Actions\Rnd\Material\AmendMaterialTechnicalDataAction;
use App\Actions\Rnd\Material\ApproveMaterialForEsbAction;
use App\Actions\Rnd\Material\ClaimMaterialReviewAction;
use App\Actions\Rnd\Material\LinkMaterialToExistingEsbProductAction;
use App\Actions\Rnd\Material\ReconcileMaterialEsbSyncAction;
use App\Actions\Rnd\Material\SubmitMaterialForItReviewAction;
use App\Actions\Rnd\Material\SyncMaterialToEsbAction;
use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialReviewStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\MonitoringTransaction;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialDetailMapper;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    config(['cache.default' => 'array', 'esb.core.base_url' => 'https://core-esb.test', 'esb.core.username' => 'it-user', 'esb.core.password' => 'secret']);
    Cache::put('esb_core.access_token', 'token');
    $this->rnd = User::factory()->create(['is_active' => true]);
    $this->rnd->assignRole('RND_STAFF');
    $this->it = User::factory()->create(['is_active' => true]);
    $this->it->assignRole('IT_STAFF');
    $this->material = RndProductEsbMaterial::factory()->create(['product_code' => 'BBM-GULA', 'sku' => 'BBM-GULA-GR', 'created_by' => $this->rnd->id]);
    $this->material->units()->update(['sku' => 'BBM-GULA-GR']);
});

function claimedMaterial(RndProductEsbMaterial $material, User $rnd, User $it): RndProductEsbMaterial
{
    app(SubmitMaterialForItReviewAction::class)->execute($material, $rnd);
    app(ClaimMaterialReviewAction::class)->execute($material, $it);

    return $material->refresh();
}

/**
 * Fakes ESB Core: no product with the code exists until it is created, then the product reads back
 * with the given details. Like the real `GET /product/{id}`, the read-back body has no `productID`.
 */
function fakeEsbProductCreate(array $details, ?Closure $create = null, array $existing = []): void
{
    $created = false;
    Http::fake(function (Request $request) use (&$created, $details, $create, $existing) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($request->method() === 'POST' && $path === '/product') {
            $created = true;

            return $create ? $create($request) : Http::response(['status' => 'ok', 'result' => ['productID' => 8123, 'isTemp' => false]]);
        }
        if ($path === '/product/list') {
            return Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 20, 'count' => count($existing), 'data' => $existing, 'prev' => null, 'next' => null]]);
        }
        if ($path === '/product/8123') {
            return Http::response(['status' => 'ok', 'result' => $created || $existing !== [] ? ['productCode' => 'BBM-GULA', 'productDetails' => $details] : null]);
        }

        return Http::response(['status' => 'fail'], 404);
    });
}

it('blocks every push without an IT approval for the current version', function () {
    Http::fake();
    $this->material->update(['esb_sync_status' => MaterialEsbSyncStatus::Queued]);

    expect(fn () => app(SyncMaterialToEsbAction::class)->execute($this->material, $this->it))->toThrow(ValidationException::class)
        ->and(fn () => app(SyncMaterialToEsbAction::class)->execute($this->material, $this->rnd))->toThrow(AuthorizationException::class);
    Http::assertNothingSent();
});

it('creates the product once, reads it back, maps every unit, and requests sourcing', function () {
    fakeEsbProductCreate([['productDetailID' => 9123, 'uomID' => 5, 'uomName' => 'GR', 'SKU' => 'BBM-GULA-GR', 'isBase' => true, 'flagActive' => true]]);
    claimedMaterial($this->material, $this->rnd, $this->it);

    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);

    $this->material->refresh();
    expect($this->material->esb_sync_status)->toBe(MaterialEsbSyncStatus::Synced)
        ->and($this->material->status)->toBe('synced')
        ->and($this->material->esb_product_id)->toBe(8123)
        ->and($this->material->esb_verified_at)->not->toBeNull()
        ->and($this->material->units()->sole()->esb_product_detail_id)->toBe(9123)
        ->and($this->material->sourcing_status)->toBe(MaterialSourcingStatus::WaitingPurchasing)
        ->and($this->material->pricing_status)->toBe(MaterialPricingStatus::NotReady)
        ->and(RndMaterialReview::query()->sole()->consumed_at)->not->toBeNull()
        ->and(RndMaterialPricingRequest::query()->count())->toBe(0);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/product')))->toHaveCount(1);
    expect(MonitoringTransaction::query()->parents()->sole()->source_action)->toBe('approved_esb_create');
});

it('refuses a second push after the approval was consumed (double click)', function () {
    fakeEsbProductCreate([['productDetailID' => 9123, 'uomID' => 5, 'sku' => 'BBM-GULA-GR', 'flagActive' => true]]);
    claimedMaterial($this->material, $this->rnd, $this->it);
    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);

    expect(fn () => app(SyncMaterialToEsbAction::class)->execute($this->material->refresh(), $this->it))->toThrow(ValidationException::class);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/product')))->toHaveCount(1);
});

it('routes a synced WIP straight to Finance without sourcing', function () {
    $wip = RndProductEsbMaterial::factory()->wip()->create(['product_code' => 'BW1500', 'sku' => 'BW1500-GR', 'created_by' => $this->rnd->id]);
    $wip->units()->update(['sku' => 'BW1500-GR']);
    $created = false;
    Http::fake(function (Request $request) use (&$created) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($request->method() === 'POST') {
            $created = true;

            return Http::response(['status' => 'ok', 'result' => ['productID' => 8200]]);
        }
        if ($path === '/product/list') {
            return Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 20, 'count' => 0, 'data' => []]]);
        }

        return Http::response(['status' => 'ok', 'result' => ['productID' => 8200, 'productDetails' => [['productDetailID' => 9200, 'uomID' => 5, 'sku' => 'BW1500-GR', 'flagActive' => true]]]]);
    });
    claimedMaterial($wip, $this->rnd, $this->it);

    app(ApproveMaterialForEsbAction::class)->execute($wip, $this->it);

    $wip->refresh();
    $request = RndMaterialPricingRequest::query()->sole();
    expect($wip->sourcing_status)->toBe(MaterialSourcingStatus::NotRequired)
        ->and($wip->sourcing_requested_at)->toBeNull()
        ->and($wip->sourcings()->count())->toBe(0)
        ->and($wip->sampleCycles()->count())->toBe(0)
        ->and($wip->pricing_status)->toBe(MaterialPricingStatus::ReadyForPricing)
        ->and($request->origin)->toBe(RndMaterialPricingRequest::ORIGIN_WIP)
        ->and($request->product_detail_id)->toBe(9200)
        ->and($request->supplier_name)->toBeNull();
});

it('never re-creates a product whose code already exists and sends it to reconciliation', function () {
    fakeEsbProductCreate([['productDetailID' => 9123, 'uomID' => 5, 'sku' => 'BBM-GULA-GR', 'flagActive' => true]], existing: [['productID' => 8123, 'productCode' => 'BBM-GULA', 'productName' => 'Gula']]);
    claimedMaterial($this->material, $this->rnd, $this->it);

    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);

    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::NeedsReconciliation)
        ->and($this->material->esb_product_id)->toBe(8123);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

    app(ReconcileMaterialEsbSyncAction::class)->verify($this->material, $this->it, 8123, 'Produk existing sudah dicek cocok di ESB');
    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::Synced);
});

it('keeps a timed-out create in reconciliation and never retries it automatically', function () {
    fakeEsbProductCreate([], fn () => Http::failedConnection('cURL error 28: Operation timed out'));
    claimedMaterial($this->material, $this->rnd, $this->it);

    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);

    $this->material->refresh();
    expect($this->material->esb_sync_status)->toBe(MaterialEsbSyncStatus::NeedsReconciliation)
        ->and($this->material->status)->toBe('unknown');
    expect(fn () => app(SyncMaterialToEsbAction::class)->execute($this->material, $this->it))->toThrow(ValidationException::class);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST'))->toHaveCount(1);
});

it('marks an explicit ESB rejection as failed and allows a safe retry with the same approval', function () {
    $attempt = 0;
    fakeEsbProductCreate([['productDetailID' => 9123, 'uomID' => 5, 'sku' => 'BBM-GULA-GR', 'flagActive' => true]], function () use (&$attempt) {
        return ++$attempt === 1
            ? Http::response(['status' => 'fail', 'errors' => [['message' => 'Kategori tidak valid']]], 422)
            : Http::response(['status' => 'ok', 'result' => ['productID' => 8123]]);
    });
    claimedMaterial($this->material, $this->rnd, $this->it);

    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);
    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::Failed);

    app(SyncMaterialToEsbAction::class)->execute($this->material, $this->it);
    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::Synced);
});

it('does not mark synced when a unit has no Product Detail in the read-back', function () {
    $this->material->units()->create(['uom_id' => 9, 'uom_name' => 'KG', 'sku' => 'BBM-GULA-KG', 'conversion_factor' => 1000, 'base_price' => 0, 'is_purchase' => true, 'flag_active' => true]);
    fakeEsbProductCreate([['productDetailID' => 9123, 'uomID' => 5, 'sku' => 'BBM-GULA-GR', 'flagActive' => true]]);
    claimedMaterial($this->material, $this->rnd, $this->it);

    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);

    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::NeedsReconciliation)
        ->and($this->material->sync_error)->toContain('KG')
        ->and($this->material->units()->where('uom_id', 9)->sole()->esb_product_detail_id)->toBeNull();
});

it('refuses an approval when the data changed after the review snapshot', function () {
    Http::fake();
    claimedMaterial($this->material, $this->rnd, $this->it);
    $this->material->update(['product_name' => 'Nama diubah diam-diam']);

    expect(fn () => app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it))->toThrow(ValidationException::class);
    expect($this->material->refresh()->review_status)->toBe(MaterialReviewStatus::UnderItReview);
    Http::assertNothingSent();
});

it('refuses an approval for a stale submission version', function () {
    Http::fake();
    claimedMaterial($this->material, $this->rnd, $this->it);

    expect(fn () => app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it, expectedVersion: 99))->toThrow(ValidationException::class);
});

it('links an existing product only when every unit maps, without any mutation', function () {
    Http::fake(['https://core-esb.test/product/7001' => Http::response(['status' => 'ok', 'result' => [
        'productCode' => 'BBM-GULA', 'productDetails' => [['productDetailID' => 7101, 'uomID' => 5, 'uomName' => 'GR', 'SKU' => 'BBM-GULA-GR', 'isBase' => true, 'flagActive' => true]],
    ]])]);
    claimedMaterial($this->material, $this->rnd, $this->it);

    app(LinkMaterialToExistingEsbProductAction::class)->execute($this->material, $this->it, 7001);

    $this->material->refresh();
    expect($this->material->esb_sync_status)->toBe(MaterialEsbSyncStatus::Synced)
        ->and($this->material->esb_product_id)->toBe(7001)
        ->and($this->material->units()->sole()->esb_product_detail_id)->toBe(7101)
        ->and(RndMaterialReview::query()->sole()->decision)->toBe(RndMaterialReview::DECISION_APPROVE_LINK);
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

it('refuses to link a product whose units do not match', function () {
    Http::fake(['https://core-esb.test/product/7001' => Http::response(['status' => 'ok', 'result' => [
        'productID' => 7001, 'productDetails' => [['productDetailID' => 7101, 'uomID' => 2, 'sku' => 'OTHER', 'flagActive' => true]],
    ]])]);
    claimedMaterial($this->material, $this->rnd, $this->it);

    expect(fn () => app(LinkMaterialToExistingEsbProductAction::class)->execute($this->material, $this->it, 7001))->toThrow(ValidationException::class);
    expect($this->material->refresh()->review_status)->toBe(MaterialReviewStatus::UnderItReview);
});

it('updates an already synced material through the Product Detail replacement flow after a new approval', function () {
    $material = RndProductEsbMaterial::factory()->synced(8300, 9301)->create(['product_code' => 'BBM-SAGU', 'sku' => 'BBM-SAGU-GR', 'created_by' => $this->rnd->id]);
    $material->units()->update(['sku' => 'BBM-SAGU-GR']);
    $material->units()->create(['uom_id' => 9, 'uom_name' => 'KG', 'sku' => 'BBM-SAGU-KG', 'esb_product_detail_id' => 9302, 'conversion_factor' => 1000, 'base_price' => 0, 'is_purchase' => true, 'flag_active' => true]);
    $material->update(['review_status' => MaterialReviewStatus::Draft]);
    $material->units()->where('uom_id', 9)->update(['conversion_factor' => 500]);
    $state = 'initial';
    $detail = fn (int $id, int $uom, string $sku, float $qty, bool $active = true): array => ['productDetailID' => $id, 'uomID' => $uom, 'sku' => $sku, 'qty' => $qty, 'flagActive' => $active];
    Http::fake(function (Request $request) use (&$state, $detail) {
        if ($request->method() === 'PUT') {
            $state = $state === 'initial' ? 'deactivated' : 'replaced';

            return Http::response(['status' => 'ok', 'result' => null]);
        }

        return Http::response(['status' => 'ok', 'result' => ['productID' => 8300, 'productDetails' => match ($state) {
            'initial' => [$detail(9301, 5, 'BBM-SAGU-GR', 1), $detail(9302, 9, 'BBM-SAGU-KG', 1000)],
            'deactivated' => [$detail(9301, 5, 'BBM-SAGU-GR', 1), $detail(9302, 9, 'BBM-SAGU-KG', 1000, false)],
            'replaced' => [$detail(9301, 5, 'BBM-SAGU-GR', 1), $detail(9302, 9, 'BBM-SAGU-KG', 1000, false), $detail(9303, 9, 'BBM-SAGU-KG', 500)],
        }]]);
    });
    claimedMaterial($material, $this->rnd, $this->it);

    app(ApproveMaterialForEsbAction::class)->execute($material, $this->it);

    $material->refresh();
    expect($material->esb_sync_status)->toBe(MaterialEsbSyncStatus::Synced)
        ->and($material->units()->where('uom_id', 9)->sole()->esb_product_detail_id)->toBe(9303)
        ->and(Http::recorded(fn (Request $request): bool => $request->method() === 'PUT'))->toHaveCount(2);
});

it('lets IT confirm a not-applied uncertain push so it can be retried with the same approval', function () {
    fakeEsbProductCreate([], fn () => Http::failedConnection('connection reset'));
    claimedMaterial($this->material, $this->rnd, $this->it);
    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);

    app(ReconcileMaterialEsbSyncAction::class)->markNotApplied($this->material->refresh(), $this->it, 'Dicek di ESB: kode belum pernah dibuat');

    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::Failed)
        ->and($this->material->esb_product_id)->toBeNull();
});

it('keeps a resubmit during IT review idempotent instead of opening a new version', function () {
    Http::fake();
    claimedMaterial($this->material, $this->rnd, $this->it);
    app(AmendMaterialTechnicalDataAction::class)->execute($this->material, $this->it, ['sub_category_id' => 30], 'Subkategori diperbaiki IT');

    $review = app(SubmitMaterialForItReviewAction::class)->execute($this->material->refresh(), $this->rnd);

    expect($review->submission_version)->toBe(1)
        ->and(RndMaterialReview::query()->count())->toBe(1)
        ->and($this->material->refresh()->review_status)->toBe(MaterialReviewStatus::UnderItReview);
});

it('verifies an uncertain create by Product ID even though the read-back has no productID', function () {
    fakeEsbProductCreate([['productDetailID' => 9123, 'uomID' => 5, 'SKU' => 'BBM-GULA-GR', 'flagActive' => true]], fn () => Http::failedConnection('cURL error 28: timed out'));
    claimedMaterial($this->material, $this->rnd, $this->it);
    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);
    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::NeedsReconciliation);

    Http::fake(['https://core-esb.test/product/8123' => Http::response(['status' => 'ok', 'result' => [
        'productCode' => 'BBM-GULA', 'productDetails' => [['productDetailID' => 9123, 'uomID' => 5, 'SKU' => 'BBM-GULA-GR', 'flagActive' => true]],
    ]])]);
    app(ReconcileMaterialEsbSyncAction::class)->verify($this->material->refresh(), $this->it, 8123, 'Produk ditemukan di ESB dengan kode yang sama');

    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::Synced)
        ->and($this->material->esb_product_id)->toBe(8123)
        ->and($this->material->units()->sole()->esb_product_detail_id)->toBe(9123);
});

it('never leaves the claim in syncing when recording a successful create fails locally', function () {
    fakeEsbProductCreate([['productDetailID' => 9123, 'uomID' => 5, 'SKU' => 'BBM-GULA-GR', 'flagActive' => true]]);
    claimedMaterial($this->material, $this->rnd, $this->it);
    $this->mock(MaterialDetailMapper::class)->shouldReceive('map')->andThrow(new RuntimeException('Undefined array key'));

    app(ApproveMaterialForEsbAction::class)->execute($this->material, $this->it);

    $this->material->refresh();
    expect($this->material->esb_sync_status)->toBe(MaterialEsbSyncStatus::NeedsReconciliation)
        ->and($this->material->esb_product_id)->toBe(8123)
        ->and($this->material->sync_error)->toContain('Product ID 8123');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST'))->toHaveCount(1);
});

it('lets IT reconcile a stale syncing claim but not one that may still be running', function () {
    claimedMaterial($this->material, $this->rnd, $this->it);
    Http::fake(['https://core-esb.test/product/8123' => Http::response(['status' => 'ok', 'result' => [
        'productCode' => 'BBM-GULA', 'productDetails' => [['productDetailID' => 9123, 'uomID' => 5, 'SKU' => 'BBM-GULA-GR', 'flagActive' => true]],
    ]])]);
    $this->material->forceFill(['esb_sync_status' => MaterialEsbSyncStatus::Syncing, 'status' => 'syncing', 'esb_sync_claimed_at' => now()->subMinutes(2)])->save();

    expect($this->material->needsEsbReconciliation())->toBeFalse()
        ->and(fn () => app(ReconcileMaterialEsbSyncAction::class)->verify($this->material, $this->it, 8123, 'Cek manual klaim yang masih berjalan'))->toThrow(ValidationException::class);

    $this->travel(RndProductEsbMaterial::ESB_CLAIM_STALE_MINUTES)->minutes();
    app(ReconcileMaterialEsbSyncAction::class)->verify($this->material->refresh(), $this->it, 8123, 'Klaim tertahan, produk ada di ESB');

    expect($this->material->refresh()->esb_sync_status)->toBe(MaterialEsbSyncStatus::Synced)
        ->and($this->material->esb_sync_claimed_at)->toBeNull();
});

it('lets IT reconcile a syncing claim right after its ESB transaction finished, prefilled from monitoring', function () {
    claimedMaterial($this->material, $this->rnd, $this->it);
    $this->material->forceFill(['esb_sync_status' => MaterialEsbSyncStatus::Syncing, 'status' => 'syncing', 'esb_sync_claimed_at' => now()])->save();
    expect($this->material->needsEsbReconciliation())->toBeFalse();

    MonitoringTransaction::factory()->create([
        'source_module' => 'rnd_product_release', 'source_action' => 'approved_esb_create', 'operation_type' => 'create_product',
        'external_reference' => '8123', 'idempotency_key' => "material:{$this->material->id}:v1",
        'local_model_type' => $this->material->getMorphClass(), 'local_model_id' => $this->material->id,
    ]);
    expect($this->material->needsEsbReconciliation())->toBeFalse();

    $this->travel(RndProductEsbMaterial::ESB_CLAIM_FINISHED_GRACE_MINUTES)->minutes();
    expect($this->material->needsEsbReconciliation())->toBeTrue()
        ->and($this->material->finishedEsbClaimTransaction()->external_reference)->toBe('8123');
});
