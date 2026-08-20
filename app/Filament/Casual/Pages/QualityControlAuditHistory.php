<?php

namespace App\Filament\Casual\Pages;

use App\Models\QualityControlAudit;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class QualityControlAuditHistory extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.quality-control-audit-history';

    public function getTitle(): string|Htmlable
    {
        return 'Riwayat Audit';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view quality control audits'), 403);
    }

    #[Computed]
    public function audits(): Collection
    {
        return QualityControlAudit::where('auditor_id', auth()->id())
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->get();
    }
}
