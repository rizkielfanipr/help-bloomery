<?php

namespace App\Filament\Casual\Pages;

use App\Models\Branch;
use App\Models\TechnicianMaintenance;
use App\Models\TechnicianMaintenanceChecklist;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class TechnicianMaintenancePage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.technician-maintenance-page';

    public ?int $branchId = null;

    public string $checkedAt = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view technician monthly maintenance'), 403);
        $this->branchId = auth()->user()->primaryBranchId();
        $this->checkedAt = now()->toDateString();
    }

    public function branches(): Collection
    {
        return Branch::query()->whereIn('id', auth()->user()->accessibleBranchIds())->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function maintenances(): Collection
    {
        return TechnicianMaintenance::query()->where('technician_id', auth()->id())->with('branch')->latest()->get();
    }

    public function startCurrentMonth(): void
    {
        abort_unless(auth()->user()?->can('create technician monthly maintenance'), 403);
        $this->validate(['branchId' => ['required', 'integer', 'exists:branches,id'], 'checkedAt' => ['required', 'date']]);
        abort_unless(auth()->user()->canAccessBranch($this->branchId), 403);

        $year = now()->year;
        $month = now()->month;
        $maintenance = TechnicianMaintenance::firstOrCreate(
            ['branch_id' => $this->branchId, 'maintenance_year' => $year, 'maintenance_month' => $month],
            ['technician_id' => auth()->id(), 'checked_at' => $this->checkedAt, 'status' => 'draft'],
        );

        if ($maintenance->items()->doesntExist()) {
            TechnicianMaintenanceChecklist::query()->where('is_active', true)->orderBy('sort_order')->get()->each(fn (TechnicianMaintenanceChecklist $checklist) => $maintenance->items()->create([
                'checklist_id' => $checklist->id,
                'question' => $checklist->question,
                'check_procedure' => $checklist->check_procedure,
                'requires_photo' => $checklist->requires_photo,
                'sort_order' => $checklist->sort_order,
            ]));
        }

        $this->redirect(TechnicianMaintenanceDetailPage::getUrl(['record' => $maintenance->id], panel: 'casual'));
    }

    public function getTitle(): string
    {
        return 'Maintenance Teknisi';
    }
}
