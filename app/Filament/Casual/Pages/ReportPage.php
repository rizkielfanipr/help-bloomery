<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualClockRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.report-page';

    #[Url]
    public string $selectedMonth = '';

    public function mount(): void
    {
        if (! $this->selectedMonth) {
            $this->selectedMonth = now()->format('Y-m');
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Laporan';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function previousMonth(): void
    {
        $this->selectedMonth = Carbon::createFromFormat('Y-m', $this->selectedMonth)
            ->subMonth()
            ->format('Y-m');
    }

    public function nextMonth(): void
    {
        $next = Carbon::createFromFormat('Y-m', $this->selectedMonth)->addMonth();

        if ($next->lte(now()->startOfMonth())) {
            $this->selectedMonth = $next->format('Y-m');
        }
    }

    #[Computed]
    public function reportData(): array
    {
        $user = auth()->user();
        $month = Carbon::createFromFormat('Y-m', $this->selectedMonth);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->min(now());

        $records = CasualClockRecord::where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('shift')
            ->orderBy('date')
            ->get();

        $workingDays = 0;
        $day = $start->copy();
        while ($day->lte($end)) {
            if ($day->isWeekday()) {
                $workingDays++;
            }
            $day->addDay();
        }

        $presentCount = $records->count();
        $lateCount = $records->where('is_late', true)->count();
        $earlyOutCount = $records->where('is_early_out', true)->count();
        $onTimeCount = max(0, $presentCount - $lateCount);
        $absentCount = max(0, $workingDays - $presentCount);

        $totalSeconds = $records->sum(function (CasualClockRecord $r): int {
            if (! $r->clock_in_at || ! $r->clock_out_at) {
                return 0;
            }

            return (int) $r->clock_in_at->diffInSeconds($r->clock_out_at);
        });

        $avgSeconds = $presentCount > 0 ? intdiv($totalSeconds, $presentCount) : 0;
        $attendanceRate = $workingDays > 0 ? round($presentCount / $workingDays * 100) : 0;
        $punctualityRate = $presentCount > 0 ? round($onTimeCount / $presentCount * 100) : 0;

        return [
            'user' => $user,
            'shift' => $user->casualShift,
            'position' => $user->casualPosition,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'records' => $records,
            'workingDays' => $workingDays,
            'presentCount' => $presentCount,
            'absentCount' => $absentCount,
            'lateCount' => $lateCount,
            'earlyOutCount' => $earlyOutCount,
            'onTimeCount' => $onTimeCount,
            'totalSeconds' => $totalSeconds,
            'avgSeconds' => $avgSeconds,
            'attendanceRate' => $attendanceRate,
            'punctualityRate' => $punctualityRate,
        ];
    }

    public function downloadPdf(): StreamedResponse
    {
        $data = $this->reportData;

        $pdf = Pdf::loadView('filament.casual.pdf.report', $data)
            ->setPaper('a4', 'portrait');

        $name = str_replace(' ', '-', strtolower($data['user']->name));
        $filename = "laporan-kinerja-{$name}-{$this->selectedMonth}.pdf";

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
