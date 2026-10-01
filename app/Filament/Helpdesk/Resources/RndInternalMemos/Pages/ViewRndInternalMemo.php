<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\RefreshInternalMemoMenuAction;
use App\Actions\Rnd\InternalMemo\RemoveMenuFromInternalMemoAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMinimumOrdersAction;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
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

    public int $menuPickerPage = 1;

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
        $this->editMemoModalOpen = true;
    }

    public function closeEditMemoModal(): void
    {
        $this->resetValidation();
        $this->editMemoModalOpen = false;
    }

    public function saveMemoInfo(): void
    {
        abort_unless($this->canUpdateMemo(), 403);
        $memo = $this->getRecord();

        $validated = $this->validate([
            'memoTitle' => ['required', 'string', 'max:150'],
            'periodMonth' => ['required', 'date'],
            'memoNumber' => ['required', 'string', 'max:255', 'unique:rnd_internal_memos,memo_number,'.$memo->id],
            'notes' => ['nullable', 'string', 'max:1000'],
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

    public function openMenuPicker(): void
    {
        abort_unless($this->canUpdateMemo(), 403);
        $this->menuSearchName = '';
        $this->menuSearchCode = '';
        $this->menuPickerPage = 1;
        $this->menuPickerRows = [];
        $this->menuPickerError = null;
        $this->menuPickerOpen = true;
        $this->loadMenuPage(1);
    }

    public function closeMenuPicker(): void
    {
        $this->menuPickerOpen = false;
    }

    public function searchMenus(): void
    {
        $this->loadMenuPage(1);
    }

    public function loadMenuPage(int $page, ?InternalMemoMenuCatalogService $catalog = null): void
    {
        $catalog ??= app(InternalMemoMenuCatalogService::class);
        $this->menuPickerLoading = true;
        $this->menuPickerError = null;

        try {
            $result = $catalog->page($page, 10, $this->menuSearchName, $this->menuSearchCode);
            $this->menuPickerRows = $result['rows'];
            $this->menuPickerPage = $result['page'];
            $this->menuPickerHasNext = $result['hasNext'];
        } catch (RuntimeException $exception) {
            $this->menuPickerRows = [];
            $this->menuPickerError = $exception->getMessage();
        } finally {
            $this->menuPickerLoading = false;
        }
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
