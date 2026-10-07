<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function unitsWorkbook(array $names): string
{
    $path = tempnam(sys_get_temp_dir(), 'units').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Product Code', 'Unit UOM']));
    foreach ($names as $index => $name) {
        $writer->addRow(Row::fromValues(["BW{$index}", $name]));
    }
    $writer->close();

    return $path;
}

beforeEach(function () {
    Cache::flush();
    config()->set([
        'esb.core.base_url' => 'https://esb.test/core',
        'esb.core.companies.BLSS' => ['username' => 'u', 'password' => 'p'],
    ]);

    Http::fake([
        '*/auth/login' => Http::response(['result' => ['accessToken' => 'tok']]),
        '*/units*' => fn ($request) => $request->method() === 'POST'
            ? Http::response(['status' => 'ok'])
            : Http::response(['status' => 'ok', 'result' => ['count' => 4, 'data' => [
                ['uomID' => 5, 'uomName' => 'GR', 'metricID' => 2],
                ['uomID' => 6, 'uomName' => 'ML', 'metricID' => 3],
                ['uomID' => 2, 'uomName' => 'PCS', 'metricID' => 1],
                ['uomID' => 90, 'uomName' => 'Resep@100GR', 'metricID' => 2],
            ]]]),
    ]);
});

it('creates only missing units, deduplicated, with the metric of the base unit', function (bool $execute) {
    $this->artisan('esb:create-units', array_filter([
        'file' => unitsWorkbook(['Resep@100GR', 'Resep@200GR', 'Resep@200GR', 'PACK@3PCS', 'Resep@50ML', 'Resep@5XYZ']),
        '--report' => tempnam(sys_get_temp_dir(), 'rep'),
        '--execute' => $execute,
    ]))->assertSuccessful();

    $posts = Http::recorded(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/units'));

    expect($posts)->toHaveCount($execute ? 3 : 0);

    if ($execute) {
        expect($posts->map(fn ($pair) => $pair[0]->data())->values()->all())->toBe([
            ['metricID' => 2, 'uomName' => 'Resep@200GR'],
            ['metricID' => 1, 'uomName' => 'PACK@3PCS'],
            ['metricID' => 3, 'uomName' => 'Resep@50ML'],
        ]);
    }
})->with([false, true]);
