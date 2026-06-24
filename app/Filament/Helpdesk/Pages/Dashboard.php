<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestRepair;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\View\View;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.helpdesk.pages.dashboard';

    protected static ?string $title = 'Dashboard';

    public array $stats = [];

    public array $trendLabels = [];

    public array $trendDataset = [];

    public array $statusLabels = [];

    public array $statusDataset = [];

    public array $recentTickets = [];

    public array $recentActivity = [];

    public function mount(): void
    {
        $this->computeStats();
        $this->computeChartData();
        $this->computeRecentData();
    }

    private function computeStats(): void
    {
        $total = ServiceRequest::count();
        $submitted = ServiceRequest::where('status', 'submitted')->count();
        $inProgress = ServiceRequest::where('status', 'in_progress')->count();
        $completed = ServiceRequest::where('status', 'completed')->count();

        $thisMonth = ServiceRequest::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        $lastMonth = ServiceRequest::whereMonth('created_at', now()->subMonthNoOverflow()->month)
            ->whereYear('created_at', now()->subMonthNoOverflow()->year)->count();

        $thisMonthCompleted = ServiceRequest::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        $lastMonthCompleted = ServiceRequest::where('status', 'completed')
            ->whereMonth('created_at', now()->subMonthNoOverflow()->month)
            ->whereYear('created_at', now()->subMonthNoOverflow()->year)->count();

        $calcChange = fn ($now, $prev) => $prev > 0
            ? round((($now - $prev) / $prev) * 100)
            : ($now > 0 ? 100 : 0);

        $this->stats = [
            [
                'label' => 'Total Tiket',
                'value' => $total,
                'sub' => 'semua permintaan',
                'change' => $calcChange($thisMonth, $lastMonth),
                'icon' => 'heroicon-o-queue-list',
                'bg' => 'bg-blue-500/10 dark:bg-blue-500/20',
                'icon_color' => 'text-blue-600 dark:text-blue-400',
                'border_color' => 'border-blue-100 dark:border-blue-500/10',
            ],
            [
                'label' => 'Diajukan',
                'value' => $submitted,
                'sub' => 'menunggu teknisi',
                'change' => null,
                'icon' => 'heroicon-o-paper-airplane',
                'bg' => 'bg-sky-500/10 dark:bg-sky-500/20',
                'icon_color' => 'text-sky-600 dark:text-sky-400',
                'border_color' => 'border-sky-100 dark:border-sky-500/10',
            ],
            [
                'label' => 'Sedang Dikerjakan',
                'value' => $inProgress,
                'sub' => 'tiket aktif',
                'change' => null,
                'icon' => 'heroicon-o-wrench-screwdriver',
                'bg' => 'bg-amber-500/10 dark:bg-amber-500/20',
                'icon_color' => 'text-amber-600 dark:text-amber-400',
                'border_color' => 'border-amber-100 dark:border-amber-500/10',
            ],
            [
                'label' => 'Selesai',
                'value' => $completed,
                'sub' => 'total diselesaikan',
                'change' => $calcChange($thisMonthCompleted, $lastMonthCompleted),
                'icon' => 'heroicon-o-check-badge',
                'bg' => 'bg-emerald-500/10 dark:bg-emerald-500/20',
                'icon_color' => 'text-emerald-600 dark:text-emerald-400',
                'border_color' => 'border-emerald-100 dark:border-emerald-500/10',
            ],
        ];
    }

    private function computeChartData(): void
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i));

        $counts = ServiceRequest::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy('date')
            ->pluck('count', 'date');

        $this->trendLabels = $days->map(fn ($d) => $d->format('d M'))->values()->toArray();
        $this->trendDataset = $days->map(fn ($d) => (int) ($counts[$d->format('Y-m-d')] ?? 0))->values()->toArray();

        $this->statusLabels = ['Diajukan', 'Dikerjakan', 'Garansi', 'Selesai'];
        $this->statusDataset = [
            (int) ServiceRequest::where('status', 'submitted')->count(),
            (int) ServiceRequest::where('status', 'in_progress')->count(),
            (int) ServiceRequest::where('status', 'warranty')->count(),
            (int) ServiceRequest::where('status', 'completed')->count(),
        ];
    }

    private function computeRecentData(): void
    {
        $this->recentTickets = ServiceRequest::with(['technician', 'scheduledBy'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'ref' => 'SR-'.str_pad($r->id, 4, '0', STR_PAD_LEFT),
                'scheduled_date' => $r->scheduled_date?->format('d M Y') ?? '-',
                'status' => $r->status->value,
                'status_label' => $r->status->getLabel(),
                'technician' => $r->technician?->name ?? 'Belum ditugaskan',
                'requestor' => $r->scheduledBy?->name ?? '-',
                'created_at' => $r->created_at->diffForHumans(),
            ])
            ->toArray();

        $this->recentActivity = ServiceRequestRepair::with(['serviceRequest', 'technician'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($repair) => [
                'is_complete' => $repair->completed_at !== null,
                'title' => $repair->completed_at
                    ? 'Perbaikan selesai oleh '.($repair->technician?->name ?? 'Teknisi')
                    : 'Perbaikan dimulai oleh '.($repair->technician?->name ?? 'Teknisi'),
                'ref' => 'SR-'.str_pad($repair->service_request_id, 4, '0', STR_PAD_LEFT),
                'time' => $repair->updated_at->diffForHumans(),
                'cycle' => $repair->cycle === 1 ? 'Perbaikan Awal' : "Perbaikan ke-{$repair->cycle}",
            ])
            ->toArray();
    }

    public function render(): View
    {
        return view($this->getView(), $this->getViewData())
            ->layout('components.layouts.helpdesk-app');
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function getFooterWidgets(): array
    {
        return [];
    }
}
