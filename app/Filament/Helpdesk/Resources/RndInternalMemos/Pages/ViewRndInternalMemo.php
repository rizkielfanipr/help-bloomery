<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\RefreshInternalMemoMenuAction;
use App\Actions\Rnd\InternalMemo\RemoveMenuFromInternalMemoAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoBrandAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMinimumOrdersAction;
use App\Actions\Rnd\ShelfLife\CreateWipShelfLifeAction;
use App\Enums\RndWipShelfLifeSource;
use App\Exceptions\Rnd\WipShelfLifeAlreadyExistsException;
use App\Filament\Helpdesk\Concerns\ManagesWipShelfLifeForm;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Models\Brand;
use App\Models\RndInternalMemoCatalogSync;
use App\Models\RndInternalMemoMaterial;
use App\Models\RndInternalMemoMenu;
use App\Models\RndProductEsbShelfLife;
use App\Services\Rnd\InternalMemo\InternalMemoCatalogContext;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogQuery;
use App\Services\Rnd\InternalMemo\InternalMemoShelfLifeLookup;
use App\Services\Rnd\ShelfLife\WipShelfLifeResolver;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use RuntimeException;

/**
 * Simplified Memo Internal workspace (docs/rnd-internal-memo-simplification-prd.md §12.2, Phase
 * 4). The old status/Syncing/Finalized/revision/archive/PDF controls are gone from the UI —
 * every mutation here only needs the single `update rnd internal memo` permission (or `delete`
 * for deleteMemo), matching the simplified Policy. The underlying workflow columns and Actions
 * (RndInternalMemoPolicy::finalize/archive/etc., FinalizeInternalMemoAction, and so on) still
 * exist for the transition period per PRD §5.2/§15 Phase 5, just unused by this page.
 *
 * docs/rnd-internal-memo-brand-prd.md §12.2–§12.5: the Memo carries one Brand (metadata only) and
 * the Menu picker reads the single global BLSS catalog — no Branch or company state remains here.
 */
class ViewRndInternalMemo extends ViewRecord
{
    use ManagesWipShelfLifeForm;

    protected static string $resource = RndInternalMemoResource::class;

    protected string $view = 'filament.helpdesk.rnd-internal-memos.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public bool $menuPickerOpen = false;

    public string $menuSearchName = '';

    public string $menuSearchCode = '';

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

    public ?int $brandId = null;

    /** Identity key of the Ringkasan Item Akhir row whose Minimum Order is being edited. */
    public ?string $minimumOrderKey = null;

    public string $minimumOrderValue = '';

    /**
     * Product shown in the Minimum Order modal, rebuilt server-side from the summary row of
     * $minimumOrderKey. Locked so the browser cannot swap the displayed product.
     *
     * @var array{product_name: string, product_code: ?string, uom_name: string, purchase_uom_name: ?string, scope_label: string}|null
     */
    #[Locked]
    public ?array $minimumOrderTarget = null;

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

    public function canExportPdf(): bool
    {
        return auth()->user()?->can('exportPdf', $this->getRecord()) ?? false;
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
        $this->brandId = $memo->brand_id;
        $this->editMemoModalOpen = true;
    }

    public function closeEditMemoModal(): void
    {
        $this->resetValidation();
        $this->editMemoModalOpen = false;
    }

