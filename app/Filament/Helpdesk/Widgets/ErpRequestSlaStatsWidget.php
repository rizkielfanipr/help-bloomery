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
}
