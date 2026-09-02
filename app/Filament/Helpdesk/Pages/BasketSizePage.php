<?php

namespace App\Filament\Helpdesk\Pages;

use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Models\BasketSizeEmployeeRecord;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class BasketSizePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Basket Size';

    protected static ?string $title = 'Basket Size';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.basket-size-page';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public ?int $branchId = null;

    #[Url]
    public ?int $employee = null;

    public function mount(): void
    {
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->dateTo = $this->dateTo ?: now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view basket sizes') ?? false;
    }

    public function branches(): Collection
    {
        return Branch::query()->orderBy('name')->get(['id', 'name']);
    }

    public function ranking(): Collection
    {
        $employeeTable = (new BasketSizeEmployeeRecord)->getTable();
        $recordTable = (new BasketSizeRecord)->getTable();

        return BasketSizeEmployeeRecord::query()
            ->join($recordTable, "{$recordTable}.id", '=', "{$employeeTable}.basket_size_record_id")
            ->when($this->branchId, fn ($query) => $query->where("{$recordTable}.branch_id", $this->branchId))
            ->whereDate("{$recordTable}.report_date", '>=', $this->dateFrom)
            ->whereDate("{$recordTable}.report_date", '<=', $this->dateTo)
            ->selectRaw("{$employeeTable}.employee_id, {$employeeTable}.employee_code, {$employeeTable}.employee_name, {$employeeTable}.employee_position")
            ->selectRaw("COUNT(*) as shift_count, SUM({$employeeTable}.basket_size_credit) as total_credit, AVG({$employeeTable}.basket_size_credit) as average_credit")
            ->groupBy([
                "{$employeeTable}.employee_id",
                "{$employeeTable}.employee_code",
                "{$employeeTable}.employee_name",
                "{$employeeTable}.employee_position",
            ])
            ->orderByDesc('total_credit')
            ->get();
    }

    public function history(): Collection
    {
        if (! $this->employee) {
            return collect();
        }

        return BasketSizeEmployeeRecord::query()
            ->where('employee_id', $this->employee)
            ->whereHas('basketSizeRecord', fn ($query) => $query
                ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
                ->whereDate('report_date', '>=', $this->dateFrom)
                ->whereDate('report_date', '<=', $this->dateTo))
            ->with(['basketSizeRecord.branch', 'salesReport'])
            ->latest('id')
            ->get();
    }

    public function salesReportUrl(int $salesReportId): string
    {
        return SalesReportResource::getUrl('view', ['record' => $salesReportId]);
    }
}
