<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\DriverMealAllowancePeriod;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DriverMealAllowanceExportController extends Controller
{
    public function __invoke(DriverMealAllowancePeriod $period): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('export driver meal allowance periods'), 403);
        $type = request()->string('type')->value() === 'detail' ? 'detail' : 'summary';
        $period->load(['summaries.driver:id,name,username', 'summaries.items']);

        $filename = sprintf('uang-makan-driver-%d-%02d-%s.xlsx', $period->report_year, $period->report_month, $type);
        $path = tempnam(sys_get_temp_dir(), 'driver_meal_').'.xlsx';
        $headerStyle = (new Style)->setFontBold()->setBackgroundColor('2563EB')->setFontColor(Color::WHITE);
        $writer = new Writer;
        $writer->openToFile($path);

        if ($type === 'summary') {
            $writer->addRow(Row::fromValues(['Nama Driver', 'Username', 'Jumlah Trip', 'Nilai Dasar', 'Penyesuaian', 'Alasan Penyesuaian', 'Total Akhir', 'Status Periode'], $headerStyle));
            foreach ($period->summaries as $summary) {
                $writer->addRow(Row::fromValues([
                    $summary->driver->name, $summary->driver->username, $summary->trip_count,
                    (float) $summary->base_amount, (float) $summary->adjustment_amount,
                    $summary->adjustment_reason ?? '', (float) $summary->final_amount, ucfirst($period->status),
                ]));
            }
        } else {
            $writer->addRow(Row::fromValues(['Nama Driver', 'Username', 'Tanggal', 'Kode Trip', 'Rute', 'Nominal', 'Dihitung', 'Alasan Pengecualian', 'Sumber Nominal'], $headerStyle));
            foreach ($period->summaries as $summary) {
                foreach ($summary->items as $item) {
                    $writer->addRow(Row::fromValues([
                        $summary->driver->name, $summary->driver->username, $item->trip_date->format('d/m/Y'),
                        $item->trip_code, $item->route_name ?? '', (float) $item->allowance_amount,
                        $item->is_included ? 'Ya' : 'Tidak', $item->exclusion_reason ?? '', $item->amount_source,
                    ]));
                }
            }
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
