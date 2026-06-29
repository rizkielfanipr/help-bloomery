<?php

namespace App\Filament\Casual\Pages;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class TechnicianHistoryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.technician-history-page';

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Teknisi';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    #[Computed]
    public function completedJobs(): Collection
    {
        return ServiceRequest::where('technician_id', auth()->id())
            ->whereIn('status', [
                ServiceRequestStatus::Completed,
                ServiceRequestStatus::Warranty,
            ])
            ->with(['repairs.technician'])
            ->orderByDesc('updated_at')
            ->get();
    }
}
