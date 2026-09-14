<?php

namespace App\Filament\Helpdesk\Widgets;

use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ListErpRepairRequests;
use App\Services\ErpRequestSlaService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;

class ErpRequestSlaStatsWidget extends Widget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.helpdesk.widgets.erp-request-sla-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('view erp requests') ?? false;
    }

    protected function getTablePage(): string
    {
        return ListErpRepairRequests::class;
    }

    /** @return list<array{label: string, value: string, description: string}> */
    public function getStats(): array
    {
        $sla = app(ErpRequestSlaService::class);
        $summary = $sla->summary($this->getPageTableQuery());

        return [
            [
                'label' => 'Rata-rata respons pertama',
                'value' => $sla->businessHours->formatDuration($summary['response_average']),
                'description' => $summary['response_count'].' tiket dengan durasi respons valid',
            ],
            [
                'label' => 'Rata-rata penyelesaian',
                'value' => $sla->businessHours->formatDuration($summary['resolution_average']),
                'description' => $summary['resolution_count'].' tiket Completed dengan durasi valid',
            ],
            [
                'label' => 'Belum direspons',
                'value' => (string) $summary['awaiting_response'],
                'description' => 'Tiket Submitted yang belum masuk Review',
            ],
            [
                'label' => 'Belum selesai',
                'value' => (string) $summary['unfinished'],
                'description' => 'Tiket aktif selain Completed dan Rejected',
            ],
        ];
    }

    /** @return list<array{label: string, value: string, description: string, target: string, target_met: ?bool, provisional: bool}> */
    public function getKpis(): array
    {
        $sla = app(ErpRequestSlaService::class);
        $kpis = $sla->ticketingKpis($this->getPageTableQuery());
        $provisional = $kpis['pending'] > 0 || $kpis['missing_completed_duration'] > 0;

        return [
            [
                'label' => 'Kecepatan pemrosesan data ERP',
                'value' => $kpis['on_time_percent'] === null ? 'Tidak tersedia' : number_format($kpis['on_time_percent'], 2, ',', '.').'%',
                'description' => $kpis['on_time'].' dari '.$kpis['total'].' permintaan Ticketing selesai ≤ 9 jam kerja. '.$kpis['pending'].' belum selesai; '.$kpis['missing_completed_duration'].' Completed tanpa durasi valid.',
                'target' => 'Target ≥ 98% selesai ≤ 1 hari kerja (9 jam)',
                'target_met' => $kpis['processing_target_met'],
                'provisional' => $provisional,
            ],
            [
                'label' => 'Penyelesaian Ticket IT ringan',
                'value' => $sla->businessHours->formatDuration($kpis['completion_average']),
                'description' => $kpis['completion_count'].' tiket Ticketing Completed dengan durasi valid. '.$kpis['missing_completed_duration'].' Completed tanpa durasi valid.',
                'target' => 'Target rata-rata < 4 jam kerja',
                'target_met' => $kpis['completion_target_met'],
                'provisional' => $provisional,
            ],
        ];
    }
}
