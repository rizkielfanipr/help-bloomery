<?php

namespace App\Http\Controllers\Casual;

use App\Models\BriefingScore;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BriefingScorePdfController
{
    public function __invoke(Request $request): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('view briefing scores') && $user->branch_id, 403);

        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $score = BriefingScore::with('branch')
            ->where('branch_id', $user->branch_id)
            ->where('year', $year)
            ->where('month', $month)
            ->firstOrFail();

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = $monthNames[$month].' '.$year;

        $pdf = Pdf::loadView('exports.casual-briefing-score-pdf', compact('score', 'periodLabel'));

        return $pdf->download("nilai-briefing-{$score->branch->name}-{$periodLabel}.pdf");
    }
}
