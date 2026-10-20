<?php

use App\Actions\Rnd\Bom\ReconcileBomAdjustmentAction;
use App\Actions\Rnd\Bom\UpdateEsbBillOfMaterialAction;
use App\Enums\MonitoringTransactionStatus;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Filament\Helpdesk\Pages\CreateBomRecipePage;
use App\Models\MonitoringTransaction;
use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    config([
        'cache.default' => 'array',
        'esb.core.base_url' => 'https://core-esb.test',
        'esb.core.username' => 'global-user',
        'esb.core.password' => 'global-password',
    ]);
});

function monitoredBomDetail(array $overrides = []): array
{
    return array_merge([
        'bomID' => 42, 'bomTypeID' => 1, 'bomTypeName' => 'Assembly', 'bomName' => 'Croissant Assembly', 'bomCode' => 'BOM-CRS',
        'productDetailID' => 100, 'productName' => 'Croissant', 'productCode' => 'CRS', 'uomName' => 'PCS', 'bomCostTotal' => 0,
        'notes' => 'Test BOM', 'accessType' => 0, 'editedDate' => '2026-07-30T10:00:00+07:00',
        'bomDetails' => [['ID' => 7, 'productID' => 2, 'productDetailID' => 200, 'productName' => 'Butter', 'productCode' => 'BTR', 'uomName' => 'GRAM', 'qty' => 100, 'lastHpp' => 125, 'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '']],
    ], $overrides);
}

function monitoredBomDraft(): array
{
    return [
        'productDetailID' => 100, 'productName' => 'Croissant', 'productCode' => 'CRS', 'uomName' => 'PCS',
        'bomDetails' => [['ID' => 7, 'productDetailID' => 200, 'productCode' => 'BTR', 'productName' => 'Butter', 'uomName' => 'GRAM', 'lastHPP' => 125, 'qty' => 150, 'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '']],
    ];
}

function fakeMonitoredBom(mixed $bomResponse): void
{
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom/42' => $bomResponse,
    ]);
}

it('records a verified BOM Adjustment update linked to its change log', function () {
    $actor = User::factory()->create();
    fakeMonitoredBom(Http::sequence()
        ->push(['status' => 'ok', 'result' => monitoredBomDetail()])
        ->push(['status' => 'ok', 'result' => null])
        ->push(['status' => 'ok', 'result' => monitoredBomDetail(['editedDate' => '2026-07-30T11:00:00+07:00'])]));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, monitoredBomDraft(), null, 'Naikkan porsi', RndBomChangeLogSource::BomAdjustment, $actor);

    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'PUT'))->toHaveCount(1);
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->source_module)->toBe('bom_adjustment')
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->actor_id)->toBe($actor->id)
        ->and($parent->local_model_id)->toBe($log->id)
        ->and($parent->idempotency_key)->toBe('bom-change-log:'.$log->id)
        ->and($parent->external_reference)->toBe('42')
        ->and($parent->attempts()->sole()->endpoint_template)->toBe('/product/bom/{bomID}')
        ->and($parent->attempts()->sole()->request_summary['bomDetails'][0]['qty'])->toEqual(150);
});

it('maps BOM update outcomes without changing the change log result', function (mixed $mutationResponse, RndBomChangeLogStatus $domain, MonitoringTransactionStatus $monitoring) {
    fakeMonitoredBom(Http::sequence()->push(['status' => 'ok', 'result' => monitoredBomDetail()])->pushResponse($mutationResponse));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, monitoredBomDraft(), null, null, RndBomChangeLogSource::Project, null);

    expect($log->status)->toBe($domain)
        ->and(MonitoringTransaction::query()->parents()->sole()->status)->toBe($monitoring)
        ->and(MonitoringTransaction::query()->parents()->sole()->source_module)->toBe('rnd_bom');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'PUT'))->toHaveCount(1);
})->with([
    'explicit validation error' => [fn () => Http::response(['status' => 'fail', 'errors' => [['message' => 'Quantity tidak valid']]], 422), RndBomChangeLogStatus::Failed, MonitoringTransactionStatus::Failed],
    'connection reset' => [fn () => Http::failedConnection('connection reset'), RndBomChangeLogStatus::NeedsReconciliation, MonitoringTransactionStatus::Unknown],
    'timeout' => [fn () => Http::failedConnection('cURL error 28: Operation timed out'), RndBomChangeLogStatus::NeedsReconciliation, MonitoringTransactionStatus::Unknown],
]);

it('marks a BOM update unknown when the verification refetch fails after a successful PUT', function () {
    fakeMonitoredBom(Http::sequence()
        ->push(['status' => 'ok', 'result' => monitoredBomDetail()])
        ->push(['status' => 'ok', 'result' => null])
        ->pushFailedConnection('connection reset'));

    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, monitoredBomDraft(), null, null, RndBomChangeLogSource::BomAdjustment, null);

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($log->status)->toBe(RndBomChangeLogStatus::NeedsReconciliation)
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Unknown)
        ->and($parent->attempts()->sole()->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and(collect($parent->timeline)->last()['result'])->toBe('not_verifiable');
});