    /**
     * Saves the Memo info. The Brand goes through UpdateInternalMemoBrandAction, which only touches
     * `brand_id`/`brand_name_snapshot`; Menus, items, and Minimum Orders are never changed and no
     * sync is queued (docs/rnd-internal-memo-brand-prd.md §8.5, §18.5). On any validation error the
     * modal and the typed values stay.
     */
    public function saveMemoInfo(UpdateInternalMemoBrandAction $updateBrand): void
    {
        abort_unless($this->canUpdateMemo(), 403);
        $memo = $this->getRecord();

        $validated = $this->validate([
            'memoTitle' => ['required', 'string', 'max:150'],
            'periodMonth' => ['required', 'date'],
            'memoNumber' => ['required', 'string', 'max:255', 'unique:rnd_internal_memos,memo_number,'.$memo->id],
            'notes' => ['nullable', 'string', 'max:1000'],
            'brandId' => ['required', 'integer', 'exists:brands,id'],
        ], [
            'brandId.required' => 'Pilih Brand Memo.',
            'brandId.exists' => 'Brand yang dipilih tidak ditemukan.',
        ]);

        $periodMonth = Carbon::parse($validated['periodMonth'])->startOfMonth()->toDateString();
        $brandChanged = $memo->brand_id !== (int) $validated['brandId'];

        try {
            DB::transaction(function () use ($memo, $validated, $periodMonth, $updateBrand): void {
                $updateBrand->execute($memo, $validated['brandId'], auth()->user(), $periodMonth);

                $memo->update([
                    'title' => $validated['memoTitle'],
                    'period_month' => $periodMonth,
                    'memo_number' => $validated['memoNumber'],
                    'notes' => $validated['notes'] ?? null,
                    'updated_by' => auth()->id(),
                ]);
            });
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            foreach (['period_month' => 'periodMonth', 'brand_id' => 'brandId'] as $actionKey => $formKey) {
                if (isset($errors[$actionKey])) {
                    $errors[$formKey] = $errors[$actionKey];
                    unset($errors[$actionKey]);
                }
            }

            throw ValidationException::withMessages($errors);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['periodMonth' => 'Memo Brand ini untuk periode dan revisi ini sudah ada.']);
        }

