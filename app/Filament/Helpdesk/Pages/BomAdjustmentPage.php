<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Rnd\Bom\ReconcileBomAdjustmentAction;
use App\Actions\Rnd\Bom\SyncBomCatalogAction;
use App\Jobs\Rnd\SyncBomCatalogJob;
use App\Models\RndBomCatalog;
use App\Models\RndBomChangeLog;
use App\Models\User;
use App\Services\Rnd\Bom\BomChangeComparator;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Search, filter, and paginate the local BOM Assembly catalog, review Change History, and
 * trigger/monitor a background catalog refresh (docs/rnd-bom-adjustment-prd.md §7, §10, §15).
 */
class BomAdjustmentPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'BOM Adjustment';

    protected static ?string $title = 'BOM Adjustment';

    protected static ?string $slug = 'bom-adjustments';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.bom-adjustment';

    #[Url]
    public string $tab = 'assembly';

    public string $search = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public string $syncStatusFilter = '';

    public int $perPage = 20;

    public int $page = 1;

    public string $historySearch = '';

    public string $historyEventFilter = '';

    public string $historyStatusFilter = '';

    public string $historySourceFilter = '';

    public string $historyUserFilter = '';

    public string $historyDateFrom = '';

    public string $historyDateTo = '';

    public int $historyPerPage = 20;

    public int $historyPage = 1;

    public ?int $historyDetailId = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view bill of materials') ?? false;
    }

    public function canViewHistory(): bool
    {
        return Auth::user()?->can('view bom adjustment history') ?? false;
    }

    public function canEdit(): bool
    {
        return Auth::user()?->can('edit bill of materials') ?? false;
    }

    public function canReconcile(): bool
    {
        return Auth::user()?->can('reconcile bom adjustments') ?? false;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['assembly', 'history'], true) ? $tab : 'assembly';
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedUnitFilter(): void
    {
        $this->page = 1;
    }

    public function updatedStatusFilter(): void
    {
        $this->page = 1;
    }

    public function updatedSyncStatusFilter(): void
    {
        $this->page = 1;
    }

    public function updatedPerPage(): void
    {
        $this->page = 1;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->unitFilter = '';
        $this->statusFilter = '';
        $this->syncStatusFilter = '';
        $this->page = 1;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function nextPage(): void
    {
        if ($this->catalogRows()->hasMorePages()) {
            $this->page++;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, min($page, $this->catalogRows()->lastPage()));
    }

    public function catalogRows(): LengthAwarePaginator
    {
        return RndBomCatalog::query()
            ->search($this->search)
            ->when($this->unitFilter !== '', fn ($query) => $query->where('uom_name', $this->unitFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->when($this->syncStatusFilter !== '', fn ($query) => $query->where('sync_status', $this->syncStatusFilter))
            ->orderBy('bom_name')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    public function availableUnits(): array
    {
        return RndBomCatalog::query()->whereNotNull('uom_name')->distinct()->orderBy('uom_name')->pluck('uom_name')->all();
    }

    /** @return Collection<int, User> */
    public function availableHistoryUsers(): Collection
    {
        return User::query()
            ->whereIn('id', RndBomChangeLog::query()->whereNotNull('changed_by')->distinct()->pluck('changed_by'))
            ->orderBy('username')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);
    }

    public function refreshCatalog(): void
    {
        abort_unless($this->canEdit(), 403);

        SyncBomCatalogJob::dispatch(Auth::id());

        Notification::make()->title('Sinkronisasi BOM dimulai di latar belakang')->success()->send();
    }

    public function syncProgress(): ?array
    {
        return SyncBomCatalogAction::progress();
    }

    public function updatedHistorySearch(): void
    {
        $this->historyPage = 1;
    }

    public function updatedHistoryEventFilter(): void
    {
        $this->historyPage = 1;
    }

    public function updatedHistoryStatusFilter(): void
    {
        $this->historyPage = 1;
    }

    public function updatedHistorySourceFilter(): void
    {
        $this->historyPage = 1;
    }

    public function updatedHistoryUserFilter(): void
    {
        $this->historyPage = 1;
    }

    public function updatedHistoryDateFrom(): void
    {
        $this->historyPage = 1;
    }

    public function updatedHistoryDateTo(): void
    {
        $this->historyPage = 1;
    }

    public function resetHistoryFilters(): void
    {
        $this->historySearch = '';
        $this->historyEventFilter = '';
        $this->historyStatusFilter = '';
        $this->historySourceFilter = '';
        $this->historyUserFilter = '';
        $this->historyDateFrom = '';
        $this->historyDateTo = '';
        $this->historyPage = 1;
    }

    public function previousHistoryPage(): void
    {
        if ($this->historyPage > 1) {
            $this->historyPage--;
        }
    }

    public function nextHistoryPage(): void
    {
        if ($this->changeLogRows()->hasMorePages()) {
            $this->historyPage++;
        }
    }

    public function goToHistoryPage(int $page): void
    {
        $this->historyPage = max(1, min($page, $this->changeLogRows()->lastPage()));
    }

    public function changeLogRows(): LengthAwarePaginator
    {
        $term = trim($this->historySearch);

        return RndBomChangeLog::query()
            ->with(['changedBy', 'reconciledBy'])
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('bom_code', 'like', "%{$term}%")
                        ->orWhere('bom_name', 'like', "%{$term}%")
                        ->orWhere('product_code', 'like', "%{$term}%")
                        ->orWhere('product_name', 'like', "%{$term}%")
                        ->orWhere('reason', 'like', "%{$term}%")
                        ->orWhereHas('changedBy', fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%"));
                });
            })
            ->when($this->historyEventFilter !== '', fn ($query) => $query->where('event', $this->historyEventFilter))
            ->when($this->historyStatusFilter !== '', fn ($query) => $query->where('status', $this->historyStatusFilter))
            ->when($this->historySourceFilter !== '', fn ($query) => $query->where('source', $this->historySourceFilter))
            ->when($this->historyUserFilter !== '', fn ($query) => $query->where('changed_by', $this->historyUserFilter))
            ->when($this->historyDateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->historyDateFrom))
            ->when($this->historyDateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->historyDateTo))
            ->orderByDesc('created_at')
            ->paginate($this->historyPerPage, ['*'], 'historyPage', $this->historyPage);
    }

    public function showHistoryDetail(int $changeLogId): void
    {
        abort_unless($this->canViewHistory(), 403);
        $this->historyDetailId = $changeLogId;
    }

    public function closeHistoryDetail(): void
    {
        $this->historyDetailId = null;
    }

    /** @return array{diff: array<string, mixed>|null, is_confirmed: bool, rows: list<array{product: string, unit: string, before: ?float, after: ?float, note: string}>} */
    public function historyDetailDiff(RndBomChangeLog $log): array
    {
        return app(BomChangeComparator::class)->diffRowsForLog($log);
    }

    public function reconcile(int $changeLogId): void
    {
        abort_unless($this->canReconcile(), 403);
        $changeLog = RndBomChangeLog::query()->findOrFail($changeLogId);
        abort_unless(Auth::user()->can('reconcile', $changeLog), 403);

        try {
            $result = app(ReconcileBomAdjustmentAction::class)->execute($changeLog, Auth::user());
            Notification::make()->title('Rekonsiliasi selesai: '.$result->status->getLabel())->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Rekonsiliasi gagal')->body($exception->getMessage())->danger()->send();
        }
    }
}