it('mirrors a BOM reconciliation into the monitoring record without re-sending the mutation', function () {
    $applied = false;
    Http::fake(function (Request $request) use (&$applied) {
        if (str_ends_with($request->url(), '/auth/login')) {
            return Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]);
        }
        if ($request->method() === 'PUT') {
            $applied = true;

            return Http::failedConnection('connection reset')($request);
        }

        return Http::response(['status' => 'ok', 'result' => $applied
            ? monitoredBomDetail(['bomDetails' => [['ID' => 7, 'productDetailID' => 200, 'qty' => 150, 'yieldPercent' => 2, 'tolerancePercent' => 0, 'printGroup' => '', 'lastHPP' => 125]]])
            : monitoredBomDetail()]);
    });
    $log = app(UpdateEsbBillOfMaterialAction::class)->execute(42, monitoredBomDraft(), null, null, RndBomChangeLogSource::BomAdjustment, null);
    expect($log->status)->toBe(RndBomChangeLogStatus::NeedsReconciliation);

    $reconciler = User::factory()->create();
    $result = app(ReconcileBomAdjustmentAction::class)->execute($log, $reconciler);

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($result->status)->toBeIn([RndBomChangeLogStatus::Success, RndBomChangeLogStatus::Failed])
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Reconciled)
        ->and($parent->reconciliation_outcome)->toBe($result->status === RndBomChangeLogStatus::Success ? 'confirmed_applied' : 'confirmed_not_applied')
        ->and($parent->reconciled_by)->toBe($reconciler->id);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'PUT'))->toHaveCount(1);
});

it('records a created BOM and blocks resending the same recipe while its result is unknown', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
    $project = RndProject::query()->create(['name' => 'BOM Monitoring', 'start_date' => '2026-07-01', 'end_date' => '2026-08-31', 'created_by' => $admin->id]);
    $product = $project->products()->create(['name' => 'Release', 'status' => 'development', 'created_by' => $admin->id]);

    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom' => Http::failedConnection('cURL error 28: Operation timed out'),
    ]);

    $products = [
        101 => ['productDetailID' => 101, 'productName' => 'Croissant', 'productCode' => 'CRS', 'unit' => 'PCS', 'baseUnit' => 'PCS', 'basePrice' => 0, 'receiptTolerance' => 0],
        202 => ['productDetailID' => 202, 'productName' => 'Butter', 'productCode' => 'BTR', 'unit' => 'GRAM', 'baseUnit' => 'GRAM', 'basePrice' => 100, 'receiptTolerance' => 0],
    ];
    $data = [
        'bomName' => 'Croissant Assembly', 'bomCode' => 'BOM-CRS', 'productDetailID' => 101, 'notes' => 'Test', 'bomCostTotal' => 0,
        'accessType' => 0, 'selectedUserAccess' => [], 'documentMaterials' => [],
        'bomDetails' => [['ID' => 0, 'productDetailID' => 202, 'lastHPP' => 100, 'qty' => 250, 'yieldPercent' => 0, 'printGroup' => '', 'tolerancePercent' => 0]],
    ];
    $submit = fn () => Livewire::test(CreateBomRecipePage::class, ['project' => $project->id, 'product' => $product->id])
        ->set('selectedProducts', $products)
        ->set('data', $data)
        ->call('create');

    expect(fn () => $submit())->toThrow(ConnectionException::class);
    $submit()->assertNotified('Pengiriman BOM sebelumnya belum pasti');

    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/product/bom')))->toHaveCount(1)
        ->and(RndProjectBom::query()->count())->toBe(0);
    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->source_module)->toBe('rnd_bom')
        ->and($parent->status)->toBe(MonitoringTransactionStatus::Unknown);
});

it('records a successful BOM create with its ESB BOM ID and local project BOM', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
    $project = RndProject::query()->create(['name' => 'BOM Monitoring', 'start_date' => '2026-07-01', 'end_date' => '2026-08-31', 'created_by' => $admin->id]);
    $product = $project->products()->create(['name' => 'Release', 'status' => 'development', 'created_by' => $admin->id]);
    Http::fake([
        'https://core-esb.test/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://core-esb.test/product/bom' => Http::response(['status' => 'ok', 'result' => ['bomID' => 424]]),
    ]);

    Livewire::test(CreateBomRecipePage::class, ['project' => $project->id, 'product' => $product->id])
        ->set('selectedProducts', [
            101 => ['productDetailID' => 101, 'productName' => 'Croissant', 'productCode' => 'CRS', 'unit' => 'PCS', 'baseUnit' => 'PCS', 'basePrice' => 0, 'receiptTolerance' => 0],
            202 => ['productDetailID' => 202, 'productName' => 'Butter', 'productCode' => 'BTR', 'unit' => 'GRAM', 'baseUnit' => 'GRAM', 'basePrice' => 100, 'receiptTolerance' => 0],
        ])
        ->set('data', [
            'bomName' => 'Croissant Assembly', 'bomCode' => 'BOM-CRS', 'productDetailID' => 101, 'notes' => 'Test', 'bomCostTotal' => 0,
            'accessType' => 0, 'selectedUserAccess' => [], 'documentMaterials' => [],
            'bomDetails' => [['ID' => 0, 'productDetailID' => 202, 'lastHPP' => 100, 'qty' => 250, 'yieldPercent' => 0, 'printGroup' => '', 'tolerancePercent' => 0]],
        ])
        ->call('create')
        ->assertHasNoErrors();

    $parent = MonitoringTransaction::query()->parents()->sole();
    expect($parent->status)->toBe(MonitoringTransactionStatus::Succeeded)
        ->and($parent->external_reference)->toBe('424')
        ->and($parent->local_model_id)->toBe(RndProjectBom::query()->where('esb_bom_id', 424)->value('id'));
});
