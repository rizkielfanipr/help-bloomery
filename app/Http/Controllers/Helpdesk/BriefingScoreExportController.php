<?php

namespace App\Http\Controllers\Helpdesk;

use App\Models\Branch;
use App\Models\BriefingScore;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BriefingScoreExportController
{
    public function __invoke(Request $request): Response|BinaryFileResponse|StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user?->can('view briefing scores'), 403);

        $year = $request->integer('year', now()->year);
        $month = $request->integer('month') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $format = $request->input('format', 'excel');

        // docs/daily-briefing-remediation-prd.md §15.3: an explicit branch_id is re-validated
        // against the viewer's own access; omitting it falls back to every accessible branch
        // rather than every branch in the database.
        if ($branchId !== null) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }

        $accessibleBranchIds = $user->canAccessAllBranches() ? null : $user->accessibleBranchIds();

        $scores = BriefingScore::with(['branch'])
            ->join('branches', 'branches.id', '=', 'briefing_scores.branch_id')
            ->select('briefing_scores.*')
            ->when($year, fn ($q) => $q->where('year', $year))
            ->when($month, fn ($q) => $q->where('month', $month))
            ->when($branchId, fn ($q) => $q->where('briefing_scores.branch_id', $branchId))
            ->when($accessibleBranchIds !== null, fn ($q) => $q->whereIn('briefing_scores.branch_id', $accessibleBranchIds))
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('branches.name')
            ->get();

        $branchLabel = $branchId ? (Branch::find($branchId)?->name ?? 'Branch') : 'Semua Branch';
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($month ? $monthNames[$month].' ' : 'Semua Bulan ').$year;

        if ($format === 'pdf') {
            return $this->exportPdf($scores, $periodLabel, $branchLabel);
        }

        return $this->exportExcel($scores, $periodLabel, $branchLabel, $year, $month);
    }

    private function exportPdf($scores, string $periodLabel, string $branchLabel): Response
    {
        $pdf = Pdf::loadView('exports.briefing-scores-pdf', compact('scores', 'periodLabel', 'branchLabel'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("nilai-briefing-{$periodLabel}.pdf");
    }

    private function exportExcel($scores, string $periodLabel, string $branchLabel, int $year, ?int $month): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'score_export_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($tempPath);

        $styleTitle = (new Style)->setFontBold()->setFontSize(14)->setBackgroundColor('5B21B6')->setFontColor(Color::WHITE);
        $styleInfo = (new Style)->setFontSize(10)->setBackgroundColor('EDE9FE');
        $styleHeader = (new Style)->setFontBold()->setFontSize(10)->setBackgroundColor('7C3AED')->setFontColor(Color::WHITE);
        $stylePass = (new Style)->setFontSize(10)->setBackgroundColor('DCFCE7');
        $styleFail = (new Style)->setFontSize(10)->setBackgroundColor('FEE2E2');

        $writer->addRow(Row::fromValues(['Laporan Nilai Briefing', '', '', '', '', ''], $styleTitle));
        $writer->addRow(Row::fromValues(['Branch: '.$branchLabel, '', '', '', '', ''], $styleInfo));
        $writer->addRow(Row::fromValues(['Periode: '.$periodLabel, '', '', '', '', ''], $styleInfo));
        $writer->addRow(Row::fromValues(['Export: '.now()->isoFormat('D MMMM Y HH:mm'), '', '', '', '', ''], $styleInfo));
        $writer->addRow(Row::fromValues(['', '', '', '', '', '']));

        // docs/daily-briefing-scoring-ui-prd.md §17: branch, period, final score, status,
        // provisional/final, per-period score, method version, and computed timestamp — the same
        // breakdown snapshot the UI and PDF read, so the numbers never disagree.
        $writer->addRow(Row::fromValues(
            ['No', 'Branch', 'Bulan', 'Nilai (%)', 'Status', 'Sementara', 'Harian (%)', 'Mingguan (%)', 'Bulanan (%)', 'Metode', 'Dihitung'],
            $styleHeader,
        ));

        foreach ($scores as $i => $score) {
            $rowStyle = $score->isPassing() ? $stylePass : $styleFail;
            $periodRate = fn (string $period) => ($score->periodBreakdown($period)['applicable'] ?? false)
                ? (float) number_format($score->periodBreakdown($period)['rate'], 2)
                : '-';

            $writer->addRow(Row::fromValues([
                $i + 1,
                $score->branch->name,
                $score->monthLabel(),
                (float) number_format($score->score, 2),
                $score->isPassing() ? 'Achieve' : 'Tidak Achieve',
                $score->isProvisional() ? 'Ya' : 'Tidak',
                $periodRate('daily'),
                $periodRate('weekly'),
                $periodRate('monthly'),
                'v'.$score->methodVersion(),
                $score->computed_at?->format('d/m/Y H:i') ?? '-',
            ], $rowStyle));
        }

        $writer->close();

        $slug = 'nilai-briefing-'.str_replace(' ', '-', strtolower($periodLabel));

        return response()->download($tempPath, $slug.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
