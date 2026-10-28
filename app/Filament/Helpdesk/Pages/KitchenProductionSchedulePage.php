<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Kitchen\RequestProductionOrderSyncAction;
use App\Models\Branch;
use App\Models\EsbProductionOrder;
use App\Services\Kitchen\ProductionOrderCalendarQuery;
use App\Services\Kitchen\ProductionOrderDetailBuilder;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Kitchen → Jadwal Produksi (docs/products/kitchen/production-schedule/prd.md §32–§34, §38): a read-only month
 * calendar of ESB Production Orders with a mobile agenda and one detail dialog. It reads local
 * snapshots only; ESB is never called while rendering or opening a detail, and nothing on the page
 * mutates data. The page holds only IDs and filter values; every detail is re-queried inside the
 * Kitchen-Brand scope and re-authorized on the server.
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

    #[Url(as: 'bulan')]
    public string $month = '';

    #[Url(as: 'branch')]
    public string $filterBranchId = '';

    /** ESB status ID from the snapshot. */
    #[Url(as: 'status')]
    public string $filterStatus = '';

    public ?int $productionOrderId = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view kitchen production schedules') ?? false;
    }

    /**
     * Re-checked on every Livewire request, so a user who loses the permission while the page is
     * open is refused on the next interaction (§32).
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
        return 'Production Order dari ESB per tanggal produksi. Data hanya dapat dilihat; perubahan dilakukan di ESB.';
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

    public function hasActiveFilters(): bool
    {
        return $this->filterBranchId !== '' || $this->filterStatus !== '';
    }

    #[Computed]
    public function calendarQuery(): ProductionOrderCalendarQuery
    {
        return app(ProductionOrderCalendarQuery::class);
    }

    /**
     * @return array{monthLabel: string, weeks: list<list<array{date: CarbonImmutable, isCurrentMonth: bool, isToday: bool, orders: list<EsbProductionOrder>}>>, agenda: list<array{date: CarbonImmutable, orders: list<EsbProductionOrder>}>, total: int, error: bool}
     */
    #[Computed]
    public function calendar(): array
    {
        $month = $this->selectedMonth();
        [$start, $end] = $this->calendarQuery->visibleRange($month);

        try {
            $byDate = $this->calendarQuery
                ->orders(Auth::user(), $start, $end, ['branch_id' => $this->filterBranchId, 'status' => $this->filterStatus])
                ->groupBy(fn (EsbProductionOrder $order): string => $order->production_order_date->toDateString());
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
                $orders = $byDate->get($date->toDateString(), collect())->values()->all();
                $week[] = [
                    'date' => $date,
                    'isCurrentMonth' => $date->month === $month->month,
                    'isToday' => $date->isToday(),
                    'orders' => $orders,
                ];

                if ($orders !== [] && $date->month === $month->month) {
                    $agenda[] = ['date' => $date, 'orders' => $orders];
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

    /** @return array<string, string> */
    #[Computed]
    public function statusOptions(): array
    {
        return $this->calendarQuery->statusOptions(Auth::user());
    }

    /**
     * @return array{groups: array<string, list<string>>, lastSyncedAt: ?CarbonInterface}|null
     */
    #[Computed]
    public function syncSummary(): ?array
    {
        try {
            return $this->calendarQuery->syncSummary(Auth::user(), $this->filterBranchId !== '' ? (int) $this->filterBranchId : null);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    // ── Manual sync (prd.md §39) ───────────────────────────────────────

    #[Computed]
    public function canSync(): bool
    {
        return Gate::allows('sync', EsbProductionOrder::class);
    }

    /**
     * True while a sync in the user's scope is queued or running; the view then polls (§39.2).
     */
    public function isSyncing(): bool
    {
        return ! empty($this->syncSummary['groups'][ProductionOrderCalendarQuery::STATE_RUNNING] ?? null);
    }

    /**
     * Queues the incremental window for the filtered Kitchen Branch (or every accessible one). The
     * Action re-authorizes and re-checks the Branch; this method only translates the outcome.
     */
    public function syncNow(): void
    {
        $result = app(RequestProductionOrderSyncAction::class)
            ->execute(Auth::user(), $this->filterBranchId !== '' ? (int) $this->filterBranchId : null);

        unset($this->syncSummary);

        $notification = match (true) {
            $result['queued'] > 0 => Notification::make()->title("Sinkronisasi {$result['queued']} cabang diantrekan.")->body('Data akan diperbarui otomatis setelah selesai.')->success(),
            $result['already_active'] > 0 => Notification::make()->title('Sinkronisasi sedang berjalan.')->body('Tunggu hingga selesai sebelum menyinkronkan ulang.')->info(),
            $result['cooling_down'] > 0 => Notification::make()->title('Data baru saja disinkronkan.')->body('Coba lagi dalam beberapa menit.')->warning(),
            default => Notification::make()->title('Tidak ada cabang yang dapat disinkronkan.')->body('Pastikan mapping ESB cabang sudah lengkap.')->warning(),
        };

        $notification->send();
    }

    // ── Production Order detail (read-only) ─────────────────────────────

    public function openProductionOrder(int $orderId): void
    {
        if (! EsbProductionOrder::query()->visibleTo(Auth::user())->whereKey($orderId)->exists()) {
            Notification::make()->title('Production Order tidak ditemukan atau tidak dapat diakses.')->danger()->send();

            return;
        }

        $this->productionOrderId = $orderId;
    }

    /**
     * @return array{order: EsbProductionOrder, notSeenInLatestSync: bool, detailsPending: bool}|null
     */
    #[Computed]
    public function productionOrderDetail(): ?array
    {
        if ($this->productionOrderId === null) {
            return null;
        }

        try {
            return app(ProductionOrderDetailBuilder::class)->build(Auth::user(), $this->productionOrderId);
        } catch (AuthorizationException|ModelNotFoundException) {
            return null;
        }
    }

    public function closeModal(): void
    {
        $this->productionOrderId = null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────

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
