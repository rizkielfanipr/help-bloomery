<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\MemoBranchMappingResolution;
use App\Actions\Rnd\InternalMemo\RefreshInternalMemoMenuAction;
use App\Actions\Rnd\InternalMemo\RemoveMenuFromInternalMemoAction;
use App\Actions\Rnd\InternalMemo\ResolveMemoBranchMappingsAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoBranchesAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMinimumOrdersAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Jobs\SyncInternalMemoMenuCatalogJob;
use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogQuery;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simplified Memo Internal workspace (docs/rnd-internal-memo-simplification-prd.md §12.2, Phase
 * 4). The old status/Syncing/Finalized/revision/archive/PDF controls are gone from the UI —
 * every mutation here only needs the single `update rnd internal memo` permission (or `delete`
 * for deleteMemo), matching the simplified Policy. The underlying workflow columns and Actions
 * (RndInternalMemoPolicy::finalize/archive/etc., FinalizeInternalMemoAction, and so on) still
 * exist for the transition period per PRD §5.2/§15 Phase 5, just unused by this page.
 */
class ViewRndInternalMemo extends ViewRecord
{
    protected static string $resource = RndInternalMemoResource::class;

    protected string $view = 'filament.helpdesk.rnd-internal-memos.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public bool $menuPickerOpen = false;

    public string $menuSearchName = '';

    public string $menuSearchCode = '';

    public ?int $menuBranchFilter = null;

    public string $menuCompanyFilter = '';

    public int $menuPickerPage = 1;

    public int $menuPickerTotal = 0;

    public int $menuPickerPerPage = 10;

    /** @var list<array<string, mixed>> */
    public array $menuPickerRows = [];

    public bool $menuPickerHasNext = false;

    public bool $menuPickerLoading = false;

    public ?string $menuPickerError = null;

    public bool $editMemoModalOpen = false;

    public string $memoTitle = '';

    public string $periodMonth = '';

    public string $memoNumber = '';

    public string $notes = '';

    /** @var list<int> */
    public array $branchIds = [];

    /** Identity key of the Ringkasan Item Akhir row whose Minimum Order is being edited. */
    public ?string $minimumOrderKey = null;

