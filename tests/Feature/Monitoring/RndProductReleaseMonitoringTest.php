<?php

use App\Enums\MonitoringTransactionStatus;
use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Models\MonitoringTransaction;
use App\Models\RndProductEsbMaterial;
use App\Models\RndProject;
use App\Models\User;
use App\Services\EsbMasterProductService;
use App\Services\Monitoring\EsbTransactionRecorder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
    Cache::flush();
    config()->set([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'rnd-test',
        'esb.core.password' => 'secret',
    ]);

    $project = RndProject::query()->create(['name' => 'Monitoring Project', 'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'created_by' => $admin->id]);
    $this->product = $project->products()->create(['name' => 'Monitoring Release', 'status' => 'development', 'created_by' => $admin->id]);
    $this->material = $this->product->esbMaterials()->create([
        'category_id' => 11, 'sub_category_id' => 21, 'uom_id' => 5, 'uom_name' => 'GR',
        'product_code' => 'BBMK-MON', 'product_name' => 'Tepung Monitoring', 'sku' => 'BBMK-MON-GR',
        'conversion_factor' => 1, 'base_price' => 100, 'status' => 'draft', 'created_by' => $admin->id,
    ]);
    $this->material->units()->create([
        'uom_id' => 5, 'uom_name' => 'GR', 'sku' => 'BBMK-MON-GR', 'conversion_factor' => 1, 'base_price' => 100,
        'is_base' => true, 'is_stock' => true, 'is_purchase' => true, 'is_transfer' => true, 'is_sales' => true, 'flag_active' => true,
    ]);
});

function emptyProductList(): array
{
    return ['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => 0, 'data' => [], 'prev' => null, 'next' => null]];
}

function syncMaterial(RndProductEsbMaterial $material): void
{
    Livewire::test(ViewProjectProductPage::class, [
        'project' => $material->product->rnd_project_id,
        'product' => $material->rnd_project_product_id,
    ])->call('syncEsbMaterial', $material->id);
}

it('records a created Master Product with its local material and Product ID without extra requests', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response(emptyProductList()),
        'https://core-esb.test/product' => Http::response(['status' => 'ok', 'result' => ['productID' => 8123, 'isTemp' => false]]),
        'https://core-esb.test/product/8123' => Http::response(['status' => 'ok', 'result' => ['productID' => 8123, 'productDetails' => []]]),
    ]);

    syncMaterial($this->material);

    expect($this->material->refresh()->status)->toBe('synced');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/product')))->toHaveCount(1);
    Http::assertSentCount(4);

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->source_module)->toBe('rnd_product_release')
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->local_model_type)->toBe($this->material->getMorphClass())
        ->and($parent->local_model_id)->toBe($this->material->id)
        ->and($parent->external_reference)->toBe('8123')
        ->and($parent->idempotency_key)->toBe("rnd-material:{$this->material->id}:create")
        ->and($parent->attempts()->sole()->operation_type)->toBe('create_product');
});

it('records a timed out Master Product create as unknown and never resends it', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response(emptyProductList()),
        'https://core-esb.test/product' => Http::failedConnection('cURL error 28: Operation timed out after 60000 milliseconds'),
    ]);

    syncMaterial($this->material);
    syncMaterial($this->material->refresh());

    expect($this->material->refresh()->status)->toBe('unknown');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/product')))->toHaveCount(1);

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Unknown)
        ->and($parent->attempts()->sole()->error_code)->toBe('connection_lost');
});

it('records an explicit Master Product rejection as failed', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response(emptyProductList()),
        'https://core-esb.test/product' => Http::response(['status' => 'fail', 'errors' => [['message' => 'Product code already registered']]], 422),
    ]);

    syncMaterial($this->material);

    expect($this->material->refresh()->status)->toBe('failed');
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Failed)
        ->and($parent->safe_error_message)->toContain('Product code already registered');
});

it('records nothing when an existing ESB product is only linked', function () {
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/list*' => Http::response(['status' => 'ok', 'result' => [
            'page' => 1, 'limit' => 100, 'count' => 1, 'prev' => null, 'next' => null,
            'data' => [['productID' => 77, 'productName' => 'Tepung Monitoring', 'productDetails' => []]],
        ]]),
    ]);

    syncMaterial($this->material);

    expect($this->material->refresh()->status)->toBe('synced')
        ->and(MonitoringTransaction::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET' && ! str_contains($request->url(), 'auth/login'));
});

it('records the two-phase Product Detail replacement as one parent with two attempts and verification steps', function () {
    Cache::put('esb_core.access_token', 'token');
    $oldDetail = ['productDetailID' => 101, 'uomID' => 9, 'qty' => 61.53, 'SKU' => 'BW1398-PCS', 'isBase' => false, 'isStock' => true, 'flagActive' => true];
    $inactiveDetail = array_merge($oldDetail, ['isStock' => false, 'flagActive' => false]);
    $newDetail = array_merge($oldDetail, ['productDetailID' => 202, 'qty' => 60]);
    Http::fake([
        'https://core-esb.test/product/81' => Http::sequence()
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => ['productID' => 81, 'productDetails' => [$inactiveDetail]]])
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => ['productID' => 81, 'productDetails' => [$inactiveDetail, $newDetail]]]),
    ]);
    $payload = ['productCode' => 'BW1398', 'productName' => 'WIP Test', 'productDetails' => [array_merge($oldDetail, ['qty' => 60, 'sku' => 'BW1398-PCS'])]];

    app(EsbTransactionRecorder::class)->transaction('rnd_product_release', 'update_material', 'update_product', fn () => app(EsbMasterProductService::class)->updateProductWithUnitReplacements(81, $payload, ['productID' => 81, 'productDetails' => [$oldDetail]]));

    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'PUT'))->toHaveCount(2);
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->attempts->pluck('operation_type')->all())->toBe(['deactivate_product_detail', 'create_replacement_detail'])
        ->and(collect($parent->timeline)->pluck('result', 'step')->all())->toBe(['verify_deactivation' => 'verified', 'verify_replacement' => 'verified']);
});

it('marks the replacement unknown when the replacement cannot be verified after it was sent', function () {
    Cache::put('esb_core.access_token', 'token');
    $oldDetail = ['productDetailID' => 101, 'uomID' => 9, 'qty' => 61.53, 'flagActive' => true];
    $inactiveDetail = array_merge($oldDetail, ['flagActive' => false]);
    Http::fake([
        'https://core-esb.test/product/81' => Http::sequence()
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => ['productID' => 81, 'productDetails' => [$inactiveDetail]]])
            ->push(['status' => 'ok', 'result' => null])
            ->push(['status' => 'ok', 'result' => ['productID' => 81, 'productDetails' => [$inactiveDetail]]]),
    ]);
    $payload = ['productDetails' => [array_merge($oldDetail, ['qty' => 60])]];

    expect(fn () => app(EsbTransactionRecorder::class)->transaction('rnd_product_release', 'update_material', 'update_product', fn () => app(EsbMasterProductService::class)->updateProductWithUnitReplacements(81, $payload, ['productID' => 81, 'productDetails' => [$oldDetail]])))
        ->toThrow(RuntimeException::class, 'ESB belum mengembalikan Product Detail baru');

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Unknown)
        ->and($parent->error_code)->toBe('post_mutation_failure')
        ->and(collect($parent->timeline)->last()['result'])->toBe('missing_replacement');
});
