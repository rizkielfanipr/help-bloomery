<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\CasualClockRecord;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CasualClockRecordExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('view clock records'), 403);

        $query = CasualClockRecord::query()
            ->with(['user.casualPosition', 'branch', 'overtimeRequest'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if ($branchId = $request->integer('branch_id') ?: null) {
            $query->where('branch_id', $branchId);
        }

        if ($userId = $request->integer('user_id') ?: null) {
            $query->where('user_id', $userId);
        }

        if ($dateFrom = $request->string('date_from')->toString() ?: null) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateUntil = $request->string('date_until')->toString() ?: null) {
            $query->whereDate('date', '<=', $dateUntil);
        }

        $suffix = match (true) {
            filled($request->date_from) && filled($request->date_until) => $request->date_from.'_sd_'.$request->date_until,
            filled($request->date_from) => 'mulai-'.$request->date_from,
            filled($request->date_until) => 'sd-'.$request->date_until,
            default => now()->format('Y-m-d'),
        };

        $filename = 'absensi-casual-'.$suffix.'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'casual_export_').'.xlsx';

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setBackgroundColor('4472C4')
            ->setFontColor(Color::WHITE);

        $writer = new Writer;
        $writer->openToFile($tempPath);

        $writer->addRow(Row::fromValues([
            'Nama',
            'Branch',
            'Posisi',
            'Tanggal',
            'Clock In',
            'Clock Out',
            'Durasi Kerja',
            'Fee (Rp)',
            'Durasi Lembur',
            'Fee Lembur (Rp)',
            'Nomor Rekening',
            'Nama Bank',
            'Nomor HP',
        ], $headerStyle));

        $query->chunk(500, function ($records) use ($writer): void {
            foreach ($records as $record) {
                $position = $record->effectivePosition();
                $fee = $position?->fee_per_day ?? 0;
                $overtimeRequest = $record->overtimeRequest;

                $overtimeFee = ($overtimeRequest?->isApproved() && $overtimeRequest->overtime_fee > 0)
                    ? (int) $overtimeRequest->overtime_fee
                    : '';

                $overtimeDuration = '';
                if ($overtimeRequest?->isApproved() && $overtimeRequest->approved_hours > 0) {
                    $totalMins = (int) round($overtimeRequest->approved_hours * 60);
                    $h = intdiv($totalMins, 60);
                    $m = $totalMins % 60;
                    $overtimeDuration = $h > 0 ? "{$h}j {$m}m" : "{$m}m";
                }

                $writer->addRow(Row::fromValues([
                    $record->user?->name ?? '',
                    $record->branch?->name ?? '',
                    $position?->name ?? '',
                    $record->date?->format('d/m/Y') ?? '',
                    $record->clock_in_at?->format('H:i') ?? '',
                    $record->clock_out_at?->format('H:i') ?? '',
                    $record->formattedWorkDuration() ?? '',
                    $fee > 0 ? (int) $fee : '',
                    $overtimeDuration,
                    $overtimeFee,
                    $record->user?->bank_account_number ?? '',
                    $record->user?->bank_name ?? '',
                    $record->user?->phone ?? '',
                ]));
            }
        });

        $writer->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
