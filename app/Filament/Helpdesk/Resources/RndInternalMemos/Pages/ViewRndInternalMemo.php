<?php

namespace App\Filament\Helpdesk\Resources\RndInternalMemos\Pages;

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\ArchiveInternalMemoAction;
use App\Actions\Rnd\InternalMemo\CreateInternalMemoRevisionAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\FinalizeInternalMemoAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMenuForecastAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMenuShelfLifeAction;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\RndInternalMemoResource;
use App\Jobs\Rnd\GenerateInternalMemoPdfJob;
use App\Jobs\Rnd\SynchronizeInternalMemoJob;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use App\Services\Rnd\InternalMemo\InternalMemoValidationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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

    public ?int $forecastModalMenuId = null;

    public string $forecastQuantity = '0';

    public string $shelfLifeValue = '';

    public string $shelfLifeUnit = '';

    public string $storageCondition = '';

    public string $shelfLifeNotes = '';

    public bool $revisionModalOpen = false;

    public string $revisionMemoNumber = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_unless(RndInternalMemoResource::canView($this->getRecord()), 403);
    }

    /**
     * Eager-loads each Menu's Materials (ordered the same way the per-Menu "Bahan" table
     * renders them) so the workspace page does not issue one extra query per Menu.
     */
    public function menus()
    {
        return $this->getRecord()->menus()
            ->with(['materials' => fn ($query) => $query->orderBy('depth')->orderBy('id')])
            ->get();
    }

    public function openMenuPicker(): void
    {
        abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);
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
        abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);

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

    public function removeMenu(int $menuId): void
    {
        abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);

        $menu = RndInternalMemoMenu::query()
            ->where('rnd_internal_memo_id', $this->getRecord()->id)
            ->findOrFail($menuId);
        $menu->delete();

        $this->getRecord()->refresh();
        Notification::make()->title('Menu berhasil dihapus dari Memo')->success()->send();
    }

    public function canSync(): bool
    {
        return auth()->user()?->can('sync', $this->getRecord()) ?? false;
    }

    /**
     * Flips the memo to Syncing immediately so a second click is blocked in the UI before the
     * queued job even starts (docs/rnd-internal-memo-prd.md §14.4).
     */
    public function runSync(): void
    {
        $memo = $this->getRecord();
        abort_unless(auth()->user()?->can('sync', $memo), 403);

        if ($memo->menus()->doesntExist()) {
            Notification::make()->title('Tambahkan Menu terlebih dahulu sebelum sinkronisasi')->warning()->send();

            return;
        }

        $memo->update(['status' => RndInternalMemoStatus::Syncing]);
        SynchronizeInternalMemoJob::dispatch($memo->id, auth()->id());

        Notification::make()->title('Sinkronisasi BOM dijalankan')->body('Proses berjalan di latar belakang; halaman ini dapat dimuat ulang untuk melihat hasilnya.')->success()->send();
    }

    public function canUpdateForecast(): bool
    {
        return auth()->user()?->can('updateForecast', $this->getRecord()) ?? false;
    }

    public function openForecastModal(int $menuId): void
    {
        abort_unless($this->canUpdateForecast(), 403);

        $menu = RndInternalMemoMenu::query()
            ->where('rnd_internal_memo_id', $this->getRecord()->id)
            ->findOrFail($menuId);

        $this->forecastModalMenuId = $menu->id;
        $this->forecastQuantity = (string) $menu->forecast_quantity;
        $this->shelfLifeValue = $menu->shelf_life_value !== null ? (string) $menu->shelf_life_value : '';
        $this->shelfLifeUnit = (string) $menu->shelf_life_unit;
        $this->storageCondition = (string) $menu->storage_condition;
        $this->shelfLifeNotes = (string) $menu->shelf_life_notes;
    }

    public function closeForecastModal(): void
    {
        $this->forecastModalMenuId = null;
    }

    public function saveForecast(
        UpdateInternalMemoMenuForecastAction $updateForecast,
        UpdateInternalMemoMenuShelfLifeAction $updateShelfLife,
    ): void {
        abort_unless($this->canUpdateForecast(), 403);

        $menu = RndInternalMemoMenu::query()
            ->where('rnd_internal_memo_id', $this->getRecord()->id)
            ->findOrFail($this->forecastModalMenuId);

        $data = $this->validate([
            'forecastQuantity' => ['required', 'numeric', 'min:0'],
            'shelfLifeValue' => ['nullable', 'numeric', 'min:0'],
            'shelfLifeUnit' => ['nullable', 'string', 'max:50'],
            'storageCondition' => ['nullable', 'string', 'max:100'],
            'shelfLifeNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $updateForecast->execute($menu, (float) $data['forecastQuantity']);
            $updateShelfLife->execute($menu, [
                'shelf_life_value' => filled($data['shelfLifeValue']) ? (float) $data['shelfLifeValue'] : null,
                'shelf_life_unit' => filled($data['shelfLifeUnit']) ? $data['shelfLifeUnit'] : null,
                'storage_condition' => filled($data['storageCondition']) ? $data['storageCondition'] : null,
                'shelf_life_notes' => filled($data['shelfLifeNotes']) ? $data['shelfLifeNotes'] : null,
            ]);
        } catch (RuntimeException $exception) {
            Notification::make()->title('Tidak dapat menyimpan')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->forecastModalMenuId = null;
        $this->getRecord()->refresh();
        Notification::make()->title('Forecast dan Shelf Life tersimpan')->success()->send();
    }

    /**
     * Memoized per-request: the Blade view and validation() both need this, and consolidating
     * requires scanning every Material on the memo, so it is computed at most once per render.
     *
     * @var array{rows: list<array<string, mixed>>, warnings: list<string>}|null
     */
    private ?array $consolidatedMaterialsCache = null;

    /** @return array{rows: list<array<string, mixed>>, warnings: list<string>} */
    public function consolidatedMaterials(): array
    {
        return $this->consolidatedMaterialsCache ??= app(InternalMemoConsolidationService::class)->consolidate($this->getRecord());
    }

    /** @return array{blockers: list<string>, warnings: list<string>} */
    public function validation(): array
    {
        return app(InternalMemoValidationService::class)->validate($this->getRecord(), $this->consolidatedMaterials());
    }

    public function canFinalize(): bool
    {
        return auth()->user()?->can('finalize', $this->getRecord()) ?? false;
    }

    public function finalizeMemo(FinalizeInternalMemoAction $finalize): void
    {
        abort_unless($this->canFinalize(), 403);

        try {
            $finalize->execute($this->getRecord(), auth()->user());
        } catch (RuntimeException $exception) {
            Notification::make()->title('Tidak dapat difinalisasi')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->getRecord()->refresh();
        Notification::make()->title('Memo berhasil difinalisasi')->success()->send();
    }

    public function canCreateRevision(): bool
    {
        return auth()->user()?->can('createRevision', $this->getRecord()) ?? false;
    }

    public function openRevisionModal(): void
    {
        abort_unless($this->canCreateRevision(), 403);
        $this->revisionMemoNumber = '';
        $this->revisionModalOpen = true;
    }

    public function closeRevisionModal(): void
    {
        $this->revisionModalOpen = false;
    }

    public function createRevision(CreateInternalMemoRevisionAction $createRevision): void
    {
        abort_unless($this->canCreateRevision(), 403);

        $data = $this->validate(['revisionMemoNumber' => ['required', 'string', 'max:255']]);

        try {
            $revision = $createRevision->execute($this->getRecord(), $data['revisionMemoNumber'], auth()->user());
        } catch (ValidationException $exception) {
            Notification::make()->title('Revisi tidak dapat dibuat')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

            return;
        } catch (RuntimeException $exception) {
            Notification::make()->title('Revisi tidak dapat dibuat')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->revisionModalOpen = false;
        Notification::make()->title('Revisi baru berhasil dibuat')->success()->send();
        $this->redirect(RndInternalMemoResource::getUrl('view', ['record' => $revision]), navigate: true);
    }

    public function canArchive(): bool
    {
        return auth()->user()?->can('archive', $this->getRecord()) ?? false;
    }

    public function canDeleteMemo(): bool
    {
        return auth()->user()?->can('delete', $this->getRecord()) ?? false;
    }

    public function deleteMemo(DeleteInternalMemoAction $deleteMemo): void
    {
        abort_unless($this->canDeleteMemo(), 403);

        $deleteMemo->execute($this->getRecord());

        Notification::make()->title('Memo Internal berhasil dihapus')->success()->send();
        $this->redirect(RndInternalMemoResource::getUrl('index'), navigate: true);
    }

    public function toggleArchive(ArchiveInternalMemoAction $archive): void
    {
        abort_unless($this->canArchive(), 403);

        $archive->execute($this->getRecord(), auth()->user());

        $this->getRecord()->refresh();
        Notification::make()->title($this->getRecord()->status === RndInternalMemoStatus::Archived ? 'Memo diarsipkan' : 'Memo dipulihkan')->success()->send();
    }

    public function canGeneratePdf(): bool
    {
        return auth()->user()?->can('generatePdf', $this->getRecord()) ?? false;
    }

    public function canDownloadPdf(): bool
    {
        return auth()->user()?->can('downloadPdf', $this->getRecord()) ?? false;
    }

    public function generatePdf(): void
    {
        abort_unless($this->canGeneratePdf(), 403);

        GenerateInternalMemoPdfJob::dispatch($this->getRecord()->id, auth()->id());

        Notification::make()->title('PDF sedang dibuat')->body('Proses berjalan di latar belakang; halaman ini dapat dimuat ulang untuk melihat hasilnya.')->success()->send();
    }

    public function documents()
    {
        return $this->getRecord()->documents()->get();
    }
}
