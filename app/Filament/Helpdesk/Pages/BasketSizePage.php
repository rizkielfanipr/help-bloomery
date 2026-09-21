<?php

namespace App\Filament\Helpdesk\Pages;

use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Models\BasketSizeEmployeeRecord;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Services\BasketSizeFinalizer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class BasketSizePage extends Page
{
    /** Shifts recalculated per click; every shift pulls its sales from ESB. */
    public const RECALCULATE_LIMIT = 30;

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

    public function canRecalculate(): bool
    {
        return auth()->user()?->can('recalculate basket sizes') ?? false;
    }

    public function recalculableCount(): int
    {
        return $this->recordsInScope()->count();
    }

    public function recalculate(BasketSizeFinalizer $finalizer): void
    {
        abort_unless($this->canRecalculate(), 403);

        $this->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ], attributes: ['dateFrom' => 'Dari Tanggal', 'dateTo' => 'Sampai Tanggal']);

        $count = $this->recordsInScope()->count();

        if ($count === 0) {
            Notification::make()->title('Tidak ada data basket size pada filter ini')->warning()->send();

            return;
        }

        if ($count > self::RECALCULATE_LIMIT) {
            Notification::make()
                ->title('Terlalu banyak shift untuk dihitung ulang sekaligus')
                ->body("Filter ini mencakup {$count} shift, maksimal ".self::RECALCULATE_LIMIT.' per proses. Persempit rentang tanggal atau pilih cabang.')
                ->warning()
                ->send();

            return;
        }

        $result = $finalizer->process(
            $this->recordsInScope()
                ->with(['salesReport.branch.activeSalesShifts', 'salesReport.branch.esbCodes'])
                ->orderBy('report_date')
                ->orderBy('id')
                ->get(),
        );

        $summary = collect([
            "{$result['finalized']} shift dihitung ulang",
            $result['waiting'] ? "{$result['waiting']} dilewati karena shift belum berakhir" : null,
            $result['skipped'] ? "{$result['skipped']} dilewati karena cabang tanpa ESB" : null,
            $result['failed'] ? "{$result['failed']} gagal (data ESB belum dapat dimuat)" : null,
        ])->filter()->join(', ');

        Notification::make()
            ->title('Hitung ulang basket size selesai')
            ->body($summary.'.')
            ->color($result['failed'] > 0 ? 'warning' : 'success')
            ->icon($result['failed'] > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
            ->send();
    }

    /**
     * @return Builder<BasketSizeRecord>
     */
    private function recordsInScope(): Builder
    {
        return $this->limitToAccessibleBranches(BasketSizeRecord::query())
            ->when($this->branchId, fn ($query) => $query->where('branch_id', $this->branchId))
            ->whereDate('report_date', '>=', $this->dateFrom)
            ->whereDate('report_date', '<=', $this->dateTo);
    }

    public function branches(): Collection
    {
        return $this->limitToAccessibleBranches(Branch::query(), 'id')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Users without access to every branch only see the branches they can access.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function limitToAccessibleBranches(Builder $query, string $column = 'branch_id'): Builder
    {
        $user = auth()->user();

        if ($user === null || $user->canAccessAllBranches()) {
            return $query;
        }

        return $query->whereIn($column, $user->accessibleBranchIds());
    }

    public function ranking(): Collection
    {
        $employeeTable = (new BasketSizeEmployeeRecord)->getTable();
        $recordTable = (new BasketSizeRecord)->getTable();

        return $this->limitToAccessibleBranches(BasketSizeEmployeeRecord::query(), "{$recordTable}.branch_id")
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
            ->orderByDesc('average_credit')
            ->orderBy("{$employeeTable}.employee_id")
            ->get();
    }

    public function history(): Collection
    {
        if (! $this->employee) {
            return collect();
        }

        return BasketSizeEmployeeRecord::query()
            ->where('employee_id', $this->employee)
            ->whereHas('basketSizeRecord', fn ($query) => $this->limitToAccessibleBranches($query)
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
