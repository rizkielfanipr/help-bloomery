<?php

namespace App\Filament\Helpdesk\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Enums\RequestStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\DesignRequest;
use App\Models\ErpRepairRequest;
use App\Models\PurchaseRequest;
use App\Models\ServiceRequest;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\View\View;

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
        $pendingPurchase = [PurchaseRequestStatus::Submitted->value, PurchaseRequestStatus::InProcess->value];

        $this->moduleStats = [
            [
                'key' => 'service',
                'label' => 'Permintaan Servis',
                'total' => ServiceRequest::count(),
                'pending' => ServiceRequest::whereIn('status', $pendingService)->count(),
                'completed' => ServiceRequest::where('status', ServiceRequestStatus::Completed->value)->count(),
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
                'total' => ErpRepairRequest::count(),
                'pending' => ErpRepairRequest::whereIn('status', $pendingRequest)->count(),
                'completed' => ErpRepairRequest::where('status', RequestStatus::Completed->value)->count(),
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
                'total' => DesignRequest::count(),
                'pending' => DesignRequest::whereIn('status', $pendingRequest)->count(),
                'completed' => DesignRequest::where('status', RequestStatus::Completed->value)->count(),
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
                'total' => PurchaseRequest::count(),
                'pending' => PurchaseRequest::whereIn('status', $pendingPurchase)->count(),
                'completed' => PurchaseRequest::where('status', PurchaseRequestStatus::Completed->value)->count(),
                'bg' => 'bg-amber-50',
                'icon_bg' => 'bg-amber-100',
                'icon_color' => 'text-amber-600',
                'badge_bg' => 'bg-amber-100 text-amber-700',
                'border' => 'border-amber-100',
                'href' => route('filament.helpdesk.resources.purchase-requests.index'),
                'path' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            ],
        ];
    }

    private function computeTrendData(): void
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i));
        $since = now()->subDays(30)->startOfDay();

        $serviceCounts = ServiceRequest::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $since)->groupBy('date')->pluck('count', 'date');

        $erpCounts = ErpRepairRequest::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $since)->groupBy('date')->pluck('count', 'date');

        $designCounts = DesignRequest::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $since)->groupBy('date')->pluck('count', 'date');

        $purchaseCounts = PurchaseRequest::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $since)->groupBy('date')->pluck('count', 'date');

        $this->trendLabels = $days->map(fn ($d) => $d->format('d M'))->values()->toArray();

        $this->trendDatasets = [
            ['label' => 'Servis',    'color' => '#3b82f6', 'data' => $days->map(fn ($d) => (int) ($serviceCounts[$d->format('Y-m-d')] ?? 0))->values()->toArray()],
            ['label' => 'ERP',       'color' => '#6366f1', 'data' => $days->map(fn ($d) => (int) ($erpCounts[$d->format('Y-m-d')] ?? 0))->values()->toArray()],
            ['label' => 'Design',    'color' => '#ec4899', 'data' => $days->map(fn ($d) => (int) ($designCounts[$d->format('Y-m-d')] ?? 0))->values()->toArray()],
            ['label' => 'Pembelian', 'color' => '#f59e0b', 'data' => $days->map(fn ($d) => (int) ($purchaseCounts[$d->format('Y-m-d')] ?? 0))->values()->toArray()],
        ];
    }

    private function computeDistribution(): void
    {
        $this->distributionLabels = ['Servis', 'ERP', 'Design', 'Pembelian'];
        $this->distributionDataset = [
            (int) ServiceRequest::count(),
            (int) ErpRepairRequest::count(),
            (int) DesignRequest::count(),
            (int) PurchaseRequest::count(),
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

        $this->recentRequests = $items->sortByDesc('date')->take(10)->values()->toArray();
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
