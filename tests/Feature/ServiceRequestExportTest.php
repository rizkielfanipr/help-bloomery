<?php

use App\Actions\ExportServiceRequestsXlsxAction;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('exports service requests to xlsx with all data and proper formatting', function () {
    $scheduledBy = User::factory()->create(['name' => 'Store Staff', 'username' => 'storestaff']);
    $technician = User::factory()->create(['name' => 'Alex Tech', 'username' => 'alextech']);

    $request = ServiceRequest::factory()->create([
        'scheduled_by' => $scheduledBy->id,
        'technician_id' => $technician->id,
        'scheduled_date' => '2026-09-01',
        'requestor_notes' => '=FormulaNotes',
        'attachments' => ['photos/before.jpg'],
        'status' => ServiceRequestStatus::Submitted,
        'warranty_expires_at' => '2026-10-01 12:00:00',
    ]);

    $action = new ExportServiceRequestsXlsxAction;
    $response = $action->execute(ServiceRequest::query());

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
    expect($rows[0])->toContain('ID', 'Kode', 'Tanggal Penjadwalan', 'Pemohon', 'Teknisi', 'Catatan Pemohon', 'Status');

    $dataRow = $rows[1];
    expect($dataRow[0])->toBe($request->id);
    expect($dataRow[1])->toBe($request->code);
    expect($dataRow[5])->toBe('Store Staff');
    expect($dataRow[8])->toBe('Alex Tech');
    expect($dataRow[9])->toBe("'=FormulaNotes");
    expect($dataRow[10])->toBe('photos/before.jpg');
    expect($dataRow[11])->toBe('Submitted');
});

it('filters service requests using an inclusive date range in direct xlsx export', function () {
    $scheduledBy = User::factory()->create();

    ServiceRequest::factory()->create([
        'scheduled_by' => $scheduledBy->id,
        'scheduled_date' => '2026-08-31',
    ]);
    $included = ServiceRequest::factory()->create([
        'scheduled_by' => $scheduledBy->id,
        'scheduled_date' => '2026-09-15',
    ]);
    ServiceRequest::factory()->create([
        'scheduled_by' => $scheduledBy->id,
        'scheduled_date' => '2026-10-01',
    ]);

    $action = new ExportServiceRequestsXlsxAction;
    $response = $action->execute(ServiceRequest::query(), '2026-09-01', '2026-09-30');

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
