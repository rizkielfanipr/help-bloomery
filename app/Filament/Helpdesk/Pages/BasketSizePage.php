<?php

namespace App\Filament\Helpdesk\Pages;

use App\Filament\Helpdesk\Resources\Branches\BranchResource;
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
    /** Shifts recalculated per request; every shift pulls its sales from ESB, so batches stay short. */
    public const RECALCULATE_BATCH_SIZE = 10;

    /** Shifts accepted per recalculation; larger ranges belong to the basket-size:finalize command. */
    public const RECALCULATE_LIMIT = 200;

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

    /** @var array<int, int> */
    public array $recalculationQueue = [];

    public int $recalculationTotal = 0;

    /** @var array{finalized: int, waiting: int, skipped: int, failed: int} */
    public array $recalculationResult = ['finalized' => 0, 'waiting' => 0, 'skipped' => 0, 'failed' => 0];

    public function mount(): void
    {
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->dateTo = $this->dateTo ?: now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view basket sizes') ?? false;
    }

    private ?Collection $missingShiftBranches = null;

    /**
     * Active branches the user can access that have no active shift configured.
     *
     * @return Collection<int, Branch>
     */
    public function branchesMissingShifts(): Collection
    {
        return $this->missingShiftBranches ??= $this->limitToAccessibleBranches(Branch::query(), 'id')
            ->where('is_active', true)
            ->whereDoesntHave('activeSalesShifts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Supervisors who maintain shifts must fill them in before the basket size data opens.
     */
    public function mustFillShiftsFirst(): bool
    {
        return BranchResource::isShiftEditorOnly() && $this->branchesMissingShifts()->isNotEmpty();
    }

    private ?int $staleRecordCount = null;

    /**
     * Final shifts in the selected filter that were calculated before their branch shifts last changed.
     */
    public function staleRecordCount(): int
    {
        return $this->staleRecordCount ??= $this->recordsInScope()->staleAfterShiftChange()->count();
    }

    /**
     * Supervisors who maintain shifts have to recalculate the outdated basket size before the data opens.
     */
    public function mustRecalculateFirst(): bool
    {
        return BranchResource::isShiftEditorOnly() && $this->canRecalculate() && $this->staleRecordCount() > 0;
    }

    public function canRecalculate(): bool
    {
        return ! $this->mustFillShiftsFirst() && (auth()->user()?->can('recalculate basket sizes') ?? false);
    }

    public function recalculableCount(): int
    {
        return $this->recordsInScope()->count();
    }

    public function startRecalculation(): void
    {
        abort_unless($this->canRecalculate(), 403);

        $this->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ], attributes: ['dateFrom' => 'Dari Tanggal', 'dateTo' => 'Sampai Tanggal']);

        $this->resetRecalculation();
        $ids = $this->recordsInScope()->orderBy('report_date')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            Notification::make()->title('Tidak ada data basket size pada filter ini')->warning()->send();

            return;
        }

        if (count($ids) > self::RECALCULATE_LIMIT) {
            Notification::make()
                ->title('Terlalu banyak shift untuk dihitung ulang sekaligus')
                ->body('Filter ini mencakup '.count($ids).' shift, maksimal '.self::RECALCULATE_LIMIT.' per proses. Persempit rentang tanggal atau pilih cabang, atau jalankan command basket-size:finalize.')
                ->warning()
                ->send();

            return;
        }

        $this->recalculationQueue = $ids;
        $this->recalculationTotal = count($ids);
    }

    public function processRecalculationBatch(BasketSizeFinalizer $finalizer): void
    {
        abort_unless($this->canRecalculate(), 403);

        if ($this->recalculationQueue === []) {
            return;
        }

        $batch = array_map('intval', array_splice($this->recalculationQueue, 0, self::RECALCULATE_BATCH_SIZE));

        $result = $finalizer->process(
            $this->limitToAccessibleBranches(BasketSizeRecord::query())
                ->whereIn('id', $batch)
                ->with(['salesReport.branch.activeSalesShifts', 'salesReport.branch.esbCodes'])
                ->orderBy('report_date')
                ->orderBy('id')
                ->get(),
        );

        foreach (['finalized', 'waiting', 'skipped', 'failed'] as $key) {
            $this->recalculationResult[$key] += $result[$key];
        }

        if ($this->recalculationQueue === []) {
            $this->finishRecalculation();
        }
    }

    private function finishRecalculation(): void
    {
        $result = $this->recalculationResult;

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

        $this->resetRecalculation();
    }

    private function resetRecalculation(): void
    {
        $this->recalculationQueue = [];
        $this->recalculationTotal = 0;
        $this->recalculationResult = ['finalized' => 0, 'waiting' => 0, 'skipped' => 0, 'failed' => 0];
    }

    /**
     * @return Builder<BasketSizeRecord>
     */
    private function recordsInScope(): Builder
    {
        return $this->limitToAccessibleBranches(BasketSizeRecord::query())
            ->when($this->mustFillShiftsFirst(), fn ($query) => $query->whereRaw('1 = 0'))
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
        if ($this->mustFillShiftsFirst() || $this->mustRecalculateFirst()) {
            return collect();
        }

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
        if (! $this->employee || $this->mustFillShiftsFirst() || $this->mustRecalculateFirst()) {
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
