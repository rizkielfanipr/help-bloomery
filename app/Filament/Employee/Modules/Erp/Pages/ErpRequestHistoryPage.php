<?php

namespace App\Filament\Employee\Modules\Erp\Pages;

use App\Models\ErpRepairRequest;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class ErpRequestHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.employee.layouts.bare';

    protected string $view = 'filament.employee.erp.pages.erp-request-history-page';

    public ?int $expandedId = null;

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Request ERP';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function toggleItem(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function requests(): Collection
    {
        return ErpRepairRequest::with(['module', 'requestType'])
            ->where('requester_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
