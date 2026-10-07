<?php

use App\Actions\Rnd\Bom\UpdateEsbBillOfMaterialAction;
use App\Enums\RndBomChangeLogStatus;
use App\Models\RndBomChangeLog;
use App\Services\EsbBillOfMaterialService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function bomUnitsWorkbook(): string
{
    $path = tempnam(sys_get_temp_dir(), 'bomunits').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Flag & Nonaktif Unit Lama');
    $writer->addRow(Row::fromValues(['Product Code', 'Product Detail ID Lama', 'Product Detail ID Baru']));
    $writer->addRow(Row::fromValues(['BW1', 100, 900]));
    $writer->addRow(Row::fromValues(['BW2', 200, 901]));
    $writer->close();

    return $path;
}

function bomDetail(int $id, int $resultDetailId, array $componentIds, array $substitution = []): array
{
    return [
        'bomID' => $id, 'bomName' => "BOM {$id}", 'bomTypeID' => 1, 'bomTypeName' => 'Assembly', 'productDetailID' => $resultDetailId,
        'editedDate' => '2026-01-01T00:00:00+07:00',
        'bomDetails' => array_map(fn (int $detailId, int $index): array => [
            'ID' => $id * 10 + $index, 'productDetailID' => $detailId, 'lastHpp' => 5, 'qty' => 2, 'yieldPercent' => 0,
            'tolerancePercent' => 0, 'printGroup' => '', 'subtitution' => $substitution,
        ], $componentIds, array_keys($componentIds)),
    ];
}

it('finds BOMs that use old units as result or component and leaves the others alone', function () {
    $details = [
        1 => bomDetail(1, 100, [5]),
        2 => bomDetail(2, 50, [200, 6]),
        3 => bomDetail(3, 50, [6]),
        4 => bomDetail(4, 50, [6], [['productDetailID' => 100]]),
    ];

    $service = Mockery::mock(EsbBillOfMaterialService::class);
    $service->shouldReceive('getAllBillOfMaterials')->andReturn(array_map(fn ($id) => ['bomID' => $id, 'bomName' => "BOM {$id}"], array_keys($details)));
    $service->shouldReceive('getBillOfMaterial')->andReturnUsing(fn (int $id) => $details[$id]);
    app()->instance(EsbBillOfMaterialService::class, $service);

    $this->artisan('esb:replace-bom-units', ['file' => bomUnitsWorkbook(), '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('would_update: 2')
        ->assertSuccessful();
});

it('sends the new product detail ids through the BOM update action when executing', function () {
    $service = Mockery::mock(EsbBillOfMaterialService::class);
    $service->shouldReceive('getAllBillOfMaterials')->andReturn([['bomID' => 1, 'bomName' => 'BOM 1']]);
    $service->shouldReceive('getBillOfMaterial')->andReturn(bomDetail(1, 100, [200, 6]));
    app()->instance(EsbBillOfMaterialService::class, $service);

    $log = new RndBomChangeLog(['status' => RndBomChangeLogStatus::Success]);
    $action = Mockery::mock(UpdateEsbBillOfMaterialAction::class);
    $action->shouldReceive('execute')->once()->withArgs(function (int $bomId, array $draft): bool {
        return $bomId === 1
            && $draft['productDetailID'] === 900
            && array_column($draft['bomDetails'], 'productDetailID') === [901, 6]
            && array_column($draft['bomDetails'], 'qty') === [2.0, 2.0];
    })->andReturn($log);
    app()->instance(UpdateEsbBillOfMaterialAction::class, $action);

    $this->artisan('esb:replace-bom-units', ['file' => bomUnitsWorkbook(), '--execute' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('updated: 1')
        ->assertSuccessful();
});

it('reads BOMs through the chosen company instead of the default account', function () {
    Cache::flush();
    config()->set([
        'esb.core.base_url' => 'https://esb.test/core',
        'esb.core.companies.BLO6' => ['username' => 'u', 'password' => 'p'],
    ]);
    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/product/bom/1' => Http::response(['status' => 'ok', 'result' => bomDetail(1, 100, [5])]),
        '*/product/bom*' => Http::response(['status' => 'ok', 'result' => ['page' => 1, 'limit' => 100, 'count' => 1, 'data' => [['bomID' => 1, 'bomName' => 'BOM 1']]]]),
    ]);

    $this->artisan('esb:replace-bom-units', ['file' => bomUnitsWorkbook(), '--company' => 'BLO6', '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('DRY RUN BLO6')
        ->expectsOutputToContain('would_update: 1')
        ->assertSuccessful();
});
