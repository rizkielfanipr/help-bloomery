<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CasualStaffExportController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        abort_unless(auth()->check(), 403);

        $query = User::query()
            ->role('casual_staff')
            ->with('casualPosition')
            ->orderBy('name');

        $filename = 'casual-staff-'.now()->format('Y-m-d').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'casual_staff_export_').'.xlsx';

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setBackgroundColor('4472C4')
            ->setFontColor(Color::WHITE);

        $writer = new Writer;
        $writer->openToFile($tempPath);

        $writer->addRow(Row::fromValues([
            'Nama',
            'Nomor HP',
            'Posisi',
            'Nama Bank',
            'Nomor Rekening',
            'Status',
            'Tanggal Daftar',
        ], $headerStyle));

        $query->chunk(500, function ($users) use ($writer): void {
            foreach ($users as $user) {
                $writer->addRow(Row::fromValues([
                    $user->name,
                    $user->phone ?? '',
                    $user->casualPosition?->name ?? '',
                    $user->bank_name ?? '',
                    $user->bank_account_number ?? '',
                    $user->is_active ? 'Aktif' : 'Tidak Aktif',
                    $user->created_at?->format('d/m/Y') ?? '',
                ]));
            }
        });

        $writer->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
