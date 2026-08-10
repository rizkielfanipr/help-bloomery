<?php

namespace App\Filament\Casual\Pages;

use App\Enums\SalesReportStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class SalesReportShiftPage extends Page
{
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

    /** @var array<int, array{name: string, sales_store: string, notes: string}> */
    public array $rows = [];

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

        $report->load(['entries' => fn ($q) => $q->where('shift_number', $this->shiftNumber), 'employees' => fn ($q) => $q->where('shift_number', $this->shiftNumber)]);

        $this->rows = $report->entries->map(fn (SalesReportEntry $entry) => [
            'name' => $entry->payment_method_name,
            'sales_store' => (string) $entry->sales_store_amount,
            'notes' => $entry->notes ?? '',
        ])->values()->all();

        $this->submittedEmployees = $report->employees->map(fn (SalesReportEmployee $e): array => [
            'code' => $e->employee_code,
            'name' => $e->employee_name,
            'position' => $e->employee_position,
        ])->all();
    }

    public function fetchFromEsb(): void
    {
        $user = auth()->user();
        $branch = $user->branch;

        if (! $branch?->esb_branch_code) {
            Notification::make()->title('Branch belum memiliki ESB Branch Code')->warning()->send();

            return;
        }

        $esbToken = $branch->esb_token;

        if (! $esbToken) {
            Notification::make()->title('Token ESB untuk branch ini belum dikonfigurasi')->warning()->send();

            return;
        }

        try {
            $esbRows = app(EsbService::class)->getPaymentSummary(
                $branch->esb_branch_code,
                $this->reportDate,
                $esbToken,
            );

            if (empty($esbRows)) {
                Notification::make()->title('Tidak ada data penjualan ESB untuk tanggal ini')->info()->send();

                return;
            }

            $this->rows = array_map(fn (array $row): array => [
                'name' => $row['name'],
                'sales_store' => '',
                'notes' => '',
            ], $esbRows);

            $this->esbFetched = true;

            Notification::make()->title('Daftar payment method berhasil dimuat')->success()->send();
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
        ], [
            'employeeIds.required' => 'Pilih minimal satu staff yang mengisi Sales Report.',
            'employeeIds.min' => 'Pilih minimal satu staff yang mengisi Sales Report.',
            'employeeIds.*.exists' => 'Staff tidak aktif atau tidak terdaftar pada branch ini.',
        ]);
    }

    public function save(SalesReportReconciliationService $reconciliationService): void
    {
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

        $becameFullySubmitted = false;
        $report = null;

        DB::transaction(function () use ($user, $employees, $branch, &$becameFullySubmitted, &$report): void {
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

            $report->refresh()->load('shiftSubmissions');
            $becameFullySubmitted = $report->allShiftsSubmitted($branch->sales_shift_count);

            if ($becameFullySubmitted) {
                $report->update([
                    'status' => SalesReportStatus::PendingSupervisor->value,
                    'submitted_at' => now(),
                ]);
            }
        });

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

    private function shiftIsUnlocked(): bool
    {
        if ($this->shiftNumber <= 1) {
            return true;
        }

        $branchId = auth()->user()?->branch_id;
        if (! $branchId) {
            return false;
        }

        $report = SalesReport::with('shiftSubmissions')
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        return $report?->isShiftSubmitted($this->shiftNumber - 1) ?? false;
    }
}
