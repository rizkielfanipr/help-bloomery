<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Inventory\StockCard\PrepareStockCardDraftAction;
use App\Actions\Inventory\StockCard\SaveStockCardDraftAction;
use App\Actions\Inventory\StockCard\SubmitStockCardAction;
use App\Enums\StockCardStatus;
use App\Models\BranchEsbCode;
use App\Models\Employee;
use App\Models\StockCard;
use App\Models\StockCardEmployee;
use App\Models\StockCardEntry;
use App\Services\EsbStockMovementService;
use App\Services\StockCardCategoryFilter;
use App\Services\StockCardDailySelectionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class StockCardEntryPage extends Page
{
    #[Locked]
    public array $categorySettingsSnapshot = [];

    #[Locked]
    public array $selectionSnapshot = [];

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.stock-card-entry-page';

    #[Url(as: 'date')]
    public string $reportDate = '';

    public StockCardStatus $status = StockCardStatus::Draft;

    public bool $isSubmitted = false;

    public bool $showConfirm = false;

    /** @var list<int> */
    public array $employeeIds = [];

    /** @var list<array{code: ?string, name: ?string, position: ?string}> */
    public array $submittedEmployees = [];

    /**
     * ESB's stock-movement balances are fetched by the Supervisor
     * during back-office review instead — staff just record the physical
     * count before closing, with no "system" quantity to compare against
     * at entry time. Staff can only submit once; any correction after that
     * happens in the back office (see ViewStockCard).
     */
    private const FLAG_UNIT = 'stockUnit';

    /** @var array<int, array{product_code: string, product_name: string, product_category: string, system_unit: string, actual_qty: string, notes: string}> */
    public array $rows = [];

    public bool $catalogLoading = false;

    public bool $catalogLoaded = false;

    public ?string $catalogError = null;

    public string $catalogPeriodFrom = '';

    public string $catalogPeriodTo = '';

    public int $catalogFailedRequests = 0;

    /** @var list<int> */
    #[Locked]
    public array $catalogPairIds = [];

    #[Locked]
    public int $catalogTaskIndex = 0;

    #[Locked]
    public int $catalogTaskTotal = 0;

    public string $catalogCurrentDate = '';

    public string $catalogCurrentCode = '';

    #[Locked]
    public ?string $catalogFetchKey = null;

    /** The date every mutation uses; reportDate stays writable because it is bound to the URL. */
    #[Locked]
    public string $draftDate = '';

    /** @var list<int> Rows changed in the browser and not yet confirmed saved. */
    #[Locked]
    public array $dirtyRowIndexes = [];

    #[Locked]
    public bool $employeesDirty = false;

    /** idle, saved, or failed; only ever set from the outcome of a save on the server. */
    #[Locked]
    public string $draftSaveState = 'idle';

    #[Locked]
    public ?string $draftSavedAt = null;

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

        $this->draftDate = Carbon::parse($this->reportDate)->toDateString();

        $this->catalogPeriodFrom = Carbon::parse($this->reportDate)->toDateString();
        $this->catalogPeriodTo = $this->reportDate;

        $this->loadData();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Stock Card Harian';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    private function loadData(): void
    {
        $user = auth()->user();

        if (! $user->branch_id) {
            return;
        }

        $card = StockCard::with(['entries', 'employees'])
            ->where('branch_id', $user->branch_id)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if (! $card) {
            if ($user->branch) {
                $this->categorySettingsSnapshot = app(StockCardCategoryFilter::class)->snapshot($user->branch);
            }

            return;
        }

        $this->categorySettingsSnapshot = $card->category_settings_snapshot ?? [];
        $this->selectionSnapshot = $card->selection_snapshot ?? [];
        $this->status = $card->status;
        $this->isSubmitted = ! $card->status->canBeEditedBySubmitter();

        $this->rows = $card->entries->map(fn ($entry) => [
            'product_code' => $entry->product_code,
            'product_name' => $entry->product_name,
            'product_category' => $entry->product_category ?? 'Tanpa Kategori',
            'system_unit' => $entry->system_unit,
            'actual_qty' => $entry->actual_qty !== null ? $this->formatQuantity((string) $entry->actual_qty) : '',
            'notes' => $entry->notes ?? '',
        ])->values()->all();

        if ($this->isSubmitted) {
            $this->catalogLoaded = true;
            $this->submittedEmployees = $card->employees->map(fn (StockCardEmployee $e): array => [
                'code' => $e->employee_code,
                'name' => $e->employee_name,
                'position' => $e->employee_position,
            ])->all();
        } else {
            $storedEmployeeIds = $card->employees->pluck('employee_id')->filter()->values()->all();
            $this->employeeIds = $this->employees->pluck('id')->intersect($storedEmployeeIds)->values()->all();
            // A deactivated employee has no checkbox to untick, so replace them on the next save.
            $this->employeesDirty = count($this->employeeIds) !== $card->employees->count();
            $this->draftSaveState = 'saved';
            $this->draftSavedAt = $card->updated_at?->format('H:i');
        }
    }

    private function formatQuantity(string $quantity): string
    {
        return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
    }

    #[Computed]
    public function employees()
    {
        $branchId = auth()->user()?->branch_id;

        return Employee::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'position']);
    }

    public function loadProductCatalog(): void
    {
        if ($this->isSubmitted || $this->catalogLoading || $this->catalogLoaded) {
            return;
        }

        // A draft already holds its product list, so resuming it must not depend on ESB being up.
        if ($this->rows !== []) {
            $this->catalogLoaded = true;

            return;
        }

        $user = auth()->user();
        if (! $user?->branch_id || ! $user->branch?->activeStockCardEsbCode()) {
            $this->catalogError = 'Sumber Stock Card belum diatur untuk Branch ini.';

            return;
        }

        $service = app(EsbStockMovementService::class);
        $cachedCatalog = $service->getCachedStockCardCatalog($user->branch, $this->reportDate, self::FLAG_UNIT);
        if ($cachedCatalog !== null) {
            $this->applyProductCatalog($cachedCatalog);

            return;
        }

        $pairs = collect([$user->branch->activeStockCardEsbCode()])->filter()->values();
        if ($pairs->contains(fn (BranchEsbCode $pair): bool => blank($pair->esb_comcode) || blank($pair->esb_branch_code))) {
            $this->catalogError = 'Mapping Company Code dan ESB Branch Code belum lengkap.';

            return;
        }
        if ($pairs->isEmpty()) {
            $this->catalogError = 'Branch belum memiliki konfigurasi ESB aktif.';

            return;
        }

        $this->catalogLoading = true;
        $this->catalogError = null;
        $this->catalogFailedRequests = 0;
        $this->catalogPairIds = $pairs->pluck('id')->all();
        $this->catalogTaskIndex = 0;
        $this->catalogTaskTotal = count($this->catalogPairIds);
        $this->catalogFetchKey = 'stock-card-catalog-fetch:'.auth()->id().':'.Str::uuid();
        Cache::put($this->catalogFetchKey, [], now()->addHour());

        $this->dispatch('stock-card-fetch-next');
    }

    public function fetchNextCatalogMovement(): void
    {
        if (! $this->catalogLoading || ! $this->catalogFetchKey) {
            return;
        }
        $pair = BranchEsbCode::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->find($this->catalogPairIds[$this->catalogTaskIndex] ?? null);
        if (! $pair) {
            $this->failProductCatalog('Mapping cabang ESB tidak valid.');

            return;
        }
        $to = Carbon::parse($this->reportDate)->toDateString();
        $from = Carbon::parse($this->reportDate)->toDateString();
        $this->catalogCurrentCode = $pair->esb_branch_code;
        $this->catalogCurrentDate = $to;
        try {
            $service = app(EsbStockMovementService::class);
            $products = $service->mergeCatalogRows(
                Cache::get($this->catalogFetchKey, []),
                $service->movements($pair, $from, $to, self::FLAG_UNIT),
                $service->categories($pair->esb_comcode),
                $pair->esb_comcode,
            );
            Cache::put($this->catalogFetchKey, $products, now()->addHour());
        } catch (\Throwable $exception) {
            $this->failProductCatalog($exception->getMessage());

            return;
        }
        $this->catalogTaskIndex++;
        if ($this->catalogTaskIndex < $this->catalogTaskTotal) {
            $this->dispatch('stock-card-fetch-next');

            return;
        }
        $this->finishProductCatalog();
    }

    private function finishProductCatalog(): void
    {
        $products = Cache::get($this->catalogFetchKey, []);
        if ($products === [] && $this->catalogFailedRequests >= $this->catalogTaskTotal) {
            $this->failProductCatalog('Riwayat Stock Movement ESB tidak dapat diambil. Silakan coba kembali.');

            return;
        }

        try {
            $service = app(EsbStockMovementService::class);
            $catalog = $service->buildStockCardProductCatalog(
                $products,
                $this->catalogPeriodFrom,
                $this->catalogPeriodTo,
                $this->catalogFailedRequests,
            );
            $service->cacheStockCardCatalog(auth()->user()->branch, $this->reportDate, self::FLAG_UNIT, $catalog);
            $this->applyProductCatalog($catalog);
        } catch (\RuntimeException $exception) {
            $this->failProductCatalog($exception->getMessage());
        } finally {
            if ($this->catalogFetchKey) {
                Cache::forget($this->catalogFetchKey);
            }
            $this->catalogFetchKey = null;
        }
    }

    /** @param array<string, mixed> $catalog */
    private function applyProductCatalog(array $catalog): void
    {
        $filteredProducts = app(StockCardCategoryFilter::class)->filter($catalog['products'], $this->categorySettingsSnapshot);
        $branch = auth()->user()?->branch;
        if (! $branch) {
            $this->failProductCatalog('Branch pengguna tidak ditemukan.');

            return;
        }

        $selection = app(StockCardDailySelectionService::class)->select(
            $branch,
            $this->reportDate,
            $filteredProducts,
            $this->categorySettingsSnapshot,
        );
        $catalog['products'] = $selection['products'];
        $source = $branch->activeStockCardEsbCode();
        $this->selectionSnapshot = [
            'source' => [
                'branch_esb_code_id' => $source?->id,
                'company_code' => $source?->esb_comcode,
                'branch_code' => $source?->esb_branch_code,
            ],
            'categories' => $selection['summary'],
            'warnings' => $selection['warnings'],
            'generated_at' => now()->toIso8601String(),
        ];

        if (empty($catalog['products'])) {
            $this->catalogLoading = false;
            $this->catalogLoaded = true;
            $this->catalogError = 'Tidak ada produk Stock Movement yang sesuai kategori pada tanggal laporan.';
            Notification::make()->title('Belum ada riwayat Stock Movement')->body($this->catalogError)->warning()->send();

            return;
        }

        $this->catalogPeriodFrom = $catalog['period_from'];
        $this->catalogPeriodTo = $catalog['period_to'];
        $this->catalogFailedRequests = $catalog['failed_requests'];
        $this->catalogLoading = false;
        $this->catalogLoaded = true;

        try {
            app(PrepareStockCardDraftAction::class)->execute(
                auth()->user(),
                $this->draftDate,
                self::FLAG_UNIT,
                collect($catalog['products'])->map(fn (array $product): array => [
                    'product_code' => $product['product_code'],
                    'product_name' => $product['product_name'],
                    'product_category' => $product['category'],
                    'system_unit' => $product['unit'],
                ])->values()->all(),
                $this->categorySettingsSnapshot,
                $this->selectionSnapshot,
            );
        } catch (AuthorizationException) {
            $this->catalogError = 'Akun Anda tidak memiliki izin membuat Stock Card.';

            return;
        }

        $pendingEmployeeIds = $this->employeesDirty ? $this->employeeIds : null;
        $this->loadData();

        if ($pendingEmployeeIds !== null) {
            $this->employeeIds = $pendingEmployeeIds;
            $this->employeesDirty = true;
            $this->persistDirtyDraft();
        }

        if ($this->catalogFailedRequests > 0) {
            Notification::make()
                ->title('Daftar produk dimuat sebagian')
                ->body($this->catalogFailedRequests.' request Stock Movement gagal. Produk dari request lain tetap ditampilkan.')
                ->warning()
                ->send();
        }
    }

    private function failProductCatalog(string $message): void
    {
        if ($this->catalogFetchKey) {
            Cache::forget($this->catalogFetchKey);
        }

        $this->catalogLoading = false;
        $this->catalogFetchKey = null;
        $this->catalogError = $message;
        Notification::make()->title('Gagal memuat daftar produk')->body($message)->danger()->send();
    }

    /**
     * Only a row's qty and notes are user input. Product identity comes from the server, so a request
     * rewriting it, or replacing the whole list, is refused.
     */
    public function updating(string $property): void
    {
        if (preg_match('/^rows(\.|$)/', $property) === 1 && preg_match('/^rows\.\d+\.(actual_qty|notes)$/', $property) !== 1) {
            abort(403);
        }
    }

    /** Autosave after a live-bound input changes, writing only the rows that changed. */
    public function updated(string $property): void
    {
        if (preg_match('/^rows\.(\d+)\.(actual_qty|notes)$/', $property, $match) === 1) {
            $rowIndex = (int) $match[1];

            if (! isset($this->rows[$rowIndex]['product_code'])) {
                unset($this->rows[$rowIndex]);

                return;
            }

            $this->dirtyRowIndexes = collect($this->dirtyRowIndexes)->push($rowIndex)->unique()->values()->all();
        } elseif ($property === 'employeeIds') {
            $this->employeesDirty = true;
        } else {
            return;
        }

        $this->persistDirtyDraft();
    }

    public function saveDraft(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        if ($this->rows === []) {
            Notification::make()->title('Belum ada produk untuk disimpan')->warning()->send();

            return;
        }

        if ($this->persistDirtyDraft()) {
            Notification::make()->title('Draft tersimpan')->success()->send();
        }
    }

    /**
     * Save what changed; a row that fails stays dirty so the next attempt retries it. An invalid qty
     * does not hold back the other rows, and until the product list exists there is nothing to save.
     */
    private function persistDirtyDraft(): bool
    {
        if ($this->isSubmitted || $this->rows === []) {
            return false;
        }

        $this->resetValidation(['employeeIds', 'employeeIds.*']);
        $changes = [];
        $invalidIndexes = [];

        foreach ($this->dirtyRowIndexes as $rowIndex) {
            $row = $this->rows[$rowIndex];
            $this->resetValidation("rows.{$rowIndex}.actual_qty");

            if (! StockCardEntry::isValidQuantity($row['actual_qty'] ?? null)) {
                $invalidIndexes[] = $rowIndex;
                $this->addError("rows.{$rowIndex}.actual_qty", 'Qty harus berupa angka minimal 0 dengan maksimal 4 angka desimal.');

                continue;
            }

            $changes[$rowIndex] = [
                'product_code' => $row['product_code'],
                'actual_qty' => $row['actual_qty'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];
        }

        try {
            $card = app(SaveStockCardDraftAction::class)->execute(
                auth()->user(),
                $this->draftDate,
                $changes,
                $this->employeesDirty ? $this->employeeIds : null,
            );
        } catch (ValidationException $exception) {
            $this->draftSaveState = 'failed';

            if ($exception->validator->errors()->has('status')) {
                $this->handleLockedCard($exception);

                return false;
            }

            throw $exception;
        } catch (AuthorizationException) {
            $this->draftSaveState = 'failed';
            Notification::make()->title('Anda tidak memiliki izin mengubah Stock Card ini')->danger()->send();

            return false;
        } catch (\Throwable $exception) {
            $this->draftSaveState = 'failed';
            $this->logFailure('save_draft', $exception);

            return false;
        }

        $this->dirtyRowIndexes = $invalidIndexes;
        $this->employeesDirty = false;

        if ($invalidIndexes !== []) {
            $this->draftSaveState = 'failed';

            return false;
        }

        $this->draftSaveState = 'saved';
        $this->draftSavedAt = $card->updated_at?->format('H:i');

        return true;
    }

    /** The draft was submitted elsewhere: show the stored, read-only report. */
    private function handleLockedCard(ValidationException $exception): void
    {
        $this->dirtyRowIndexes = [];
        $this->employeesDirty = false;
        $this->showConfirm = false;
        $this->loadData();
        Notification::make()->title($exception->validator->errors()->first('status'))->warning()->send();
    }

    /** No qty, notes, or message: an exception text can carry the submitted values. */
    private function logFailure(string $operation, \Throwable $exception): void
    {
        Log::error('Stock Card employee app mutation failed', [
            'operation' => $operation,
            'branch_id' => auth()->user()?->branch_id,
            'report_date' => $this->draftDate,
            'status' => $this->status->value,
            'exception' => $exception::class,
        ]);
    }

    public function requestConfirm(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        if (! $this->catalogLoaded) {
            Notification::make()->title('Tunggu daftar produk selesai dimuat')->warning()->send();

            return;
        }

        if (empty($this->rows)) {
            Notification::make()->title('Tidak ada produk Stock Movement yang dapat dilaporkan')->warning()->send();

            return;
        }

        foreach ($this->rows as $row) {
            if (blank($row['actual_qty'] ?? null)) {
                Notification::make()
                    ->title('Ada qty aktual yang belum diisi')
                    ->body('Pastikan semua item sudah diisi qty aktualnya.')
                    ->warning()
                    ->send();

                return;
            }
        }

        if ($this->employeeIds === []) {
            $this->addError('employeeIds', 'Pilih minimal satu staff yang mengisi Stock Card.');

            return;
        }

        $this->showConfirm = $this->persistDirtyDraft();
    }

    public function cancelConfirm(): void
    {
        $this->showConfirm = false;
    }

    /**
     * The confirmation is only a courtesy: anything sent here is validated again by the Action.
     */
    public function submit(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        $this->showConfirm = false;

        if (($this->dirtyRowIndexes !== [] || $this->employeesDirty) && ! $this->persistDirtyDraft()) {
            return;
        }

        try {
            app(SubmitStockCardAction::class)->execute(auth()->user(), $this->draftDate);
        } catch (ValidationException $exception) {
            $errors = $exception->validator->errors();

            if ($errors->has('status')) {
                $this->handleLockedCard($exception);

                return;
            }

            if ($errors->has('rows')) {
                Notification::make()->title('Stock Card belum lengkap')->body($errors->first('rows'))->warning()->send();

                return;
            }

            throw $exception;
        } catch (AuthorizationException) {
            Notification::make()->title('Anda tidak memiliki izin mengirim Stock Card ini')->danger()->send();

            return;
        } catch (\Throwable $exception) {
            $this->logFailure('submit', $exception);
            Notification::make()->title('Stock Card gagal dikirim')->body('Draft tetap tersimpan. Silakan coba lagi.')->danger()->send();

            return;
        }

        $this->loadData();

        Notification::make()->title('Stock Card berhasil dikirim untuk review')->success()->send();
    }
}
