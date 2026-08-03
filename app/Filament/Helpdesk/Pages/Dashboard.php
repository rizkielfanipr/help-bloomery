<?php

namespace App\Filament\Helpdesk\Pages;

use App\Enums\BriefingReviewStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RequestStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TripStatus;
use App\Models\BriefingItem;
use App\Models\BriefingTask;
use App\Models\CasualClockRecord;
use App\Models\CasualOvertimeRequest;
use App\Models\DesignRequest;
use App\Models\ErpRepairRequest;
use App\Models\PurchaseRequest;
use App\Models\SalesReport;
use App\Models\ServiceRequest;
use App\Models\Trip;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.helpdesk.pages.dashboard';

    protected static ?string $title = 'Dashboard';

    public array $moduleStats = [];

    public array $trendLabels = [];

    public array $trendDatasets = [];

    public array $distributionLabels = [];

    public array $distributionDataset = [];

    public array $recentRequests = [];

    public function mount(): void
    {
        $this->computeModuleStats();
        $this->computeTrendData();
        $this->computeDistribution();
        $this->computeRecentRequests();
    }

    private function computeModuleStats(): void
    {
        $pendingService = [ServiceRequestStatus::Submitted->value, ServiceRequestStatus::InProgress->value, ServiceRequestStatus::ReSubmitted->value];
        $pendingRequest = [RequestStatus::Submitted->value, RequestStatus::InReview->value, RequestStatus::InProgress->value];
        $pendingPurchase = [PurchaseRequestStatus::Submitted->value, PurchaseRequestStatus::Approved->value];

        $serviceCounts = ServiceRequest::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $erpCounts = ErpRepairRequest::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $designCounts = DesignRequest::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $purchaseCounts = PurchaseRequest::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $tripCounts = Trip::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

        $this->moduleStats = [
            [
                'key' => 'service',
                'label' => 'Permintaan Servis',
                'total' => $serviceCounts->sum(),
                'pending' => $serviceCounts->only($pendingService)->sum(),
                'completed' => (int) ($serviceCounts[ServiceRequestStatus::Completed->value] ?? 0),
                'bg' => 'bg-blue-50',
                'icon_bg' => 'bg-blue-100',
                'icon_color' => 'text-blue-600',
                'badge_bg' => 'bg-blue-100 text-blue-700',
                'border' => 'border-blue-100',
                'href' => route('filament.helpdesk.resources.service-requests.index'),
                'path' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.657-4.656-.005-.005a1.023 1.023 0 0 0-.361-.214l-3.074-.925a1.023 1.023 0 0 1-.36-.214L9.25 7.5m6.174 1.667L9.25 7.5m4.424 7.672 2.496-3.03',
            ],
            [
                'key' => 'erp',
                'label' => 'Permintaan ERP',
                'total' => $erpCounts->sum(),
                'pending' => $erpCounts->only($pendingRequest)->sum(),
                'completed' => (int) ($erpCounts[RequestStatus::Completed->value] ?? 0),
                'bg' => 'bg-indigo-50',
                'icon_bg' => 'bg-indigo-100',
                'icon_color' => 'text-indigo-600',
                'badge_bg' => 'bg-indigo-100 text-indigo-700',
                'border' => 'border-indigo-100',
                'href' => route('filament.helpdesk.resources.erp-repair-requests.index'),
                'path' => 'M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z',
            ],
            [
                'key' => 'design',
                'label' => 'Permintaan Design',
                'total' => $designCounts->sum(),
                'pending' => $designCounts->only($pendingRequest)->sum(),
                'completed' => (int) ($designCounts[RequestStatus::Completed->value] ?? 0),
                'bg' => 'bg-pink-50',
                'icon_bg' => 'bg-pink-100',
                'icon_color' => 'text-pink-600',
                'badge_bg' => 'bg-pink-100 text-pink-700',
                'border' => 'border-pink-100',
                'href' => route('filament.helpdesk.resources.design-requests.index'),
                'path' => 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42',
            ],
            [
                'key' => 'purchase',
                'label' => 'Permintaan Pembelian',
                'total' => $purchaseCounts->sum(),
                'pending' => $purchaseCounts->only($pendingPurchase)->sum(),
                'completed' => (int) ($purchaseCounts[PurchaseRequestStatus::Completed->value] ?? 0),
                'bg' => 'bg-amber-50',
                'icon_bg' => 'bg-amber-100',
                'icon_color' => 'text-amber-600',
                'badge_bg' => 'bg-amber-100 text-amber-700',
                'border' => 'border-amber-100',
                'href' => route('filament.helpdesk.resources.purchase-requests.index'),
                'path' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            ],
            [
                'key' => 'casual',
                'label' => 'Casual Staff',
                'total' => User::role('CASUAL_STAFF')->count(),
                'pending' => CasualClockRecord::whereDate('date', today())->count(),
                'completed' => CasualOvertimeRequest::count(),
                'pending_label' => 'absensi hari ini',
                'completed_label' => 'pengajuan lembur',
                'bg' => 'bg-teal-50',
                'icon_bg' => 'bg-teal-100',
                'icon_color' => 'text-teal-600',
                'badge_bg' => 'bg-teal-100 text-teal-700',
                'border' => 'border-teal-100',
                'href' => route('filament.helpdesk.resources.casual-staff.index'),
                'path' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
            ],
            [
                'key' => 'briefing',
                'label' => 'Poin Briefing',
                'total' => BriefingItem::count(),
                'pending' => BriefingItem::whereIn('review_status', [
                    BriefingReviewStatus::LegacyPending->value,
                    BriefingReviewStatus::SupervisorReview->value,
                ])->count(),
                'completed' => BriefingItem::where('review_status', BriefingReviewStatus::Approved->value)->count(),
                'pending_label' => 'perlu ditinjau',
                'completed_label' => 'disetujui',
                'bg' => 'bg-emerald-50',
                'icon_bg' => 'bg-emerald-100',
                'icon_color' => 'text-emerald-600',
                'badge_bg' => 'bg-emerald-100 text-emerald-700',
                'border' => 'border-emerald-100',
                'href' => route('filament.helpdesk.resources.briefing-items.index'),
                'path' => 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75',
            ],
            [
                'key' => 'trip',
                'label' => 'Manajemen Trip',
                'total' => $tripCounts->sum(),
                'pending' => (int) ($tripCounts[TripStatus::InProgress->value] ?? 0),
                'completed' => (int) ($tripCounts[TripStatus::Completed->value] ?? 0),
                'bg' => 'bg-sky-50',
                'icon_bg' => 'bg-sky-100',
                'icon_color' => 'text-sky-600',
                'badge_bg' => 'bg-sky-100 text-sky-700',
                'border' => 'border-sky-100',
                'href' => route('filament.helpdesk.resources.trips.index'),
                'path' => 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales Report',
                'total' => SalesReport::count(),
                'pending' => SalesReport::whereMonth('report_date', now()->month)->whereYear('report_date', now()->year)->count(),
                'completed' => SalesReport::whereDate('report_date', today())->count(),
                'pending_label' => 'bulan ini',
                'completed_label' => 'hari ini',
                'bg' => 'bg-violet-50',
                'icon_bg' => 'bg-violet-100',
                'icon_color' => 'text-violet-600',
                'badge_bg' => 'bg-violet-100 text-violet-700',
                'border' => 'border-violet-100',
                'href' => route('filament.helpdesk.resources.sales-reports.index'),
                'path' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
            ],
        ];
    }

    private function computeTrendData(): void
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i));
        $since = now()->subDays(30)->startOfDay();

        $byDate = fn ($model, string $col = 'created_at') => $model::selectRaw("DATE({$col}) as date, COUNT(*) as count")
            ->where($col, '>=', $since)->groupByRaw("DATE({$col})")->pluck('count', 'date');

        $counts = [
            'service' => $byDate(ServiceRequest::class),
            'erp' => $byDate(ErpRepairRequest::class),
            'design' => $byDate(DesignRequest::class),
            'purchase' => $byDate(PurchaseRequest::class),
            'casual' => $byDate(CasualClockRecord::class),
            'briefing' => $byDate(BriefingItem::class),
            'trip' => $byDate(Trip::class),
            'sales' => $byDate(SalesReport::class, 'report_date'),
        ];

        $this->trendLabels = $days->map(fn ($d) => $d->format('d M'))->values()->toArray();

        $series = [
            ['label' => 'Servis',    'key' => 'service',  'color' => '#3b82f6'],
            ['label' => 'ERP',       'key' => 'erp',      'color' => '#6366f1'],
            ['label' => 'Design',    'key' => 'design',   'color' => '#ec4899'],
            ['label' => 'Pembelian', 'key' => 'purchase', 'color' => '#f59e0b'],
            ['label' => 'Casual',    'key' => 'casual',   'color' => '#14b8a6'],
            ['label' => 'Briefing',  'key' => 'briefing', 'color' => '#10b981'],
            ['label' => 'Trip',      'key' => 'trip',     'color' => '#0ea5e9'],
            ['label' => 'Sales',     'key' => 'sales',    'color' => '#8b5cf6'],
        ];

        $this->trendDatasets = collect($series)->map(fn ($s) => [
            'label' => $s['label'],
            'color' => $s['color'],
            'data' => $days->map(fn ($d) => (int) ($counts[$s['key']][$d->format('Y-m-d')] ?? 0))->values()->toArray(),
        ])->toArray();
    }

    private function computeDistribution(): void
    {
        $this->distributionLabels = ['Servis', 'ERP', 'Design', 'Pembelian', 'Casual', 'Briefing', 'Trip', 'Sales'];
        $this->distributionDataset = [
            (int) ServiceRequest::count(),
            (int) ErpRepairRequest::count(),
            (int) DesignRequest::count(),
            (int) PurchaseRequest::count(),
            (int) CasualClockRecord::count(),
            (int) BriefingItem::count(),
            (int) Trip::count(),
            (int) SalesReport::count(),
        ];
    }

    private function computeRecentRequests(): void
    {
        $items = collect();

        ServiceRequest::with('scheduledBy')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'Servis',
                'label' => 'SR-'.str_pad($r->id, 4, '0', STR_PAD_LEFT),
                'sub' => $r->scheduledBy?->name ?? '-',
                'status_label' => $r->status->getLabel(),
                'status_color' => $r->status->getColor(),
                'href' => route('filament.helpdesk.resources.service-requests.view', $r->id),
                'date' => $r->created_at,
                'dot' => 'bg-blue-500',
            ]);
        });

        ErpRepairRequest::with('requester', 'module')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'ERP',
                'label' => $r->module?->name ?? 'Permintaan ERP',
                'sub' => $r->requester?->name ?? '-',
                'status_label' => $r->status->getLabel(),
                'status_color' => $r->status->getColor(),
                'href' => route('filament.helpdesk.resources.erp-repair-requests.view', $r->id),
                'date' => $r->created_at,
                'dot' => 'bg-indigo-500',
            ]);
        });

        DesignRequest::with('requester')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'Design',
                'label' => $r->judul_permintaan ?? 'Permintaan Design',
                'sub' => $r->requester?->name ?? '-',
                'status_label' => $r->status->getLabel(),
                'status_color' => $r->status->getColor(),
                'href' => route('filament.helpdesk.resources.design-requests.index'),
                'date' => $r->created_at,
                'dot' => 'bg-pink-500',
            ]);
        });

        PurchaseRequest::with('user')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'Pembelian',
                'label' => $r->item_name ?? 'Permintaan Pembelian',
                'sub' => $r->user?->name ?? '-',
                'status_label' => $r->status->getLabel(),
                'status_color' => $r->status->getColor(),
                'href' => route('filament.helpdesk.resources.purchase-requests.index'),
                'date' => $r->created_at,
                'dot' => 'bg-amber-500',
            ]);
        });

        CasualClockRecord::with('user')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'Casual',
                'label' => 'Absensi '.($r->date?->format('d M Y') ?? '-'),
                'sub' => $r->user?->name ?? '-',
                'status_label' => $r->clock_out_at ? 'Selesai' : 'Hadir',
                'status_color' => $r->clock_out_at ? 'success' : 'warning',
                'href' => route('filament.helpdesk.resources.casual-clock-records.index'),
                'date' => $r->created_at,
                'dot' => 'bg-teal-500',
            ]);
        });

        BriefingItem::with('record.user')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'Briefing',
                'label' => BriefingTask::cached()->firstWhere('key', $r->task_key)?->label ?? $r->task_key,
                'sub' => $r->record?->user?->name ?? '-',
                'status_label' => $r->review_status?->getLabel() ?? 'Belum Ditinjau',
                'status_color' => $r->review_status?->getColor() ?? 'gray',
                'href' => route('filament.helpdesk.resources.briefing-items.index'),
                'date' => $r->created_at,
                'dot' => 'bg-emerald-500',
            ]);
        });

        Trip::with('driver', 'tripRoute')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'Trip',
                'label' => $r->code ?? ('Trip #'.$r->id),
                'sub' => $r->driver?->name ?? '-',
                'status_label' => $r->status->getLabel(),
                'status_color' => $r->status->getColor(),
                'href' => route('filament.helpdesk.resources.trips.view', $r->id),
                'date' => $r->created_at,
                'dot' => 'bg-sky-500',
            ]);
        });

        SalesReport::with('branch', 'submittedBy')->latest()->limit(4)->get()->each(function ($r) use ($items) {
            $items->push([
                'type' => 'Sales',
                'label' => 'Sales '.($r->branch?->name ?? '-'),
                'sub' => $r->submittedBy?->name ?? '-',
                'status_label' => 'Submitted',
                'status_color' => 'success',
                'href' => route('filament.helpdesk.resources.sales-reports.view', $r->id),
                'date' => $r->created_at,
                'dot' => 'bg-violet-500',
            ]);
        });

        $this->recentRequests = $items->sortByDesc('date')->take(10)->values()->toArray();
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