        $this->editMemoModalOpen = false;
        $this->getRecord()->refresh();
        Notification::make()->title($brandChanged ? 'Brand Memo berhasil diperbarui' : 'Informasi Memo tersimpan')->success()->send();
    }

    /** @return Collection<int, Brand> */
    public function brandOptions(): Collection
    {
        return Brand::query()->orderBy('name')->get(['id', 'name']);
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
        abort_unless($this->canUpdateMemo(), 403);
        app(InternalMemoCatalogContext::class)->requestSync(auth()->id());
        $this->loadMenuPage(1);
    }

    /** Manual "Muat Ulang Katalog": queues the global BLSS sync even when the snapshot is fresh. */
    public function refreshMenuCatalog(InternalMemoCatalogContext $context): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        $queued = $context->requestSync(auth()->id(), force: true);
        Notification::make()
            ->title($queued ? 'Katalog Menu sedang diperbarui' : 'Pembaruan katalog sudah berjalan')
            ->body('Daftar Menu terakhir tetap dapat dipakai selama proses berjalan.')
            ->info()
            ->send();
    }

    /**
     * Global catalog state for the picker header: in progress, failed, stale, last success. Local
     * data only — no ESB request.
     *
     * @return array{syncing: bool, failed: bool, stale: bool, last_synced_at: ?Carbon}
     */
    public function menuCatalogStatus(): array
    {
        $context = app(InternalMemoCatalogContext::class);
        $state = $context->state();

        return [
            'syncing' => $state?->isInProgress() ?? false,
            'failed' => $state?->status === RndInternalMemoCatalogSync::STATUS_FAILED,
            'stale' => $context->isStale($state),
            'last_synced_at' => $state?->last_synced_at,
        ];
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
            $paginator = $catalog->paginate(10, $this->menuSearchName, $this->menuSearchCode, $page);
            $selectedMenuIds = $this->getRecord()->menus()->pluck('esb_menu_id')->map(fn ($id): int => (int) $id)->all();
            $this->menuPickerRows = collect($paginator->items())->map(fn ($menu): array => [
                'menuID' => $menu->menu_id,
                'menuCode' => $menu->menu_code,
                'menuName' => $menu->menu_name,
                'categoryDetail' => $menu->category_detail,
                'bomName' => $menu->bom_name,
                'hasBom' => $menu->bom_id > 0,
                'isSelected' => in_array((int) $menu->menu_id, $selectedMenuIds, true),
            ])->all();
            $this->menuPickerPage = $paginator->currentPage();
            $this->menuPickerTotal = $paginator->total();
            $this->menuPickerPerPage = $paginator->perPage();
            $this->menuPickerHasNext = $paginator->hasMorePages();

        } catch (RuntimeException $exception) {
            $this->menuPickerRows = [];
            $this->menuPickerError = $exception->getMessage();
        } finally {
            $this->menuPickerLoading = false;
        }
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

    /** Only the Menu ID is accepted; the Action re-reads the row from the BLSS catalog. */
    public function addMenu(int $menuId, AddMenuToInternalMemoAction $addMenu): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        try {
            $addMenu->execute($this->getRecord(), $menuId);
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
     * "Refresh Semua": runs the same per-Menu refresh (BOM + Product data from ESB) for every Menu
     * of the Memo. One failing Menu does not stop the others; the result is reported once.
     */
    public function refreshAllMenus(RefreshInternalMemoMenuAction $refreshMenu): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        $failed = [];
        $menus = $this->getRecord()->menus()->get();

        foreach ($menus as $menu) {
            try {
                $refreshMenu->execute($menu);
            } catch (RuntimeException) {
                $failed[] = $menu->menu_name;
            }
        }

        $this->getRecord()->refresh();
        $this->summaryCache = null;
        $this->summaryShelfLifeCache = null;

        if ($failed === []) {
            Notification::make()->title('Semua Menu berhasil disegarkan')->body($menus->count().' Menu diperbarui dari ESB.')->success()->send();

            return;
        }

        Notification::make()
            ->title(($menus->count() - count($failed)).' dari '.$menus->count().' Menu berhasil disegarkan')
            ->body('Gagal: '.implode(', ', $failed).'. Coba lagi untuk Menu tersebut.')
            ->warning()
            ->send();
    }

    /**
     * Ringkasan Item Akhir (docs/rnd-internal-memo-simplification-prd.md §9.4, §12.2), memoized
     * per request since the Blade view reads it more than once.
     *
     * @var array{store: array{wip: list<array<string, mixed>>, bahan: list<array<string, mixed>>}, kitchen: array{wip: list<array<string, mixed>>, bahan: list<array<string, mixed>>}, warnings: list<string>}|null
     */
    private ?array $summaryCache = null;

    /** @return array{store: array{wip: list<array<string, mixed>>, bahan: list<array<string, mixed>>}, kitchen: array{wip: list<array<string, mixed>>, bahan: list<array<string, mixed>>}, warnings: list<string>} */
    public function summary(): array
    {
        return $this->summaryCache ??= app(InternalMemoConsolidationService::class)->consolidateForSummary($this->getRecord());
    }

    /** Per-request cache of the WIP Shelf Life masters shown in the product summary. */
    private ?Collection $summaryShelfLifeCache = null;

    /**
     * WIP Shelf Life masters of the WIPs in the product summary, keyed by Product Detail ID (one
     * bulk query; non-base units resolve to their Product's master). Masters are BLSS, so only WIPs
     * of BLSS Menus are looked up — the Menu's company decides, not the Memo-level column, which a
     * legacy multi-branch Memo may still hold as another company.
     *
     * @return Collection<int, RndProductEsbShelfLife>
     */
    public function summaryShelfLives(): Collection
    {
        return $this->summaryShelfLifeCache ??= app(InternalMemoShelfLifeLookup::class)->masters($this->getRecord());
    }

    /** Filling a missing WIP Shelf Life from the Memo needs both Memo update and Shelf Life manage rights. */
    public function canFillShelfLife(): bool
    {
        return collect(RndWipShelfLifeSource::InternalMemo->requiredPermissions())
            ->every(fn (string $permission): bool => auth()->user()?->can($permission) ?? false);
    }

    public function openMemoShelfLifeModal(int $productDetailId): void
    {
        abort_unless($this->canFillShelfLife(), 403);

        $material = $this->memoWipMaterial($productDetailId);

        if (app(WipShelfLifeResolver::class)->masters([$productDetailId])->has($productDetailId)) {
            $this->summaryShelfLifeCache = null;
            Notification::make()->title('Shelf Life WIP sudah tersedia')->body('Perubahan master dilakukan dari menu Shelf Life.')->info()->send();

            return;
        }

        $this->fillShelfLifeForm([
            'product_detail_id' => $productDetailId,
            'product_code' => $material->product_code,
            'product_name' => $material->product_name,
            'uom_name' => $material->uom_name,
            'purchase_uom_name' => $material->purchase_uom_name,
        ], null);
    }

    /**
     * Creates the missing master only — an existing one is never edited from here (it is edited
     * from the Shelf Life menu). The target WIP is re-verified against this Memo on save.
     */
    public function saveMemoShelfLife(CreateWipShelfLifeAction $createShelfLife): void
    {
        abort_unless($this->canFillShelfLife(), 403);
        abort_if($this->shelfLifeTarget === null, 404);

        $material = $this->memoWipMaterial((int) $this->shelfLifeTarget['product_detail_id']);
        $values = $this->validatedShelfLifeValues();

        try {
            $createShelfLife->execute(RndWipShelfLifeSource::InternalMemo, [
                'company_code' => RndProductEsbShelfLife::DEFAULT_COMPANY_CODE,
                'esb_product_detail_id' => (int) $material->esb_product_detail_id,
                'product_code' => $material->product_code,
                'product_name' => $material->product_name,
                ...$values,
            ], auth()->user());
        } catch (WipShelfLifeAlreadyExistsException) {
            $this->closeShelfLifeModal();
            $this->summaryShelfLifeCache = null;
            Notification::make()->title('Shelf Life WIP sudah diisi pengguna lain')->body('Nilai terbaru ditampilkan. Perubahan master dilakukan dari menu Shelf Life.')->warning()->send();

            return;
        }

        $this->closeShelfLifeModal();
        $this->summaryShelfLifeCache = null;
        Notification::make()->title('Shelf Life WIP tersimpan')->body('Master disimpan lokal dan langsung berlaku di menu Shelf Life, Project, dan Memo lain.')->success()->send();
    }

    /** Server-side check that the Product Detail ID is a WIP of a BLSS Menu of this Memo. */
    private function memoWipMaterial(int $productDetailId): RndInternalMemoMaterial
    {
        $material = app(InternalMemoShelfLifeLookup::class)->blssWipMaterials($this->getRecord())->where('esb_product_detail_id', $productDetailId)->first();

        abort_if($material === null || $productDetailId < 1, 422, 'WIP ini bukan bagian dari Memo.');

        return $material;
    }

    /** Opens the Minimum Order modal for one summary row; product data comes from the server. */
    public function editMinimumOrder(string $key): void
    {
        abort_unless($this->canUpdateMemo(), 403);

        $row = collect($this->summary())
            ->only(['store', 'kitchen'])
            ->flatMap(fn (array $groups): array => [...$groups['wip'], ...$groups['bahan']])
            ->firstWhere('key', $key);
        abort_if($row === null, 404);

        $this->resetValidation();
        $this->minimumOrderKey = $key;
        $this->minimumOrderValue = $row['minimum_order'] === null ? '' : rtrim(rtrim(number_format((float) $row['minimum_order'], 4, '.', ''), '0'), '.');
        $this->minimumOrderTarget = [
            'product_name' => $row['product_name'],
            'product_code' => $row['product_code'],
            'uom_name' => $row['uom_name'],
            'purchase_uom_name' => $row['has_purchase_uom'] ? $row['purchase_uom_name'] : null,
            'scope_label' => ($row['scope'] === 'kitchen' ? 'Kitchen' : 'Store').' · '.($row['is_wip'] ? 'WIP' : 'RAW'),
        ];
    }

    public function cancelMinimumOrder(): void
    {
        $this->resetValidation();
        $this->minimumOrderKey = null;
        $this->minimumOrderValue = '';
        $this->minimumOrderTarget = null;
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
        $this->minimumOrderTarget = null;
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
