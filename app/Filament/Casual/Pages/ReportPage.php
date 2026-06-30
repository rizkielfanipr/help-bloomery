<?php

namespace App\Filament\Casual\Pages;

use App\Models\CasualClockRecord;
use App\Models\CasualOvertimeRequest;
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
    public string $selectedWeek = '';

    public function mount(): void
    {
        if (! $this->selectedWeek) {
            $this->selectedWeek = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
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

    public function previousWeek(): void
    {
        $this->selectedWeek = Carbon::parse($this->selectedWeek)
            ->subWeek()
            ->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $next = Carbon::parse($this->selectedWeek)->addWeek();

        if ($next->lte(now()->startOfWeek(Carbon::MONDAY))) {
            $this->selectedWeek = $next->format('Y-m-d');
        }
    }

    #[Computed]
    public function reportData(): array
    {
        $user = auth()->user();
        $weekStart = Carbon::parse($this->selectedWeek)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->min(now());

        $records = CasualClockRecord::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with('overtimeRequest')
            ->orderBy('date')
            ->get();

        $totalDays = $weekStart->copy()->startOfDay()->diffInDays($weekEnd->copy()->startOfDay()) + 1;

        $presentCount = $records->count();
        $lateCount = $records->where('is_late', true)->count();
        $earlyOutCount = $records->where('is_early_out', true)->count();
        $onTimeCount = max(0, $presentCount - $lateCount);
        $absentCount = max(0, $totalDays - $presentCount);

        $totalSeconds = $records->sum(function (CasualClockRecord $r): int {
            if (! $r->clock_in_at || ! $r->clock_out_at) {
                return 0;
            }

            return (int) $r->clock_in_at->diffInSeconds($r->clock_out_at);
        });

        $avgSeconds = $presentCount > 0 ? intdiv($totalSeconds, $presentCount) : 0;
        $attendanceRate = $totalDays > 0 ? round($presentCount / $totalDays * 100) : 0;
        $punctualityRate = $presentCount > 0 ? round($onTimeCount / $presentCount * 100) : 0;

        $recordIds = $records->pluck('id');
        $overtimes = CasualOvertimeRequest::whereIn('casual_clock_record_id', $recordIds)->get();

        $overtimeCount = $overtimes->count();
        $overtimeTotalHours = $overtimes->sum('approved_hours');
        $overtimeTotalFee = $overtimes->sum('overtime_fee');

        return [
            'user' => $user,
            'shift' => $user->casualShift,
            'position' => $user->casualPosition,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'records' => $records,
            'totalDays' => $totalDays,
            'presentCount' => $presentCount,
            'absentCount' => $absentCount,
            'lateCount' => $lateCount,
            'earlyOutCount' => $earlyOutCount,
            'onTimeCount' => $onTimeCount,
            'totalSeconds' => $totalSeconds,
            'avgSeconds' => $avgSeconds,
            'attendanceRate' => $attendanceRate,
            'punctualityRate' => $punctualityRate,
            'overtimeCount' => $overtimeCount,
            'overtimeTotalHours' => $overtimeTotalHours,
            'overtimeTotalFee' => $overtimeTotalFee,
        ];
    }

    public function downloadPdf(): StreamedResponse
    {
        $data = $this->reportData;

        $pdf = Pdf::loadView('filament.casual.pdf.report', $data)
            ->setPaper('a4', 'portrait');

        $name = str_replace(' ', '-', strtolower($data['user']->name));
        $filename = "laporan-kinerja-{$name}-{$this->selectedWeek}.pdf";

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
