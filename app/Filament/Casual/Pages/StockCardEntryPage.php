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
use App\Services\EsbService;
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
use Livewire\Attributes\Url;

class StockCardEntryPage extends Page
{
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
     * ESB's daily-sales-material-usage report is fetched by the Supervisor
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
    public array $catalogPairIds = [];

    /** @var list<string> */
    public array $catalogDates = [];

    public int $catalogTaskIndex = 0;

    public int $catalogTaskTotal = 0;

    public string $catalogCurrentDate = '';

    public string $catalogCurrentCode = '';

    public ?string $catalogFetchKey = null;

    public string $catalogPhase = 'usage';

    /** @var list<string> */
    public array $catalogCategoryCodes = [];

    public int $catalogCategoryIndex = 0;

    public int $catalogCategoryTotal = 0;

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

        $this->catalogPeriodFrom = Carbon::parse($this->reportDate)->subMonthNoOverflow()->toDateString();
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
            return;
        }

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
        if (! $user?->branch_id || ! $user->branch?->hasEsbIntegration()) {
            $this->catalogError = 'Branch belum memiliki konfigurasi ESB aktif.';

            return;
        }

        $service = new EsbService;
        $cachedCatalog = $service->getCachedStockCardCatalog($user->branch, $this->reportDate, self::FLAG_UNIT);
        if ($cachedCatalog !== null) {
            $this->applyProductCatalog($cachedCatalog);

            return;
        }

        $pairs = $user->branch->activeEsbCodes()
            ->filter(fn (BranchEsbCode $pair): bool => filled($pair->esb_token))
            ->values();
        if ($pairs->isEmpty()) {
            $this->catalogError = 'Branch belum memiliki konfigurasi ESB aktif.';

            return;
        }

        $this->catalogLoading = true;
        $this->catalogError = null;
        $this->catalogFailedRequests = 0;
        $this->catalogPairIds = $pairs->pluck('id')->all();
        $this->catalogDates = collect(Carbon::parse($this->catalogPeriodFrom)->daysUntil($this->catalogPeriodTo))
            ->map(fn (Carbon $date): string => $date->toDateString())
            ->all();
        $this->catalogTaskIndex = 0;
        $this->catalogTaskTotal = count($this->catalogPairIds) * count($this->catalogDates);
        $this->catalogPhase = 'usage';
        $this->catalogCategoryCodes = [];
        $this->catalogCategoryIndex = 0;
        $this->catalogCategoryTotal = 0;
        $this->catalogFetchKey = 'stock-card-catalog-fetch:'.auth()->id().':'.Str::uuid();
        Cache::put($this->catalogFetchKey, [], now()->addHour());

        $this->dispatch('stock-card-fetch-next');
    }

    public function fetchNextCatalogUsage(): void
    {
        if (! $this->catalogLoading || ! $this->catalogFetchKey) {
            return;
        }

        if ($this->catalogPhase === 'category') {
            $this->fetchNextCatalogCategory();

            return;
        }

        if ($this->catalogTaskIndex >= $this->catalogTaskTotal) {
            $this->startCategoryEnrichment();

            return;
        }

        $pairCount = count($this->catalogPairIds);
        if ($pairCount === 0) {
            $this->failProductCatalog('Branch belum memiliki konfigurasi ESB aktif.');

            return;
        }

        $dateIndex = intdiv($this->catalogTaskIndex, $pairCount);
        $pairIndex = $this->catalogTaskIndex % $pairCount;
        $pair = BranchEsbCode::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->where('is_active', true)
            ->find($this->catalogPairIds[$pairIndex]);
        $date = $this->catalogDates[$dateIndex] ?? null;

        $dateIsInPeriod = false;
        if ($date) {
            try {
                $dateIsInPeriod = Carbon::parse($date)->betweenIncluded($this->catalogPeriodFrom, $this->catalogPeriodTo);
            } catch (\Throwable) {
                $dateIsInPeriod = false;
            }
        }

        if (! $pair || ! $dateIsInPeriod || ! $pair->esb_token) {
            $this->catalogFailedRequests++;
        } else {
            $this->catalogCurrentDate = $date;
            $this->catalogCurrentCode = $pair->esb_branch_code;

            try {
                $products = Cache::get($this->catalogFetchKey, []);
                $rows = (new EsbService)->getDailySalesMaterialUsage(
                    $pair->esb_branch_code,
                    $date,
                    self::FLAG_UNIT,
                    $pair->esb_token,
                );

                foreach ($rows as $row) {
                    $productCode = trim((string) ($row['productCode'] ?? ''));
                    $productName = trim((string) ($row['productName'] ?? ''));
                    if ($productCode === '' || $productName === '') {
                        continue;
                    }

                    $products[$productCode] ??= [
                        'product_code' => $productCode,
                        'product_name' => $productName,
                        'category' => trim((string) ($row['categoryName'] ?? $row['productCategoryName'] ?? '')),
                        'unit' => (string) ($row['unit'] ?? $row['unitConversion'] ?? ''),
                        'usage_dates' => [],
                        'total_qty' => 0.0,
                    ];
                    $products[$productCode]['usage_dates'][$date] = true;
                    $products[$productCode]['total_qty'] += (float) ($row['totalQty'] ?? 0);
                }

                Cache::put($this->catalogFetchKey, $products, now()->addHour());
            } catch (\Throwable) {
                $this->catalogFailedRequests++;
            }
        }

        $this->catalogTaskIndex++;

        if ($this->catalogTaskIndex < $this->catalogTaskTotal) {
            $this->dispatch('stock-card-fetch-next');

            return;
        }

        $this->startCategoryEnrichment();
    }

    private function startCategoryEnrichment(): void
    {
        $products = Cache::get($this->catalogFetchKey, []);
        if ($products === [] && $this->catalogFailedRequests >= $this->catalogTaskTotal) {
            $this->failProductCatalog('Riwayat Daily Usage ESB tidak dapat diambil. Silakan coba kembali.');

            return;
        }

        $this->catalogPhase = 'category';
        $this->catalogCategoryCodes = array_values(array_keys($products));
        $this->catalogCategoryIndex = 0;
        $this->catalogCategoryTotal = (int) ceil(count($this->catalogCategoryCodes) / 8);
        $this->catalogCurrentDate = '';
        $this->catalogCurrentCode = '';

        if ($this->catalogCategoryTotal === 0) {
            $this->finishProductCatalog();

            return;
        }

        $this->dispatch('stock-card-fetch-next');
    }

    private function fetchNextCatalogCategory(): void
    {
        if ($this->catalogCategoryIndex >= $this->catalogCategoryTotal) {
            $this->finishProductCatalog();

            return;
        }

        $productCodes = array_slice($this->catalogCategoryCodes, $this->catalogCategoryIndex * 8, 8);

        try {
            $details = (new EsbService)->getActiveProductDetailsByCodes($productCodes);
            $categoriesByCode = collect($details)
                ->filter(fn (array $detail): bool => filled($detail['productCode'] ?? null))
                ->mapWithKeys(fn (array $detail): array => [
                    (string) $detail['productCode'] => (string) ($detail['categoryName'] ?? ''),
                ]);
            $products = Cache::get($this->catalogFetchKey, []);

            foreach ($productCodes as $productCode) {
                if (isset($products[$productCode])) {
                    $products[$productCode]['category'] = $categoriesByCode->get(
                        $productCode,
                        $products[$productCode]['category'],
                    );
                }
            }

            Cache::put($this->catalogFetchKey, $products, now()->addHour());
        } catch (\Throwable) {
            $this->catalogFailedRequests++;
        }

        $this->catalogCategoryIndex++;

        if ($this->catalogCategoryIndex < $this->catalogCategoryTotal) {
            $this->dispatch('stock-card-fetch-next');

            return;
        }

        $this->finishProductCatalog();
    }

    private function finishProductCatalog(): void
    {
        $products = Cache::get($this->catalogFetchKey, []);
        if ($products === [] && $this->catalogFailedRequests >= $this->catalogTaskTotal) {
            $this->failProductCatalog('Riwayat Daily Usage ESB tidak dapat diambil. Silakan coba kembali.');

            return;
        }

        try {
            $service = new EsbService;
            $catalog = $service->buildStockCardProductCatalog(
                $products,
                $this->catalogPeriodFrom,
                $this->catalogPeriodTo,
                $this->catalogFailedRequests,
                false,
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
        if (empty($catalog['products']) && $existingRows->isEmpty()) {
            $this->catalogLoading = false;
            $this->catalogLoaded = true;
            $this->catalogError = 'Tidak ada produk Daily Usage pada periode satu bulan terakhir.';
            Notification::make()->title('Belum ada riwayat Daily Usage')->body($this->catalogError)->warning()->send();

            return;
        }

        $catalogRows = collect($catalog['products'])->map(function (array $product) use ($existingRows): array {
            $existing = $existingRows->get($product['product_code']);

            return [
                'product_code' => $product['product_code'],
                'product_name' => $product['product_name'],
                'product_category' => $product['category'],
                'system_unit' => $product['unit'],
                'actual_qty' => $existing['actual_qty'] ?? '',
                'notes' => $existing['notes'] ?? '',
            ];
        });
        $catalogCodes = $catalogRows->pluck('product_code');
        $preservedDraftRows = $existingRows->reject(fn (array $row, string $code): bool => $catalogCodes->contains($code));

        $this->rows = $catalogRows->concat($preservedDraftRows)->values()->all();
        $this->catalogPeriodFrom = $catalog['period_from'];
        $this->catalogPeriodTo = $catalog['period_to'];
        $this->catalogFailedRequests = $catalog['failed_requests'];
        $this->catalogLoading = false;
        $this->catalogLoaded = true;

        $this->persistDraft();

        if ($this->catalogFailedRequests > 0) {
            Notification::make()
                ->title('Daftar produk dimuat sebagian')
                ->body($this->catalogFailedRequests.' request Daily Usage gagal. Produk dari request lain tetap ditampilkan.')
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
                ]);
            } catch (QueryException) {
                // Another persistDraft() call within the same request already created it.
                $card = StockCard::where('branch_id', $user->branch_id)
                    ->whereDate('report_date', $this->reportDate)
                    ->firstOrFail();
            }
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
            Notification::make()->title('Tidak ada produk Daily Usage yang dapat dilaporkan')->warning()->send();

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
