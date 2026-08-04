<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\DriverMealAllowancePeriod;
use App\Models\DriverMealAllowanceSummary;
use App\Models\DriverMealAllowanceTripItem;
use App\Services\DriverMealAllowanceService;
use BackedEnum;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class DriverMealAllowancePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Driver';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Uang Makan Driver';

    protected static ?string $title = 'Uang Makan Driver';

    protected string $view = 'filament.helpdesk.pages.driver-meal-allowance';

    public int $reportYear;

    public int $reportMonth;

    public ?int $selectedPeriodId = null;

    public array $adjustments = [];

    public array $adjustmentReasons = [];

    public array $itemAmounts = [];

    public array $itemIncluded = [];

    public array $itemReasons = [];

    public string $reopenReason = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view driver meal allowance periods') ?? false;
    }

    public function mount(): void
    {
        $this->reportYear = now()->year;
        $this->reportMonth = now()->month;
        $this->selectedPeriodId = DriverMealAllowancePeriod::latest('start_date')->value('id');
        $this->loadInputs();
    }

    public function getPeriodsProperty(): Collection
    {
        return DriverMealAllowancePeriod::query()->latest('start_date')->get();
    }

    public function getSelectedPeriodProperty(): ?DriverMealAllowancePeriod
    {
        if (! $this->selectedPeriodId) {
            return null;
        }

        return DriverMealAllowancePeriod::query()
            ->with(['summaries' => fn ($query) => $query->with(['driver:id,name,username', 'items'])->orderByDesc('final_amount'), 'finalizedBy:id,name'])
            ->find($this->selectedPeriodId);
    }

    public function selectPeriod(int $periodId): void
    {
        abort_unless(DriverMealAllowancePeriod::whereKey($periodId)->exists(), 404);
        $this->selectedPeriodId = $periodId;
        $this->loadInputs();
    }

    public function createPeriod(DriverMealAllowanceService $service): void
    {
        abort_unless(auth()->user()->can('create driver meal allowance periods'), 403);
        $this->validate(['reportYear' => ['required', 'integer', 'min:2020', 'max:2100'], 'reportMonth' => ['required', 'integer', 'between:1,12']]);

        $period = $service->createPeriod($this->reportYear, $this->reportMonth, auth()->id());
        $this->selectedPeriodId = $period->id;
        $this->loadInputs();
        Notification::make()->title('Periode berhasil disiapkan')->success()->send();
    }

    public function syncPeriod(DriverMealAllowanceService $service): void
    {
        abort_unless(auth()->user()->can('edit driver meal allowance periods'), 403);
        $service->sync($this->selectedPeriodOrFail());
        $this->loadInputs();
        Notification::make()->title('Data perjalanan berhasil diperbarui')->success()->send();
    }

    public function saveAdjustment(int $summaryId, DriverMealAllowanceService $service): void
    {
        abort_unless(auth()->user()->can('edit driver meal allowance periods'), 403);
        $summary = $this->summaryOrFail($summaryId);
        $service->updateAdjustment($summary, (float) ($this->adjustments[$summaryId] ?? 0), $this->adjustmentReasons[$summaryId] ?? null, auth()->id());
        $this->loadInputs();
        Notification::make()->title('Penyesuaian berhasil disimpan')->success()->send();
    }

    public function saveItem(int $itemId, DriverMealAllowanceService $service): void
    {
        abort_unless(auth()->user()->can('edit driver meal allowance periods'), 403);
        $item = DriverMealAllowanceTripItem::query()->with('summary.period')->where('period_id', $this->selectedPeriodId)->findOrFail($itemId);
        $service->updateItem($item, (float) ($this->itemAmounts[$itemId] ?? 0), (bool) ($this->itemIncluded[$itemId] ?? false), $this->itemReasons[$itemId] ?? null);
        $this->loadInputs();
        Notification::make()->title('Detail perjalanan berhasil disimpan')->success()->send();
    }

    public function finalizePeriod(DriverMealAllowanceService $service): void
    {
        abort_unless(auth()->user()->can('finalize driver meal allowance periods'), 403);
        $service->finalize($this->selectedPeriodOrFail(), auth()->id());
        Notification::make()->title('Periode berhasil difinalisasi')->success()->send();
    }

    public function reopenPeriod(DriverMealAllowanceService $service): void
    {
        abort_unless(auth()->user()->can('reopen driver meal allowance periods'), 403);
        $this->validate(['reopenReason' => ['required', 'string', 'min:5']]);
        $service->reopen($this->selectedPeriodOrFail(), $this->reopenReason, auth()->id());
        $this->reopenReason = '';
        $this->loadInputs();
        Notification::make()->title('Periode dibuka kembali')->warning()->send();
    }

    public function lateTripCount(): int
    {
        $period = $this->selectedPeriod;

        return $period ? app(DriverMealAllowanceService::class)->lateTripCount($period) : 0;
    }

    /**
     * Build a complete daily ledger. Dates without a trip remain visible with a zero value.
     *
     * @return array<int, array{date: CarbonInterface, item: DriverMealAllowanceTripItem|null}>
     */
    public function dailyRows(DriverMealAllowanceSummary $summary): array
    {
        $period = $this->selectedPeriod;
        if (! $period) {
            return [];
        }

        $itemsByDate = $summary->items
            ->sortBy(fn (DriverMealAllowanceTripItem $item): string => $item->trip_date->format('Y-m-d').' '.$item->trip_code)
            ->groupBy(fn (DriverMealAllowanceTripItem $item): string => $item->trip_date->format('Y-m-d'));

        $rows = [];
        foreach (CarbonPeriod::create($period->start_date, $period->end_date) as $date) {
            $items = $itemsByDate->get($date->format('Y-m-d'));

            if ($items?->isNotEmpty()) {
                foreach ($items as $item) {
                    $rows[] = ['date' => $date->copy(), 'item' => $item];
                }

                continue;
            }

            $rows[] = ['date' => $date->copy(), 'item' => null];
        }

        return $rows;
    }

    private function loadInputs(): void
    {
        $period = $this->selectedPeriod;
        if (! $period) {
            return;
        }

        foreach ($period->summaries as $summary) {
            $this->adjustments[$summary->id] = (float) $summary->adjustment_amount;
            $this->adjustmentReasons[$summary->id] = $summary->adjustment_reason ?? '';
            foreach ($summary->items as $item) {
                $this->itemAmounts[$item->id] = (float) $item->allowance_amount;
                $this->itemIncluded[$item->id] = $item->is_included;
                $this->itemReasons[$item->id] = $item->exclusion_reason ?? '';
            }
        }
    }

    private function selectedPeriodOrFail(): DriverMealAllowancePeriod
    {
        return DriverMealAllowancePeriod::findOrFail($this->selectedPeriodId);
    }

    private function summaryOrFail(int $summaryId): DriverMealAllowanceSummary
    {
        return DriverMealAllowanceSummary::query()->with('period')->where('period_id', $this->selectedPeriodId)->findOrFail($summaryId);
    }
}
