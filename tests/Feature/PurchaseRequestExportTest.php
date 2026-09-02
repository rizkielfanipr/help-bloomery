<?php

use App\Actions\ExportPurchaseRequestsXlsxAction;
use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Models\Branch;
use App\Models\PurchaseRequest;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

it('exports purchase requests to xlsx with all data and proper formatting', function () {
    $user = User::factory()->create(['name' => 'John Doe', 'username' => 'johndoe']);
    $processor = User::factory()->create(['name' => 'Admin User']);
    $branch = Branch::factory()->create(['name' => 'Main Branch']);

    $request = PurchaseRequest::factory()->create([
        'user_id' => $user->id,
        'processed_by' => $processor->id,
        'branch_id' => $branch->id,
        'division' => 'IT',
        'item_name' => '=FormulaItem',
        'quantity' => 5,
        'purchase_type' => PurchaseType::New,
        'purchase_reason' => 'Need new equipment',
        'status' => PurchaseRequestStatus::Approved,
        'created_at' => '2026-09-01 10:00:00',
    ]);

    $action = new ExportPurchaseRequestsXlsxAction;
    $response = $action->execute(PurchaseRequest::query());

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
    expect($rows[0])->toContain('ID', 'Kode Internal', 'No. Permintaan', 'Pemohon', 'Nama Barang');

    $dataRow = $rows[1];
    expect($dataRow[0])->toBe($request->id);
    expect($dataRow[7])->toBe('John Doe');
    expect($dataRow[8])->toBe('johndoe');
    expect($dataRow[10])->toBe('Main Branch');
    expect($dataRow[12])->toBe("'=FormulaItem");
    expect($dataRow[13])->toBe(5);
    expect($dataRow[21])->toBe('Admin User');
});

it('filters purchase requests using an inclusive date range in direct xlsx export', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();

    PurchaseRequest::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-08-31 23:59:59',
    ]);
    $included = PurchaseRequest::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-09-15 12:00:00',
    ]);
    PurchaseRequest::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-10-01 00:00:00',
    ]);

    $action = new ExportPurchaseRequestsXlsxAction;
    $response = $action->execute(PurchaseRequest::query(), '2026-09-01', '2026-09-30');

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
