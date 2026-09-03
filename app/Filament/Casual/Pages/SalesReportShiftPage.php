<?php

namespace App\Filament\Casual\Pages;

use App\Actions\CalculateBasketSizeAction;
use App\Enums\SalesReportStatus;
use App\Models\ComplimentType;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportEmployee;
use App\Models\SalesReportEntry;
use App\Models\SalesReportShiftSubmission;
use App\Models\User;
use App\Services\EsbService;
use App\Services\SalesReportReconciliationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

class SalesReportShiftPage extends Page
{
    use WithFileUploads;

    /** Order-type labels always shown after fetching, even with no data. */
    private const LABELS = ['DINE IN', 'TAKEAWAY'];

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.sales-report-shift-page';

    #[Url(as: 'date')]
    public string $reportDate = '';

    #[Url(as: 'shift')]
    public int $shiftNumber = 1;

    public bool $isSubmitted = false;

    public string $currentStatus = 'draft';

    public bool $esbFetched = false;

    public bool $showConfirm = false;

    /** @var array<int, int> */
    public array $employeeIds = [];

    /** @var array<int, array{code: ?string, name: ?string, position: ?string}> */
    public array $submittedEmployees = [];

    /** @var array<int, array{type: string, notes: string, attachments: array<int, string>}> */
    public array $submittedCompliments = [];

    /** @var array<int, array{compliment_type_id: int|null, notes: string, attachments: array<int, mixed>}> */
    public array $compliments = [];

    /** @var array<int, array{label: string, name: string, sales_store: string, notes: string}> */
    public array $rows = [];

    /** @var array<int, array<string, mixed>> */
    public array $transactionSnapshots = [];

    /**
     * Per-label reason to show when a label has no rows after fetching:
     * 'not_configured' (no active ESB pair for that label), 'failed'
     * (pair configured but the ESB call errored), or 'no_transactions'
     * (fetched fine, just genuinely zero sales for the day).
     *
     * @var array<string, string>
     */
    public array $labelStatus = [];

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

        $branch = auth()->user()->branch;
        $this->shiftNumber = $this->shiftNumber >= 1 ? $this->shiftNumber : 1;

        if (! ($branch?->hasSalesShift($this->shiftNumber) ?? $this->shiftNumber === 1)) {
            Notification::make()
                ->title('Shift tidak tersedia')
                ->body('Branch ini tidak memiliki Shift '.$this->shiftNumber.'.')
                ->warning()
                ->send();

            $this->redirect(route('filament.casual.pages.sales-report-page', [
                'reportDate' => $this->reportDate,
            ]), navigate: true);

            return;
        }

        if (! $this->shiftIsUnlocked()) {
            Notification::make()
                ->title('Shift '.$this->shiftNumber.' masih terkunci')
                ->body('Submit laporan Shift '.($this->shiftNumber - 1).' terlebih dahulu.')
                ->warning()
                ->send();

            $this->redirect(route('filament.casual.pages.sales-report-page', [
                'reportDate' => $this->reportDate,
            ]), navigate: true);

            return;
        }

        $this->loadData();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sales Report Shift '.$this->shiftNumber;
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
        $branchId = $user->branch_id;

        if (! $branchId) {
            return;
        }

        $report = SalesReport::with(['shiftSubmissions'])
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if (! $report) {
            return;
        }

        $this->currentStatus = $report->status->value;

        if (! $report->isShiftSubmitted($this->shiftNumber)) {
            return;
        }

        $this->isSubmitted = true;

        $report->load([
            'entries' => fn ($q) => $q->where('shift_number', $this->shiftNumber),
            'employees' => fn ($q) => $q->where('shift_number', $this->shiftNumber),
            'compliments' => fn ($q) => $q->where('shift_number', $this->shiftNumber),
        ]);

        $this->rows = $report->entries->map(fn (SalesReportEntry $entry) => [
            'label' => $entry->label,
            'name' => $entry->payment_method_name,
            'sales_store' => (string) $entry->sales_store_amount,
            'notes' => $entry->notes ?? '',
        ])->values()->all();

