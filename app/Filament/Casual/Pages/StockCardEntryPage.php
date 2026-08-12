<?php

namespace App\Filament\Casual\Pages;

use App\Enums\StockCardStatus;
use App\Models\Employee;
use App\Models\StockCard;
use App\Models\StockCardApproval;
use App\Models\StockCardEmployee;
use App\Models\StockCardEntry;
use App\Models\User;
use App\Services\EsbCoreService;
use App\Services\EsbService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

    /** @var array<int, array{product_code: string, product_name: string, system_unit: string, actual_qty: string, notes: string}> */
    public array $rows = [];

    public string $productSearch = '';

    /** @var list<array{product_code: string, product_name: string, unit: string}> */
    public array $productSearchResults = [];

    public bool $showProductSearch = false;

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

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
            'system_unit' => $entry->system_unit,
            'actual_qty' => $entry->actual_qty !== null ? rtrim(rtrim((string) $entry->actual_qty, '0'), '.') : '',
            'notes' => $entry->notes ?? '',
        ])->values()->all();

        if ($this->isSubmitted) {
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

    public function updatedProductSearch(): void
    {
        $this->searchProducts();
    }

    public function searchProducts(): void
    {
        $query = trim($this->productSearch);

        if ($query === '') {
            $this->productSearchResults = [];

            return;
        }

        try {
            // Search by name via the login-based Core API (matches ESB's documented
            // Product List endpoint), then resolve unit/price detail via the static
            // Master Product token — same combo CreateBomRecipePage already uses.
            $list = app(EsbCoreService::class)->getProducts(['productName' => $query, 'limit' => 20]);
            $codes = array_column($list['data'], 'productCode');
            $details = $codes === [] ? [] : (new EsbService)->getActiveProductDetailsByCodes($codes);
        } catch (\RuntimeException $e) {
            $this->productSearchResults = [];
            Notification::make()->title('Gagal mencari produk')->body($e->getMessage())->danger()->send();

            return;
        }

        $existingCodes = collect($this->rows)->pluck('product_code')->all();

        $this->productSearchResults = collect($details)
            ->reject(fn (array $p) => in_array($p['productCode'], $existingCodes, true))
            ->map(fn (array $p): array => [
                'product_code' => $p['productCode'],
                'product_name' => $p['productName'],
                'unit' => $p['unit'],
            ])
            ->unique(fn (array $p) => $p['product_code'].'|'.$p['unit'])
            ->take(20)
            ->values()
            ->all();
    }

    public function addProduct(string $productCode, string $productName, string $unit): void
    {
        if ($this->isSubmitted) {
            return;
        }

        if (collect($this->rows)->contains('product_code', $productCode)) {
            return;
        }

        $this->rows[] = [
            'product_code' => $productCode,
            'product_name' => $productName,
            'system_unit' => $unit,
            'actual_qty' => '',
            'notes' => '',
        ];

        $this->productSearchResults = collect($this->productSearchResults)
            ->reject(fn (array $p) => $p['product_code'] === $productCode)
            ->values()
            ->all();

        $this->persistDraft();

        Notification::make()->title("\"{$productName}\" ditambahkan ke daftar")->success()->send();
    }

    public function removeProduct(string $productCode): void
    {
        if ($this->isSubmitted) {
            return;
        }

        $this->rows = collect($this->rows)
            ->reject(fn (array $r) => $r['product_code'] === $productCode && $r['actual_qty'] === '')
            ->values()
            ->all();

        $this->persistDraft();
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
                    'system_qty' => null,
                    'system_unit' => $row['system_unit'],
                    'is_manual' => true,
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

        if (empty($this->rows)) {
            Notification::make()->title('Tambahkan minimal satu produk terlebih dahulu')->warning()->send();

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
