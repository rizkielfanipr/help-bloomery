<?php

namespace App\Filament\Casual\Pages;

use App\Enums\StockCardStatus;
use App\Models\BranchEsbCode;
use App\Models\Employee;
use App\Models\StockCard;
use App\Models\StockCardApproval;
use App\Models\StockCardEmployee;
use App\Models\StockCardEntry;
use App\Models\User;
use App\Services\EsbStockMovementService;
use App\Services\StockCardCategoryFilter;
use App\Services\StockCardDailySelectionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

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
            'actual_qty' => $entry->actual_qty !== null ? rtrim(rtrim((string) $entry->actual_qty, '0'), '.') : '',
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
            $this->employeeIds = $card->employees->pluck('employee_id')->filter()->values()->all();
        }
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
        $existingRows = collect($this->rows)->keyBy('product_code');

        if ($existingRows->isNotEmpty()) {
            $this->catalogPeriodFrom = $catalog['period_from'];
            $this->catalogPeriodTo = $catalog['period_to'];
            $this->catalogFailedRequests = $catalog['failed_requests'];
            $this->catalogLoading = false;
            $this->catalogLoaded = true;

            return;
        }

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

        $catalogRows = collect($catalog['products'])->map(function (array $product): array {
            return [
                'product_code' => $product['product_code'],
                'product_name' => $product['product_name'],
                'product_category' => $product['category'],
                'system_unit' => $product['unit'],
                'actual_qty' => '',
                'notes' => '',
            ];
        });
        $this->rows = $catalogRows->values()->all();
        $this->catalogPeriodFrom = $catalog['period_from'];
        $this->catalogPeriodTo = $catalog['period_to'];
        $this->catalogFailedRequests = $catalog['failed_requests'];
        $this->catalogLoading = false;
        $this->catalogLoaded = true;

        $this->persistDraft();

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
     * Fires after any live-bound input changes (qty/notes on a row). Saves
     * a draft immediately so an accidental refresh/reload doesn't lose
     * progress — the draft is a normal StockCard/StockCardEntry row with
     * status left at Draft, restored by loadData() on the next mount.
     */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'rows.') || $property === 'employeeIds') {
            $this->persistDraft();
        }
    }

    private function persistDraft(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        $user = auth()->user();
        if (! $user->branch_id) {
            return;
        }

        $card = StockCard::where('branch_id', $user->branch_id)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if (! $card) {
            try {
                $card = StockCard::create([
                    'branch_id' => $user->branch_id,
                    'report_date' => $this->reportDate,
                    'flag_unit' => self::FLAG_UNIT,
                    'status' => StockCardStatus::Draft->value,
                    'category_settings_snapshot' => $this->categorySettingsSnapshot,
                    'selection_snapshot' => $this->selectionSnapshot,
                ]);
            } catch (QueryException) {
                // Another persistDraft() call within the same request already created it.
                $card = StockCard::where('branch_id', $user->branch_id)
                    ->whereDate('report_date', $this->reportDate)
                    ->firstOrFail();
            }
        }

        if ($card->selection_snapshot === null && $this->selectionSnapshot !== []) {
            $card->update(['selection_snapshot' => $this->selectionSnapshot]);
        }

        $currentCodes = collect($this->rows)->pluck('product_code')->all();

        StockCardEntry::where('stock_card_id', $card->id)
            ->whereNotIn('product_code', $currentCodes)
            ->delete();

        foreach ($this->rows as $row) {
            StockCardEntry::updateOrCreate(
                ['stock_card_id' => $card->id, 'product_code' => $row['product_code']],
                [
                    'product_name' => $row['product_name'],
                    'product_category' => $row['product_category'] ?? null,
                    'system_qty' => null,
                    'system_unit' => $row['system_unit'],
                    'is_manual' => false,
                    'actual_qty' => $row['actual_qty'] !== '' ? (float) $row['actual_qty'] : null,
                    'notes' => $row['notes'] !== '' ? $row['notes'] : null,
                ]
            );
        }

        $this->syncEmployees($card);
    }

    private function syncEmployees(StockCard $card): void
    {
        $employees = Employee::query()->whereIn('id', $this->employeeIds)->get();

        $card->employees()->delete();

        if ($employees->isNotEmpty()) {
            $card->employees()->createMany($employees->map(fn (Employee $employee): array => [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'employee_position' => $employee->position,
            ])->all());
        }
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
            if ($row['actual_qty'] === '' || $row['actual_qty'] === null) {
                Notification::make()
                    ->title('Ada qty aktual yang belum diisi')
                    ->body('Pastikan semua item sudah diisi qty aktualnya.')
                    ->warning()
                    ->send();

                return;
            }
        }

        $this->validateEmployees(auth()->user());

        $this->showConfirm = true;
    }

    private function validateEmployees(User $user): void
    {
        $this->validate([
            'employeeIds' => ['required', 'array', 'min:1'],
            'employeeIds.*' => [
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $user->branch_id)
                    ->where('is_active', true)),
            ],
        ], [
            'employeeIds.required' => 'Pilih minimal satu staff yang mengisi Stock Card.',
            'employeeIds.min' => 'Pilih minimal satu staff yang mengisi Stock Card.',
            'employeeIds.*.exists' => 'Staff tidak aktif atau tidak terdaftar pada branch ini.',
        ]);
    }

    public function cancelConfirm(): void
    {
        $this->showConfirm = false;
    }

    public function save(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        $user = auth()->user();

        $this->validateEmployees($user);

        $this->persistDraft();

        $card = StockCard::where('branch_id', $user->branch_id)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if ($card) {
            // Snapshot what staff reported now — this is what the Supervisor
            // will see as the baseline to compare their own correction against.
            $card->entries()->update(['reported_qty' => DB::raw('actual_qty')]);

            $card->update([
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'status' => StockCardStatus::PendingSupervisor->value,
            ]);

            StockCardApproval::create([
                'stock_card_id' => $card->id,
                'stage' => 'submitter',
                'action' => 'submitted',
                'actor_id' => $user->id,
                'notes' => null,
                'revision_number' => $card->revision_number,
            ]);
        }

        $this->status = StockCardStatus::PendingSupervisor;
        $this->isSubmitted = true;
        $this->showConfirm = false;

        // The read-only view right after submit renders from this array, not
        // a fresh mount()/loadData() — populate it so staff names don't
        // appear blank until the next full page load.
        $this->submittedEmployees = Employee::query()
            ->whereIn('id', $this->employeeIds)
            ->get()
            ->map(fn (Employee $employee): array => [
                'code' => $employee->employee_code,
                'name' => $employee->name,
                'position' => $employee->position,
            ])->all();

        Notification::make()->title('Stock Card berhasil disimpan')->success()->send();
    }
}
