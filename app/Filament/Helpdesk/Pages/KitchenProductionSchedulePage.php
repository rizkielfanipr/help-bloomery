<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Kitchen\CancelProductionScheduleAction;
use App\Actions\Kitchen\SaveProductionScheduleAction;
use App\Enums\KitchenProductionScheduleStatus;
use App\Models\Branch;
use App\Models\KitchenProductionSchedule;
use App\Models\KitchenProductionScheduleItem;
use App\Models\RndBomCatalog;
use App\Services\Kitchen\ProductionBomEligibilityService;
use App\Services\Kitchen\ProductionScheduleCalendarQuery;
use App\Services\Kitchen\ProductionScheduleDetailBuilder;
use App\Services\Kitchen\ProductionScheduleValidator;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Kitchen → Jadwal Produksi (docs/products/kitchen/production-schedule/prd.md §8, §13–§16). A month calendar
 * with a mobile agenda, one schedule editor whose BOM picker is a step of the same dialog (never a
 * stacked modal), and one detail dialog with an inline cancellation confirmation.
 *
 * The page holds only IDs and typed form values. Every mutation goes through a Kitchen Action that
 * re-authorizes and re-validates on the server, so hidden buttons are a convenience, not the guard.
 * The BOM catalog is read one picker page at a time and never stored in component state.
 */
class KitchenProductionSchedulePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Kitchen';

    protected static ?string $navigationLabel = 'Jadwal Produksi';

    protected static ?string $title = 'Jadwal Produksi';

    protected static ?string $slug = 'kitchen-production-schedules';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.kitchen-production-schedule-page';

    public const PICKER_PAGE_SIZE = 10;

    #[Url(as: 'bulan')]
    public string $month = '';

    #[Url(as: 'branch')]
    public string $filterBranchId = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    /** 'editor' | 'detail' | null — only one dialog is ever open. */
    public ?string $modal = null;

    /** 'form' | 'picker' inside the editor; 'view' | 'cancel' inside the detail. */
    public string $step = 'form';

    public ?int $editingId = null;

    public ?string $editingUpdatedAt = null;

    /** @var array{branch_id: string, production_date: string, notes: string, items: list<array{rnd_bom_catalog_id: int, planned_quantity: string, notes: string, label: string, product: string, uom: string, notice: ?string}>} */
    public array $form = ['branch_id' => '', 'production_date' => '', 'notes' => '', 'items' => []];

    public string $pickerSearch = '';

    public int $pickerPage = 1;

    /** @var list<int> */
    public array $pickerSelected = [];

    public ?int $detailId = null;

    public string $cancelReason = '';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view kitchen production schedules') ?? false;
    }

    /**
     * Re-checked on every Livewire request, so a user who loses the permission while the page is
     * open is refused on the next interaction (§6 rule 8).
     */
    public function booted(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = today()->format('Y-m');
        }
    }

    public function getSubheading(): ?string
    {
        return 'Rencana produksi Kitchen per tanggal. Satu Branch memiliki satu jadwal per hari berisi beberapa BOM Assembly.';
    }

    // ── Month navigation and filters ────────────────────────────────────

    public function previousMonth(): void
    {
        $this->month = $this->selectedMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->selectedMonth()->addMonthNoOverflow()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = today()->format('Y-m');
    }

    public function resetFilters(): void
    {
        $this->filterBranchId = '';
        $this->filterStatus = '';
    }

    #[Computed]
    public function calendarQuery(): ProductionScheduleCalendarQuery
    {
        return app(ProductionScheduleCalendarQuery::class);
    }

    /**
     * @return array{monthLabel: string, weeks: list<list<array{date: CarbonImmutable, isCurrentMonth: bool, isToday: bool, schedules: list<KitchenProductionSchedule>}>>, agenda: list<array{date: CarbonImmutable, schedules: list<KitchenProductionSchedule>}>, total: int, error: bool}
     */
    #[Computed]
    public function calendar(): array
    {
        $month = $this->selectedMonth();
        [$start, $end] = $this->calendarQuery->visibleRange($month);

        try {
            $byDate = $this->calendarQuery
                ->schedules(Auth::user(), $start, $end, ['branch_id' => $this->filterBranchId, 'status' => $this->filterStatus])
                ->groupBy(fn (KitchenProductionSchedule $schedule): string => $schedule->production_date->toDateString());
            $error = false;
        } catch (Throwable $exception) {
            report($exception);
            $byDate = collect();
            $error = true;
        }

        $weeks = [];
        $agenda = [];

        for ($weekStart = $start; $weekStart->lte($end); $weekStart = $weekStart->addWeek()) {
            $week = [];

            for ($offset = 0; $offset < 7; $offset++) {
                $date = $weekStart->addDays($offset);
                $schedules = $byDate->get($date->toDateString(), collect())->values()->all();
                $week[] = [
                    'date' => $date,
                    'isCurrentMonth' => $date->month === $month->month,
                    'isToday' => $date->isToday(),
                    'schedules' => $schedules,
                ];

                if ($schedules !== [] && $date->month === $month->month) {
                    $agenda[] = ['date' => $date, 'schedules' => $schedules];
                }
            }

            $weeks[] = $week;
        }

        return [
            'monthLabel' => $month->locale('id')->translatedFormat('F Y'),
            'weeks' => $weeks,
            'agenda' => $agenda,
            'total' => $byDate->flatten()->count(),
            'error' => $error,
        ];
    }

    /** @return EloquentCollection<int, Branch> */
    #[Computed]
    public function filterBranches(): EloquentCollection
    {
        return $this->calendarQuery->filterBranches(Auth::user());
    }

    /** @return EloquentCollection<int, Branch> */
    #[Computed]
    public function schedulableBranches(): EloquentCollection
    {
        return $this->calendarQuery->schedulableBranches(Auth::user());
    }

    #[Computed]
    public function canCreate(): bool
    {
        return Gate::allows('create', KitchenProductionSchedule::class) && $this->schedulableBranches->isNotEmpty();
    }

    public function hasActiveFilters(): bool
    {
        return $this->filterBranchId !== '' || $this->filterStatus !== '';
    }

    // ── Editor ──────────────────────────────────────────────────────────

    public function openCreate(?string $date = null): void
    {
        abort_unless($this->canCreate, 403);

        $this->resetEditor();
        $this->form['production_date'] = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : today()->toDateString();
        $this->form['branch_id'] = $this->filterBranchId !== '' && $this->schedulableBranches->contains('id', (int) $this->filterBranchId)
            ? $this->filterBranchId
            : ($this->schedulableBranches->count() === 1 ? (string) $this->schedulableBranches->first()->id : '');
        $this->modal = 'editor';
    }

    public function openEdit(int $scheduleId): void
    {
        $schedule = $this->findVisibleSchedule($scheduleId);

        if ($schedule === null || ! Gate::allows('update', $schedule) || $schedule->status !== KitchenProductionScheduleStatus::Scheduled) {
            Notification::make()->title('Jadwal ini tidak dapat diubah.')->danger()->send();

            return;
        }

        $detail = app(ProductionScheduleDetailBuilder::class)->build(Auth::user(), $schedule->id);

        $this->resetEditor();
        $this->editingId = $schedule->id;
        $this->editingUpdatedAt = $schedule->updated_at?->toIso8601String();
        $this->form = [
            'branch_id' => (string) $schedule->branch_id,
            'production_date' => $schedule->production_date->toDateString(),
            'notes' => (string) $schedule->notes,
            'items' => collect($detail['items'])->map(fn (array $row): array => $this->editorRow($row['item'], $row['notice']))->values()->all(),
        ];
        $this->modal = 'editor';
    }

    public function removeItem(int $index): void
    {
        unset($this->form['items'][$index]);
        $this->form['items'] = array_values($this->form['items']);
        $this->resetValidation();
    }

    public function moveItem(int $index, int $direction): void
    {
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($this->form['items'][$index], $this->form['items'][$target])) {
            return;
        }

        [$this->form['items'][$index], $this->form['items'][$target]] = [$this->form['items'][$target], $this->form['items'][$index]];
        $this->resetValidation();
    }

    public function saveSchedule(): void
    {
        $this->resetValidation();
        $input = [
            'branch_id' => $this->form['branch_id'],
            'production_date' => $this->form['production_date'],
            'notes' => $this->form['notes'],
            'items' => collect($this->form['items'])->map(fn (array $item): array => [
                'rnd_bom_catalog_id' => $item['rnd_bom_catalog_id'],
                'planned_quantity' => $item['planned_quantity'],
                'notes' => $item['notes'],
            ])->all(),
        ];

        try {
            app(SaveProductionScheduleAction::class)->execute(
                Auth::user(),
                $input,
                $this->editingId === null ? null : $this->scheduleOrFail($this->editingId),
                $this->editingUpdatedAt,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key === 'schedule' ? 'form' : 'form.'.$key, $messages[0]);
            }

            return;
        } catch (AuthorizationException|ModelNotFoundException) {
            $this->addError('form', 'Anda tidak memiliki akses untuk menyimpan jadwal pada Branch ini.');

            return;
        }

        Notification::make()->title($this->editingId === null ? 'Jadwal produksi disimpan' : 'Jadwal produksi diperbarui')->success()->send();
        $this->closeModal();
        unset($this->calendar);
    }

    // ── BOM picker (a step of the editor dialog) ────────────────────────

    public function openPicker(): void
    {
        $this->pickerSearch = '';
        $this->pickerPage = 1;
        $this->pickerSelected = [];
        $this->step = 'picker';
    }

    public function closePicker(): void
    {
        $this->pickerSelected = [];
        $this->step = 'form';
    }

    public function updatedPickerSearch(): void
    {
        $this->pickerPage = 1;
    }

    public function goToPickerPage(int $page): void
    {
        $this->pickerPage = max(1, $page);
    }

    public function togglePick(int $catalogId): void
    {
        $this->pickerSelected = in_array($catalogId, $this->pickerSelected, true)
            ? array_values(array_diff($this->pickerSelected, [$catalogId]))
            : [...$this->pickerSelected, $catalogId];
    }

    /**
     * Adds the picked BOMs after re-reading them through the eligibility rule, so a row that is no
     * longer eligible (or an ID that never was) is dropped here and refused again on save.
     */
    public function addPicked(): void
    {
        $existing = collect($this->form['items'])->pluck('rnd_bom_catalog_id')->all();
        $ids = array_values(array_diff(array_map('intval', $this->pickerSelected), $existing));
        $room = ProductionScheduleValidator::MAX_ITEMS - count($this->form['items']);

        app(ProductionBomEligibilityService::class)->eligibleQuery()
            ->whereKey(array_slice($ids, 0, max(0, $room)))
            ->get(['id', 'bom_code', 'bom_name', 'product_code', 'product_name', 'uom_name'])
            ->sortBy(fn (RndBomCatalog $catalog): int => array_search($catalog->id, $ids, true))
            ->each(function (RndBomCatalog $catalog): void {
                $this->form['items'][] = [
                    'rnd_bom_catalog_id' => $catalog->id,
                    'planned_quantity' => '',
                    'notes' => '',
                    'label' => trim("{$catalog->bom_code} · {$catalog->bom_name}", ' ·'),
                    'product' => trim("{$catalog->product_code} · {$catalog->product_name}", ' ·'),
                    'uom' => (string) $catalog->uom_name,
                    'notice' => null,
                ];
            });

        $this->closePicker();
    }

    #[Computed]
    public function pickerResults(): LengthAwarePaginator
    {
        return app(ProductionBomEligibilityService::class)->eligibleQuery()
            ->search($this->pickerSearch)
            ->orderBy('bom_name')
            ->orderBy('id')
            ->paginate(self::PICKER_PAGE_SIZE, ['id', 'bom_code', 'bom_name', 'product_code', 'product_name', 'uom_name', 'component_count', 'last_synced_at'], 'pickerPage', $this->pickerPage);
    }

    // ── Detail and cancel ───────────────────────────────────────────────

    public function openDetail(int $scheduleId, string $step = 'view'): void
    {
        if ($this->findVisibleSchedule($scheduleId) === null) {
            Notification::make()->title('Jadwal tidak ditemukan atau tidak dapat diakses.')->danger()->send();

            return;
        }

        $this->modal = 'detail';
        $this->step = in_array($step, ['view', 'cancel'], true) ? $step : 'view';
        $this->detailId = $scheduleId;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    /**
     * @return array{schedule: KitchenProductionSchedule, items: list<array{item: KitchenProductionScheduleItem, notice: ?string}>, canUpdate: bool, canCancel: bool}|null
     */
    #[Computed]
    public function detail(): ?array
    {
        if ($this->detailId === null) {
            return null;
        }

        try {
            return app(ProductionScheduleDetailBuilder::class)->build(Auth::user(), $this->detailId);
        } catch (AuthorizationException|ModelNotFoundException) {
            return null;
        }
    }

    public function showDetailStep(string $step): void
    {
        $this->step = in_array($step, ['view', 'cancel'], true) ? $step : 'view';
        $this->resetValidation();
    }

    public function confirmCancel(): void
    {
        $this->resetValidation();

        try {
            app(CancelProductionScheduleAction::class)->execute(Auth::user(), $this->scheduleOrFail($this->detailId), $this->cancelReason);
        } catch (ValidationException $exception) {
            $this->addError('cancelReason', collect($exception->errors())->flatten()->first());

            return;
        } catch (AuthorizationException|ModelNotFoundException) {
            $this->addError('cancelReason', 'Anda tidak memiliki akses untuk membatalkan jadwal ini.');

            return;
        }

        Notification::make()->title('Jadwal produksi dibatalkan')->success()->send();
        $this->step = 'view';
        $this->cancelReason = '';
        unset($this->detail, $this->calendar);
    }

    public function closeModal(): void
    {
        $this->modal = null;
        $this->step = 'form';
        $this->detailId = null;
        $this->cancelReason = '';
        $this->resetEditor();
        $this->resetValidation();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function resetEditor(): void
    {
        $this->editingId = null;
        $this->editingUpdatedAt = null;
        $this->form = ['branch_id' => '', 'production_date' => '', 'notes' => '', 'items' => []];
        $this->pickerSearch = '';
        $this->pickerPage = 1;
        $this->pickerSelected = [];
        $this->step = 'form';
    }

    /**
     * @return array{rnd_bom_catalog_id: int, planned_quantity: string, notes: string, label: string, product: string, uom: string, notice: ?string}
     */
    private function editorRow(KitchenProductionScheduleItem $item, ?string $notice): array
    {
        return [
            'rnd_bom_catalog_id' => (int) $item->rnd_bom_catalog_id,
            'planned_quantity' => self::formatQuantity($item->planned_quantity),
            'notes' => (string) $item->notes,
            'label' => trim("{$item->bom_code} · {$item->bom_name}", ' ·'),
            'product' => trim("{$item->product_code} · {$item->product_name}", ' ·'),
            'uom' => (string) $item->uom_name,
            'notice' => $notice,
        ];
    }

    private function findVisibleSchedule(int $scheduleId): ?KitchenProductionSchedule
    {
        return KitchenProductionSchedule::query()->visibleTo(Auth::user())->find($scheduleId);
    }

    private function scheduleOrFail(?int $scheduleId): KitchenProductionSchedule
    {
        return KitchenProductionSchedule::query()->findOrFail((int) $scheduleId);
    }

    private function selectedMonth(): CarbonImmutable
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : today()->format('Y-m');

        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
    }

    /**
     * "12.5000" → "12,5" without passing through a float.
     */
    public static function formatQuantity(?string $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            return '';
        }

        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole.','.$fraction;
    }
}