        $this->submittedEmployees = $report->employees->map(fn (SalesReportEmployee $e): array => [
            'code' => $e->employee_code,
            'name' => $e->employee_name,
            'position' => $e->employee_position,
        ])->all();

        $this->submittedCompliments = $report->compliments->map(fn ($compliment): array => [
            'type' => $compliment->compliment_type_name,
            'notes' => $compliment->notes,
            'attachments' => $compliment->attachment_paths,
        ])->all();
    }

    public function fetchFromEsb(): void
    {
        $user = auth()->user();
        $branch = $user->branch;

        if (! $branch?->hasEsbIntegration()) {
            Notification::make()->title('Branch belum memiliki konfigurasi ESB')->warning()->send();

            return;
        }

        try {
            [$startTime, $endTime] = $this->shiftWindow($branch);
            $shiftSummary = app(EsbService::class)->getShiftSummaryByLabelForBranch(
                $branch,
                $this->reportDate,
                $startTime,
                $endTime,
            );
            $groups = $shiftSummary['groups'];

            if (empty($groups)) {
                Notification::make()
                    ->title('Branch belum memiliki kode ESB berlabel Dine In / Takeaway')
                    ->body('Hubungi back office untuk melengkapi label kode ESB branch ini.')
                    ->warning()
                    ->send();

                return;
            }

            $rows = [];
            $labelStatus = [];
            $allOk = true;

            foreach (self::LABELS as $label) {
                if (! isset($groups[$label])) {
                    $labelStatus[$label] = 'not_configured';

                    continue;
                }

                $group = $groups[$label];

                if (! $group['ok']) {
                    $allOk = false;
                }

                foreach ($group['rows'] as $row) {
                    $rows[] = ['label' => $label, 'name' => $row['name'], 'sales_store' => '', 'notes' => ''];
                }

                $labelStatus[$label] = match (true) {
                    ! $group['ok'] => 'failed',
                    empty($group['rows']) => 'no_transactions',
                    default => 'ok',
                };
            }

            if (empty($rows)) {
                Notification::make()->title('Tidak ada data penjualan ESB untuk tanggal ini')->info()->send();

                return;
            }

            $this->rows = $rows;
            $this->transactionSnapshots = $shiftSummary['transactions'];
            $this->labelStatus = $labelStatus;
            $this->esbFetched = true;

            if ($allOk) {
                Notification::make()->title('Daftar payment method berhasil dimuat')->success()->send();
            } else {
                Notification::make()
                    ->title('Daftar payment method dimuat sebagian')
                    ->body('Salah satu atau lebih pasangan kode ESB branch ini gagal diambil.')
                    ->warning()
                    ->send();
            }
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Gagal mengambil data ESB')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function totalStore(): float
    {
        return collect($this->rows)->sum(fn ($r) => (float) ($r['sales_store'] ?? 0));
    }

    public function totalStoreForLabel(string $label): float
    {
        return collect($this->rows)
            ->where('label', $label)
            ->sum(fn ($r) => (float) ($r['sales_store'] ?? 0));
    }

    public function requestConfirm(): void
    {
        if (! $this->shiftIsUnlocked() || $this->isSubmitted) {
            return;
        }

        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Akun kamu belum terhubung ke cabang')->danger()->send();

            return;
        }

        if (! $this->esbFetched || empty($this->rows)) {
            Notification::make()->title('Muat daftar payment method terlebih dahulu sebelum menyimpan')->warning()->send();

            return;
        }

        $this->validateRows($user);

        $this->showConfirm = true;
    }

    public function cancelConfirm(): void
    {
        $this->showConfirm = false;
    }

    public function addCompliment(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        $this->compliments[] = [
            'compliment_type_id' => null,
            'notes' => '',
            'attachments' => [],
        ];
    }

    public function removeCompliment(int $index): void
    {
        if ($this->isSubmitted || ! array_key_exists($index, $this->compliments)) {
            return;
        }

        unset($this->compliments[$index]);
        $this->compliments = array_values($this->compliments);
    }

    public function removeComplimentAttachment(int $complimentIndex, int $attachmentIndex): void
    {
        if ($this->isSubmitted || ! isset($this->compliments[$complimentIndex]['attachments'][$attachmentIndex])) {
            return;
        }

        unset($this->compliments[$complimentIndex]['attachments'][$attachmentIndex]);
        $this->compliments[$complimentIndex]['attachments'] = array_values($this->compliments[$complimentIndex]['attachments']);
    }

    private function validateRows(User $user): void
    {
        $this->validate([
            'employeeIds' => ['required', 'array', 'min:1'],
            'employeeIds.*' => [
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $user->branch_id)
                    ->where('is_active', true)),
            ],
            'rows.*.sales_store' => ['nullable', 'numeric', 'min:0'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
            'compliments' => ['array'],
            'compliments.*.compliment_type_id' => [
                'required',
                'integer',
                Rule::exists('compliment_types', 'id')->where('is_active', true),
            ],
            'compliments.*.attachments' => ['required', 'array', 'min:1'],
            'compliments.*.attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'compliments.*.notes' => ['required', 'string', 'max:2000'],
        ], [
            'employeeIds.required' => 'Pilih minimal satu staff yang mengisi Sales Report.',
            'employeeIds.min' => 'Pilih minimal satu staff yang mengisi Sales Report.',
            'employeeIds.*.exists' => 'Staff tidak aktif atau tidak terdaftar pada branch ini.',
            'compliments.*.compliment_type_id.required' => 'Pilih jenis compliment.',
            'compliments.*.compliment_type_id.exists' => 'Jenis compliment sudah tidak aktif atau tidak tersedia.',
            'compliments.*.attachments.required' => 'Upload minimal satu attachment nota.',
            'compliments.*.attachments.min' => 'Upload minimal satu attachment nota.',
            'compliments.*.attachments.*.mimes' => 'Attachment harus berupa JPG, JPEG, PNG, WEBP, atau PDF.',
            'compliments.*.attachments.*.max' => 'Ukuran setiap attachment maksimal 5 MB.',
            'compliments.*.notes.required' => 'Keterangan compliment wajib diisi.',
        ]);
    }

    public function save(
        SalesReportReconciliationService $reconciliationService,
        CalculateBasketSizeAction $calculateBasketSize,
    ): void {
        if (! $this->showConfirm || ! $this->shiftIsUnlocked() || $this->isSubmitted) {
            return;
        }

        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Akun kamu belum terhubung ke cabang')->danger()->send();

            return;
        }

        $this->validateRows($user);

        $employees = Employee::query()->whereIn('id', $this->employeeIds)->get();
        $branch = $user->branch;
        $complimentTypes = ComplimentType::query()
            ->whereIn('id', collect($this->compliments)->pluck('compliment_type_id'))
            ->get()
            ->keyBy('id');

        $storedComplimentPaths = [];
        $becameFullySubmitted = false;
        $report = null;

        try {
            $preparedCompliments = collect($this->compliments)->map(function (array $compliment) use ($user, $complimentTypes, &$storedComplimentPaths): array {
                $attachmentPaths = collect($compliment['attachments'])->map(function ($attachment) use (&$storedComplimentPaths): string {
                    $path = $attachment->store('sales-report-compliments', 'b2');
                    $storedComplimentPaths[] = $path;

                    return $path;
                })->all();

                return [
                    'shift_number' => $this->shiftNumber,
                    'compliment_type_id' => $compliment['compliment_type_id'],
                    'compliment_type_name' => $complimentTypes[$compliment['compliment_type_id']]->name,
                    'attachment_paths' => $attachmentPaths,
                    'notes' => trim($compliment['notes']),
                    'submitted_by' => $user->id,
                ];
            })->all();

            DB::transaction(function () use ($user, $employees, $branch, $calculateBasketSize, $preparedCompliments, &$becameFullySubmitted, &$report): void {
                $report = SalesReport::query()
                    ->where('branch_id', $user->branch_id)
                    ->whereDate('report_date', $this->reportDate)
                    ->lockForUpdate()
                    ->first()
                    ?? SalesReport::create([
                        'branch_id' => $user->branch_id,
                        'report_date' => $this->reportDate,
                        'status' => SalesReportStatus::Draft->value,
                    ]);

                abort_if($report->isShiftSubmitted($this->shiftNumber), 409);

                foreach ($this->rows as $row) {
                    SalesReportEntry::create([
                        'sales_report_id' => $report->id,
                        'shift_number' => $this->shiftNumber,
                        'payment_method_name' => $row['name'],
                        'label' => $row['label'],
                        'sales_store_amount' => (float) ($row['sales_store'] ?? 0),
                        'notes' => trim($row['notes'] ?? '') ?: null,
                    ]);
                }

                SalesReportShiftSubmission::create([
                    'sales_report_id' => $report->id,
                    'shift_number' => $this->shiftNumber,
                    'submitted_by' => $user->id,
                    'submitted_at' => now(),
                ]);

                $report->employees()->where('shift_number', $this->shiftNumber)->delete();
                $report->employees()->createMany($employees->map(fn (Employee $employee): array => [
                    'shift_number' => $this->shiftNumber,
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'employee_position' => $employee->position,
                ])->all());

                $report->compliments()->createMany($preparedCompliments);

                $report->esbTransactions()->where('shift_number', $this->shiftNumber)->delete();
                $report->esbTransactions()->createMany(collect($this->transactionSnapshots)->map(fn (array $transaction): array => [
                    'shift_number' => $this->shiftNumber,
                    'source_branch_code' => $transaction['source_branch_code'] ?? null,
                    'source_comcode' => $transaction['source_comcode'] ?? null,
                    'sales_num' => $transaction['sales_num'],
                    'sales_date_out' => $transaction['sales_date_out'],
                    'payment_total' => $transaction['payment_total'],
                    'pax_total' => $transaction['pax_total'] ?? 0,
                    'revenue_total' => $transaction['revenue_total'] ?? $transaction['payment_total'],
                ])->all());

                $calculateBasketSize->execute($report, $this->shiftNumber);

                $report->refresh()->load('shiftSubmissions');
                $becameFullySubmitted = $report->allShiftsSubmitted($branch->salesShiftNumbers());

                if ($becameFullySubmitted) {
                    $report->update([
                        'status' => SalesReportStatus::PendingSupervisor->value,
                        'submitted_at' => now(),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('b2')->delete($storedComplimentPaths);

            throw $exception;
        }

        if ($becameFullySubmitted && $report) {
            try {
                $reconciliationService->reconcile($report->fresh());
            } catch (\Throwable) {
                // Best-effort: staff's submission already succeeded. A Supervisor
                // can retry the ESB pull manually from the review page.
            }
        }

        $this->isSubmitted = true;
        $this->showConfirm = false;
        $this->currentStatus = $becameFullySubmitted ? SalesReportStatus::PendingSupervisor->value : SalesReportStatus::Draft->value;

        Notification::make()
            ->title('Laporan Shift '.$this->shiftNumber.' berhasil disimpan')
            ->body($becameFullySubmitted
                ? 'Semua shift sudah lengkap, laporan menunggu pemeriksaan Supervisor Store.'
                : 'Menunggu Shift berikutnya sebelum diperiksa Supervisor Store.')
            ->success()
            ->send();
    }

    /** @return array{0:string,1:string} */
    private function shiftWindow($branch): array
    {
        $shift = $branch->configuredSalesShift($this->shiftNumber);
        if ($shift) {
            return [$shift->start_time, $shift->end_time];
        }

        return match ($this->shiftNumber) {
            1 => ['07:00:00', '15:00:00'],
            2 => ['15:00:00', '23:00:00'],
            default => ['00:00:00', '23:59:59'],
        };
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

    #[Computed]
    /** @return Collection<int, ComplimentType> */
    public function complimentTypes(): Collection
    {
        return ComplimentType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function shiftIsUnlocked(): bool
    {
        $branch = auth()->user()?->branch;
        if (! $branch) {
            return false;
        }

        $shiftNumbers = $branch->salesShiftNumbers();
        $shiftIndex = array_search($this->shiftNumber, $shiftNumbers, true);
        if ($shiftIndex === 0) {
            return true;
        }

        if ($shiftIndex === false) {
            return false;
        }

        $report = SalesReport::with('shiftSubmissions')
            ->where('branch_id', $branch->id)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        return $report?->isShiftSubmitted($shiftNumbers[$shiftIndex - 1]) ?? false;
    }
}