    public string $minimumOrderValue = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_unless(RndInternalMemoResource::canView($this->getRecord()), 403);
    }

    /**
     * Eager-loads each Menu's Materials (ordered the same way the per-Menu structure section
     * renders them) so the workspace page does not issue one extra query per Menu.
     */
    public function menus()
    {
        return $this->getRecord()->menus()
            ->with(['materials' => fn ($query) => $query->orderBy('depth')->orderBy('id')])
            ->get();
    }

    public function canUpdateMemo(): bool
    {
        return auth()->user()?->can('update', $this->getRecord()) ?? false;
    }

    public function openEditMemoModal(): void
    {
        abort_unless($this->canUpdateMemo(), 403);
        $memo = $this->getRecord();
        $this->resetValidation();
        $this->memoTitle = $memo->title;
        $this->periodMonth = $memo->period_month->format('Y-m');
        $this->memoNumber = (string) $memo->memo_number;
        $this->notes = (string) $memo->notes;
        $this->branchIds = $memo->branches()->pluck('branch_id')->map(fn ($id): int => (int) $id)->all();
        $this->editMemoModalOpen = true;
    }

    public function closeEditMemoModal(): void
    {
        $this->resetValidation();
        $this->editMemoModalOpen = false;
    }

    public function saveMemoInfo(UpdateInternalMemoBranchesAction $updateBranches): void
    {
        abort_unless($this->canUpdateMemo(), 403);
        $memo = $this->getRecord();

        $validated = $this->validate([
            'memoTitle' => ['required', 'string', 'max:150'],
            'periodMonth' => ['required', 'date'],
            'memoNumber' => ['required', 'string', 'max:255', 'unique:rnd_internal_memos,memo_number,'.$memo->id],
            'notes' => ['nullable', 'string', 'max:1000'],
            'branchIds' => ['required', 'array', 'min:1'],
            'branchIds.*' => ['integer'],
        ]);

        $periodMonth = Carbon::parse($validated['periodMonth'])->startOfMonth()->toDateString();

        if (
            $memo->revision === 1
            && RndInternalMemo::query()
                ->where('company_code', $memo->company_code)
                ->whereDate('period_month', $periodMonth)
                ->where('revision', 1)
                ->where('id', '!=', $memo->id)
                ->exists()
        ) {
            throw ValidationException::withMessages(['periodMonth' => 'Memo untuk periode ini sudah ada.']);
        }

        $updateBranches->execute($memo, $validated['branchIds'], auth()->user());

        $memo->update([
            'title' => $validated['memoTitle'],
            'period_month' => $periodMonth,
            'memo_number' => $validated['memoNumber'],
            'notes' => $validated['notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        $this->editMemoModalOpen = false;
        Notification::make()->title('Informasi Memo tersimpan')->success()->send();
    }

    /** @return array<int, array{branch:Branch,resolution:MemoBranchMappingResolution}> */
    public function branchOptions(ResolveMemoBranchMappingsAction $resolveMappings): array
    {
        $user = auth()->user();
        $branches = $user->canAccessAllBranches()
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $user->accessibleBranchIds())->orderBy('name')->get();
        $resolutions = $resolveMappings->resolveMany($branches);

        return $branches->map(fn (Branch $branch): array => [
            'branch' => $branch,
            'resolution' => $resolutions[$branch->id],
        ])->all();
    }

    /**
     * Only opens the modal shell; the actual fetch happens in initializeMenuPicker() via
     * wire:init, same two-step pattern as the BOM "Tambah Komponen" picker
     * (ViewProjectProductPage::openInlineProductPicker() + wire:init="loadInlineProducts").
     */
    public function openMenuPicker(): void
    {
        abort_unless($this->canUpdateMemo(), 403);
        $this->menuSearchName = '';
        $this->menuSearchCode = '';
        $this->menuBranchFilter = null;
        $this->menuCompanyFilter = '';
        $this->menuPickerPage = 1;
        $this->menuPickerTotal = 0;
        $this->menuPickerRows = [];
        $this->menuPickerError = null;
        $this->menuPickerOpen = true;
    }

    /**
     * Fired by wire:init after the modal shell is visible. Opening the picker must only fetch the
     * requested page: eagerly warming the full Master Menu catalog made one Livewire request wait
     * for dozens of ESB pages and could exceed PHP-FPM's execution limit.
     */
    public function initializeMenuPicker(): void
    {
        $this->dispatchStaleCatalogSyncs();
        $this->loadMenuPage(1);
    }

    public function closeMenuPicker(): void
    {
        $this->menuPickerOpen = false;
    }

    /** Mirrors the live-search convention of the BOM "Tambah Komponen" picker. */
    public function updatedMenuSearchName(): void
    {
        $this->loadMenuPage(1);
    }

    public function updatedMenuSearchCode(): void
    {
        $this->loadMenuPage(1);
    }

    public function updatedMenuBranchFilter(): void
    {
        $this->loadMenuPage(1);
    }

    public function updatedMenuCompanyFilter(): void
    {
        $this->loadMenuPage(1);
    }

    public function previousMenuPage(): void
    {
        if ($this->menuPickerPage > 1) {
            $this->loadMenuPage($this->menuPickerPage - 1);
        }
    }

    public function nextMenuPage(): void
    {
        if ($this->menuPickerHasNext) {
            $this->loadMenuPage($this->menuPickerPage + 1);
        }
    }

    public function goToMenuPage(int $page): void
    {
        $lastPage = max(1, (int) ceil($this->menuPickerTotal / max(1, $this->menuPickerPerPage)));
        $this->loadMenuPage(min($lastPage, max(1, $page)));
    }

    public function loadMenuPage(int $page, ?InternalMemoMenuCatalogQuery $catalog = null): void
    {
        $catalog ??= app(InternalMemoMenuCatalogQuery::class);
        $this->menuPickerLoading = true;
        $this->menuPickerError = null;

        try {
            $paginator = $catalog->paginate(
                $this->getRecord(),
                10,
                $this->menuSearchName,
                $this->menuSearchCode,
                $this->menuBranchFilter,
                $this->menuCompanyFilter !== '' ? $this->menuCompanyFilter : null,
                $page,
            );
            $this->menuPickerRows = collect($paginator->items())->map(function ($menu) use ($catalog): array {
                return [
                    'menuID' => $menu->menu_id,
                    'menuCode' => $menu->menu_code,
                    'menuName' => $menu->menu_name,
                    'categoryDetail' => $menu->category_detail,
                    'bomID' => $menu->bom_id,
                    'bomName' => $menu->bom_name,
                    'flagActive' => $menu->flag_active,
                    'hasBom' => $menu->bom_id > 0,
                    'companyCode' => $menu->company_code,
                    'branchNames' => $catalog->branchNamesForMenu($this->getRecord(), $menu->company_code, $menu->menu_id),
                    'memoBranchIds' => $catalog->memoBranchIdsForMenu($this->getRecord(), $menu->company_code, $menu->menu_id),
                    'raw' => $menu->raw_snapshot ?? [],
                ];
            })->all();
            $this->menuPickerPage = $paginator->currentPage();
            $this->menuPickerTotal = $paginator->total();
            $this->menuPickerPerPage = $paginator->perPage();
            $this->menuPickerHasNext = $paginator->hasMorePages();

            if ($this->menuPickerRows === [] && $this->getRecord()->branches()->whereIn('catalog_sync_status', ['pending', 'syncing'])->exists()) {
                $this->menuPickerError = 'Katalog Menu sedang disinkronkan. Coba lagi setelah proses selesai.';
            }
        } catch (RuntimeException $exception) {
            $this->menuPickerRows = [];
            $this->menuPickerError = $exception->getMessage();
        } finally {
            $this->menuPickerLoading = false;
        }
    }

    private function dispatchStaleCatalogSyncs(): void
    {
        $this->getRecord()->branches()
            ->where(function ($query): void {
                $query->whereIn('catalog_sync_status', ['pending', 'failed'])
                    ->orWhereNull('catalog_synced_at')
                    ->orWhere('catalog_synced_at', '<', now()->subMinutes(15));
            })
            ->get()
            ->each(fn ($branch) => SyncInternalMemoMenuCatalogJob::dispatch(
                $branch->company_code_snapshot,
                $branch->branch_code_snapshot,
            ));
    }

    /**
     * The ESB Master Menu contract only proves one category-related field, `categoryDetail`
     * (e.g. "BEVERAGES - COFFEE") — confirmed via a live request, Phase 0 of
     * docs/rnd-internal-memo-simplification-prd.md. There is no separate "category" field. Per
     * explicit user confirmation, the picker table splits it on the first " - " into Category
     * (before) and Category Detail (after); a value with no " - " is shown as Category only.
     *
     * @return array{category: ?string, detail: ?string}
     */
    public function splitMenuCategory(?string $categoryDetail): array
    {
        if (blank($categoryDetail)) {
            return ['category' => null, 'detail' => null];
        }

        [$category, $detail] = array_pad(explode(' - ', $categoryDetail, 2), 2, null);

        return ['category' => trim($category), 'detail' => $detail !== null ? trim($detail) : null];
    }

    /** @param array<string, mixed> $menu */
    public function addMenu(array $menu, AddMenuToInternalMemoAction $addMenu): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        try {
            $addMenu->execute($this->getRecord(), $menu);
        } catch (ValidationException $exception) {
            Notification::make()->title('Menu tidak dapat ditambahkan')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            return;
        } catch (RuntimeException $exception) {
            Notification::make()->title('Menu tidak dapat ditambahkan')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->menuPickerOpen = false;
        $this->getRecord()->refresh();
        Notification::make()->title('Menu berhasil ditambahkan')->success()->send();
    }

    public function removeMenu(int $menuId, RemoveMenuFromInternalMemoAction $removeMenu): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        $removeMenu->execute($this->getRecord(), $menuId);

        $this->getRecord()->refresh();
        Notification::make()->title('Menu berhasil dihapus dari Memo')->success()->send();
    }

    public function refreshMenu(int $menuId, RefreshInternalMemoMenuAction $refreshMenu): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        $menu = RndInternalMemoMenu::query()
            ->where('rnd_internal_memo_id', $this->getRecord()->id)
            ->findOrFail($menuId);

        try {
            $refreshMenu->execute($menu);
        } catch (RuntimeException $exception) {
            Notification::make()->title('Menu gagal disegarkan')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->getRecord()->refresh();
        Notification::make()->title('Menu berhasil disegarkan')->success()->send();
    }

    /**
     * Ringkasan Item Akhir (docs/rnd-internal-memo-simplification-prd.md §9.4, §12.2), memoized
     * per request since the Blade view reads it more than once.
     *
     * @var array{bahan: list<array<string, mixed>>, wip: list<array<string, mixed>>, warnings: list<string>}|null
     */
    private ?array $summaryCache = null;

    /** @return array{bahan: list<array<string, mixed>>, wip: list<array<string, mixed>>, warnings: list<string>} */
    public function summary(): array
    {
        return $this->summaryCache ??= app(InternalMemoConsolidationService::class)->consolidateForSummary($this->getRecord());
    }

    public function editMinimumOrder(string $key, ?string $currentValue): void
    {
        abort_unless($this->canUpdateMemo(), 403);
        $this->minimumOrderKey = $key;
        $this->minimumOrderValue = $currentValue ?? '';
    }

    public function cancelMinimumOrder(): void
    {
        $this->minimumOrderKey = null;
        $this->minimumOrderValue = '';
    }

    public function saveMinimumOrder(UpdateInternalMemoMinimumOrdersAction $updateMinimumOrders): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        if ($this->minimumOrderKey === null) {
            return;
        }

        $validated = $this->validate(['minimumOrderValue' => ['nullable', 'numeric', 'min:0']]);
        $value = filled($validated['minimumOrderValue']) ? (float) $validated['minimumOrderValue'] : null;

        $updateMinimumOrders->execute($this->getRecord(), $this->minimumOrderKey, $value);

        $this->minimumOrderKey = null;
        $this->minimumOrderValue = '';
        $this->summaryCache = null;
        Notification::make()->title('Minimum Order tersimpan')->success()->send();
    }

    public function canDeleteMemo(): bool
    {
        return auth()->user()?->can('delete', $this->getRecord()) ?? false;
    }

    public function deleteMemo(DeleteInternalMemoAction $deleteMemo): void
    {
        abort_unless($this->canDeleteMemo(), 403);

        try {
            $deleteMemo->execute($this->getRecord());
        } catch (RuntimeException $exception) {
            Notification::make()->title('Memo tidak dapat dihapus')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Memo Internal berhasil dihapus')->success()->send();
        $this->redirect(RndInternalMemoResource::getUrl('index'), navigate: true);
    }
}
