<?php

use App\Actions\ExportErpRepairRequestsXlsxAction;
use App\Enums\ItRequestStatus;
use App\Models\Branch;
use App\Models\ErpModule;
use App\Models\ErpRepairRequest;
use App\Models\ItRequestType;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

it('exports erp repair requests to xlsx with all data and proper formatting', function () {
    $requester = User::factory()->create(['name' => 'Jane Request', 'username' => 'janereq']);
    $closer = User::factory()->create(['name' => 'IT Admin']);
    $branch = Branch::factory()->create(['name' => 'Jakarta Branch']);
    $module = ErpModule::create(['name' => 'Inventory Module', 'is_active' => true]);
    $requestType = ItRequestType::create(['name' => 'Bug Fix', 'is_active' => true]);

    $request = ErpRepairRequest::factory()->create([
        'requester_id' => $requester->id,
        'closed_by' => $closer->id,
        'branch_id' => $branch->id,
        'erp_module_id' => $module->id,
        'request_type_id' => $requestType->id,
        'keterangan' => '=FormulaKeterangan',
        'attachments' => ['logs/error.log', 'screenshots/shot.png'],
        'status' => ItRequestStatus::Completed,
        'priority' => 'high',
        'it_notes' => 'Resolved after patch deployment',
        'created_at' => '2026-09-01 10:00:00',
        'resolved_at' => '2026-09-01 14:00:00',
    ]);

    $action = new ExportErpRepairRequestsXlsxAction;
    $response = $action->execute(ErpRepairRequest::query());

    expect($response)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($response->getFile()->getPathname())->toStartWith(realpath(sys_get_temp_dir()));

    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toContain('ID', 'No. Tiket', 'Pemohon', 'Cabang', 'Modul ERP', 'Request Type', 'Keterangan');

    $dataRow = $rows[1];
    expect($dataRow[0])->toBe($request->id);
    expect($dataRow[5])->toBe('Jane Request');
    expect($dataRow[6])->toBe('janereq');
    expect($dataRow[8])->toBe('Jakarta Branch');
    expect($dataRow[10])->toBe('Inventory Module');
    expect($dataRow[12])->toBe('Bug Fix');
    expect($dataRow[13])->toBe("'=FormulaKeterangan");
    expect($dataRow[14])->toBe('logs/error.log | screenshots/shot.png');
    expect($dataRow[16])->toBe('High');
    expect($dataRow[17])->toBe('Resolved after patch deployment');
    expect($dataRow[19])->toBe('IT Admin');
});

it('filters erp repair requests using an inclusive date range in direct xlsx export', function () {
    $requester = User::factory()->create();
    $branch = Branch::factory()->create();

    ErpRepairRequest::factory()->create([
        'requester_id' => $requester->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-08-31 23:59:59',
    ]);
    $included = ErpRepairRequest::factory()->create([
        'requester_id' => $requester->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-09-15 12:00:00',
    ]);
    ErpRepairRequest::factory()->create([
        'requester_id' => $requester->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-10-01 00:00:00',
    ]);

    $action = new ExportErpRepairRequestsXlsxAction;
    $response = $action->execute(ErpRepairRequest::query(), '2026-09-01', '2026-09-30');

    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    expect($rows)->toHaveCount(2);
    expect($rows[1][0])->toBe($included->id);
});
