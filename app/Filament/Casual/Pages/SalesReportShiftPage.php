<?php

namespace App\Filament\Casual\Pages;

use App\Enums\SalesReportStatus;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportApproval;
use App\Models\SalesReportEntry;
use App\Services\EsbService;
use Carbon\CarbonImmutable;
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

    public string $shiftStart = '';

    public string $shiftEnd = '';

    public bool $isSubmitted = false;

    public bool $showConfirm = false;

    public bool $esbFetched = false;

    public bool $showDiscrepancies = false;

    public string $currentStatus = 'draft';

    public ?string $rejectionReason = null;

    public ?int $employeeId = null;

    public ?string $employeeCode = null;

    public ?string $employeeName = null;

    public ?string $employeePosition = null;

    /** @var array<int, array{name: string, sales_system: float, sales_store: string, notes: string}> */
    public array $rows = [];

    /** @var array<int, array{sales_num: string, sales_date_out: string, payment_total: float}> */
    public array $esbTransactions = [];

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

        $this->shiftNumber = in_array($this->shiftNumber, [1, 2], true) ? $this->shiftNumber : 1;

        if (! $this->shiftIsUnlocked()) {
            Notification::make()
                ->title('Shift 2 masih terkunci')
                ->body('Submit laporan Shift 1 terlebih dahulu.')
                ->warning()
                ->send();

            $this->redirect(route('filament.casual.pages.sales-report-page', [
                'reportDate' => $this->reportDate,
            ]), navigate: true);

            return;
        }

        $schedule = auth()->user()->branch?->salesShiftSchedule($this->shiftNumber);
        $this->shiftStart = substr((string) ($schedule['start'] ?? ''), 0, 5);
        $this->shiftEnd = substr((string) ($schedule['end'] ?? ''), 0, 5);

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

        $report = SalesReport::with(['entries', 'esbTransactions'])
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $this->reportDate)
            ->where('shift_number', $this->shiftNumber)
            ->first();

        if (! $report) {
            return;
        }

        $status = $report->status ?? SalesReportStatus::Draft;
        $this->isSubmitted = ! $status->canBeEditedBySubmitter();
        $this->currentStatus = $status->value;
        $this->rejectionReason = $report->rejection_reason;
        $this->employeeId = $report->employee_id;
        $this->employeeCode = $report->employee_code;
        $this->employeeName = $report->employee_name;
        $this->employeePosition = $report->employee_position;
        $this->shiftStart = $report->shift_started_at?->format('H:i') ?? $this->shiftStart;
        $this->shiftEnd = $report->shift_ended_at?->format('H:i') ?? $this->shiftEnd;

        $this->rows = $report->entries->map(fn ($entry) => [
            'name' => $entry->payment_method_name,
            'sales_system' => (float) $entry->sales_system_amount,
            'sales_store' => $entry->sales_store_amount > 0 ? (string) $entry->sales_store_amount : '',
            'notes' => $entry->notes ?? '',
        ])->values()->all();
        $this->esbTransactions = $report->esbTransactions->map(fn ($transaction) => [
            'sales_num' => $transaction->sales_num,
            'sales_date_out' => $transaction->sales_date_out->format('Y-m-d H:i:s'),
            'payment_total' => (float) $transaction->payment_total,
        ])->values()->all();

        if (! empty($this->rows)) {
            $this->esbFetched = true;
        }
    }

    public function fetchFromEsb(): void
    {
        if (! $this->shiftIsUnlocked()) {
            Notification::make()->title('Submit Shift 1 terlebih dahulu')->warning()->send();

            return;
        }

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
            $summary = (new EsbService)->getShiftPaymentSummary(
                $branch->esb_branch_code,
                $this->reportDate,
                $this->shiftStart,
                $this->shiftEnd,
                $esbToken,
            );
            $esbRows = $summary['rows'];

            if (empty($esbRows)) {
                Notification::make()->title('Tidak ada data penjualan ESB untuk tanggal ini')->info()->send();

                return;
            }

            $this->rows = array_map(fn ($row) => [
                'name' => $row['name'],
                'sales_system' => $row['total'],
                'sales_store' => '',
                'notes' => '',
            ], $esbRows);
            $this->esbTransactions = $summary['transactions'];

            $this->esbFetched = true;

            Notification::make()->title('Data ESB berhasil dimuat')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Gagal mengambil data ESB')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function totalSystem(): float
    {
        return collect($this->rows)->sum(fn ($r) => (float) $r['sales_system']);
    }

    public function totalStore(): float
    {
        return collect($this->rows)->sum(fn ($r) => (float) ($r['sales_store'] ?? 0));
    }

    public function getSelisih(int $idx): float
    {
        $row = $this->rows[$idx] ?? null;
        if (! $row) {
            return 0.0;
        }

        return (float) $row['sales_system'] - (float) ($row['sales_store'] ?? 0);
    }

    public function requestConfirm(): void
    {
        if (! $this->shiftIsUnlocked()) {
            Notification::make()->title('Submit Shift 1 terlebih dahulu')->warning()->send();

            return;
        }

        if ($this->isSubmitted) {
            return;
        }

        if (! $this->esbFetched || empty($this->rows)) {
            Notification::make()->title('Fetch data ESB terlebih dahulu sebelum menyimpan')->warning()->send();

            return;
        }

        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Akun kamu belum terhubung ke cabang')->danger()->send();

            return;
        }

        $this->resetValidation();
        $this->validate([
            'reportDate' => ['required', 'date'],
            'employeeId' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $user->branch_id)
                    ->where('is_active', true)),
            ],
            'rows.*.sales_store' => ['nullable', 'numeric', 'min:0'],
        ], [
            'employeeId.required' => 'Pilih staff yang mengisi Sales Report.',
            'employeeId.exists' => 'Staff tidak aktif atau tidak terdaftar pada branch ini.',
        ]);

        $hasMissingDifferenceNotes = false;
        foreach ($this->rows as $idx => $row) {
            $difference = (float) $row['sales_system'] - (float) ($row['sales_store'] ?? 0);
            if (abs($difference) > 0.009 && empty(trim($row['notes'] ?? ''))) {
                $this->addError("rows.{$idx}.notes", 'Notes wajib diisi karena terdapat selisih pada payment method ini.');
                $hasMissingDifferenceNotes = true;
            }
        }

        if ($hasMissingDifferenceNotes) {
            $this->showDiscrepancies = true;
            Notification::make()
                ->title('Sales difference detected')
                ->body('Terdapat selisih pada Sales Report. Isi notes pada payment method yang ditandai, lalu submit ulang.')
                ->warning()
                ->send();
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->showConfirm = true;
    }

    public function cancelConfirm(): void
    {
        $this->showConfirm = false;
    }

    public function save(): void
    {
        if (! $this->shiftIsUnlocked()) {
            Notification::make()->title('Submit Shift 1 terlebih dahulu')->warning()->send();

            return;
        }

        if ($this->isSubmitted) {
            return;
        }

        $this->showConfirm = false;

        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Akun kamu belum terhubung ke cabang')->danger()->send();

            return;
        }

        $this->validate([
            'employeeId' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $user->branch_id)
                    ->where('is_active', true)),
            ],
        ]);

        $employee = Employee::query()->findOrFail($this->employeeId);

        DB::transaction(function () use ($user, $employee): void {
            $report = SalesReport::query()
                ->where('branch_id', $user->branch_id)
                ->whereDate('report_date', $this->reportDate)
                ->where('shift_number', $this->shiftNumber)
                ->first()
                ?? new SalesReport([
                    'branch_id' => $user->branch_id,
                    'report_date' => $this->reportDate,
                    'shift_number' => $this->shiftNumber,
                ]);
            $isRevision = $report->exists && in_array($report->status, [
                SalesReportStatus::RejectedBySupervisor,
                SalesReportStatus::RejectedByFinance,
            ], true);

            $report->fill([
                'submitted_by' => $user->id,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'employee_position' => $employee->position,
                'submitted_at' => now(),
                'status' => SalesReportStatus::PendingSupervisor->value,
                'rejection_reason' => null,
                'supervisor_reviewed_by' => null,
                'supervisor_reviewed_at' => null,
                'supervisor_note' => null,
                'finance_reviewed_by' => null,
                'finance_reviewed_at' => null,
                'finance_note' => null,
                'revision_number' => $isRevision ? $report->revision_number + 1 : ($report->revision_number ?? 0),
                'shift_started_at' => $this->shiftBoundary($this->shiftStart),
                'shift_ended_at' => $this->shiftBoundary($this->shiftEnd, true),
            ])->save();

            foreach ($this->rows as $row) {
                SalesReportEntry::updateOrCreate(
                    ['sales_report_id' => $report->id, 'payment_method_name' => $row['name']],
                    [
                        'sales_system_amount' => (float) $row['sales_system'],
                        'sales_store_amount' => (float) ($row['sales_store'] ?? 0),
                        'notes' => trim($row['notes'] ?? '') ?: null,
                        'settlement_amount' => null,
                        'mdr_percentage' => null,
                        'mdr_amount' => null,
                        'expected_settlement_amount' => null,
                        'settlement_difference' => null,
                        'reconciliation_status' => null,
                        'finance_note' => null,
                    ]
                );
            }

            $report->esbTransactions()->delete();
            if ($this->esbTransactions !== []) {
                $report->esbTransactions()->createMany($this->esbTransactions);
            }

            SalesReportApproval::create([
                'sales_report_id' => $report->id,
                'stage' => 'submitter',
                'action' => $isRevision ? 'resubmitted' : 'submitted',
                'actor_id' => $user->id,
                'revision_number' => $report->revision_number,
            ]);
        });

        $this->isSubmitted = true;
        $this->currentStatus = SalesReportStatus::PendingSupervisor->value;
        $this->rejectionReason = null;

        Notification::make()
            ->title('Laporan Shift '.$this->shiftNumber.' berhasil disimpan')
            ->body('Data dikunci sementara dan menunggu approval Supervisor Store.')
            ->success()
            ->send();
    }

    private function shiftBoundary(string $time, bool $isEnd = false): CarbonImmutable
    {
        $boundary = CarbonImmutable::parse($this->reportDate.' '.$time, config('app.timezone'));

        if ($isEnd) {
            $start = CarbonImmutable::parse($this->reportDate.' '.$this->shiftStart, config('app.timezone'));
            if ($boundary->lessThanOrEqualTo($start)) {
                $boundary = $boundary->addDay();
            }
        }

        return $boundary;
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
        if ($this->shiftNumber === 1) {
            return true;
        }

        $branchId = auth()->user()?->branch_id;
        if (! $branchId) {
            return false;
        }

        return SalesReport::query()
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $this->reportDate)
            ->where('shift_number', 1)
            ->whereNotNull('submitted_at')
            ->exists();
    }
}
