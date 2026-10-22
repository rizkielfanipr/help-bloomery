<?php

namespace App\Filament\Casual\Pages;

use App\Actions\SalesReport\SaveSalesReportShiftAction;
use App\Enums\SalesReportDataSource;
use App\Enums\SalesReportStatus;
use App\Models\ComplimentType;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportCompliment;
use App\Models\SalesReportEmployee;
use App\Models\SalesReportEntry;
use App\Models\SalesReportVoid;
use App\Models\User;
use App\Services\EsbService;
use App\Services\SalesReport\SalesReportEvidenceStore;
use App\Services\SalesReport\SalesReportShiftSnapshotStore;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
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

    /** @var array<int, array<string, mixed>> */
    public array $submittedCompliments = [];

    /** @var array<int, array<string, mixed>> */
    public array $submittedVoids = [];

    /** @var array<int, array{compliment_type_id: int|null, sales_number: string, sales_number_source: string, notes: string, attachments: array<int, mixed>}> */
    public array $compliments = [];

    /** @var array<int, array{sales_number: string, sales_number_source: string, payment_method_name: string, payment_method_source: string, reason: string, original_receipt: mixed, voided_receipt: mixed}> */
    public array $voids = [];

    /** @var array<int, array{label: string, name: string, sales_store: string, notes: string}> */
    public array $rows = [];

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
            'compliments' => fn ($q) => $q->where('shift_number', $this->shiftNumber)->with('submittedBy:id,name'),
            'voids' => fn ($q) => $q->where('shift_number', $this->shiftNumber)->with('submittedBy:id,name'),
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

        // Read-only presentation; evidence is linked through the authorized evidence route only.
        $this->submittedCompliments = $report->compliments->map(fn (SalesReportCompliment $compliment): array => [
            'type' => $compliment->compliment_type_name,
            'sales_number' => $compliment->sales_number,
            'source' => $compliment->effectiveSalesNumberSource()->getLabel(),
            'source_value' => $compliment->effectiveSalesNumberSource()->value,
            'notes' => $compliment->notes,
            'submitted_by' => $compliment->submittedBy?->name,
            'attachments' => collect($compliment->attachment_paths ?? [])->map(fn (string $path, int $index): array => [
                'url' => route('sales-report-evidence.compliment', ['compliment' => $compliment->id, 'index' => $index]),
                'legacy_pdf' => SalesReportEvidenceStore::isLegacyPdf($path),
            ])->values()->all(),
        ])->all();

        $this->submittedVoids = $report->voids->map(fn (SalesReportVoid $void): array => [
            'sales_number' => $void->sales_number,
            'source' => $void->sales_number_source->getLabel(),
            'source_value' => $void->sales_number_source->value,
            'payment_method' => $void->payment_method_name,
            'payment_method_manual' => $void->payment_method_source === SalesReportDataSource::Manual,
            'reason' => $void->reason,
            'submitted_by' => $void->submittedBy?->name,
            'submitted_at' => $void->created_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
            'original_url' => route('sales-report-evidence.void', ['void' => $void->id, 'kind' => 'original']),
            'voided_url' => route('sales-report-evidence.void', ['void' => $void->id, 'kind' => 'voided']),
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
            app(SalesReportShiftSnapshotStore::class)->put(
                $user,
                $this->reportDate,
                $this->shiftNumber,
                $shiftSummary['transactions'],
                collect($rows)->pluck('name')->all(),
            );
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
            'sales_number' => '',
            'sales_number_source' => SalesReportDataSource::EsbSnapshot->value,
            'notes' => '',
            'attachments' => [],
        ];
    }

    public function addVoid(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        $this->voids[] = [
            'sales_number' => '',
            'sales_number_source' => SalesReportDataSource::EsbSnapshot->value,
            'payment_method_name' => '',
            'payment_method_source' => SalesReportDataSource::EsbSnapshot->value,
            'reason' => '',
            'original_receipt' => null,
            'voided_receipt' => null,
        ];
    }

    public function removeVoid(int $index): void
    {
        if ($this->isSubmitted || ! array_key_exists($index, $this->voids)) {
            return;
        }

        unset($this->voids[$index]);
        $this->voids = array_values($this->voids);
    }

    /**
     * Switches a row between the ESB picker and an explicit, unverified manual entry.
     *
     * @param  'compliments'|'voids'  $section
     * @param  'sales_number'|'payment_method'  $field
     */
    public function setEntryMode(string $section, int $index, string $field, string $mode): void
    {
        if ($this->isSubmitted || ! in_array($section, ['compliments', 'voids'], true) || ! isset($this->{$section}[$index])
            || ! in_array($field, ['sales_number', 'payment_method'], true) || ($field === 'payment_method' && $section !== 'voids')
            || ! in_array($mode, SalesReportDataSource::submittableValues(), true)) {
            return;
        }

        $value = $field === 'sales_number' ? 'sales_number' : 'payment_method_name';
        $this->{$section}[$index][$field.'_source'] = $mode;
        $this->{$section}[$index][$value] = '';
    }

    /**
     * Server-side Sales Number search over this shift's ESB snapshot (picker).
     *
     * @return list<array{sales_number: string, sales_date_out: string, payment_total: float}>
     */
    #[Renderless]
    public function searchSalesNumbers(string $term = ''): array
    {
        return app(SalesReportShiftSnapshotStore::class)->search(auth()->user(), $this->reportDate, $this->shiftNumber, mb_substr($term, 0, 50));
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
        $this->validate(SaveSalesReportShiftAction::rules($user), SaveSalesReportShiftAction::messages());
    }

    public function save(SaveSalesReportShiftAction $saveShift): void
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
        $snapshots = app(SalesReportShiftSnapshotStore::class);

        try {
            $result = $saveShift->execute($user, $this->reportDate, $this->shiftNumber, [
                'employeeIds' => $this->employeeIds,
                'rows' => $this->rows,
                'compliments' => $this->compliments,
                'voids' => $this->voids,
            ], $snapshots->get($user, $this->reportDate, $this->shiftNumber));
        } catch (ValidationException $exception) {
            if (array_key_exists('shift', $exception->errors())) {
                $this->showConfirm = false;
                Notification::make()->title('Shift sudah dikirim')->body($exception->errors()['shift'][0])->warning()->send();

                return;
            }

            $this->showConfirm = false;

            throw $exception;
        }

        $snapshots->forget($user, $this->reportDate, $this->shiftNumber);
        $completedAllShifts = $result['completed_all_shifts'];
        $this->isSubmitted = true;
        $this->showConfirm = false;
        $this->currentStatus = $completedAllShifts ? SalesReportStatus::PendingSupervisor->value : SalesReportStatus::Draft->value;
        $this->compliments = [];
        $this->voids = [];
        $this->loadData();

        Notification::make()
            ->title('Laporan Shift '.$this->shiftNumber.' berhasil disimpan')
            ->body($completedAllShifts
                ? 'Semua shift sudah lengkap, laporan menunggu pemeriksaan Supervisor Store.'
                : 'Menunggu Shift berikutnya sebelum diperiksa Supervisor Store.')
            ->success()
            ->send();
    }

    /** @return array{0:string,1:string} */
    private function shiftWindow($branch): array
    {
        return $branch->salesShiftWindow($this->shiftNumber);
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

    /**
     * Payment Methods of this shift's ESB snapshot, for the Void picker.
     *
     * @return list<string>
     */
    #[Computed]
    public function paymentMethodOptions(): array
    {
        return app(SalesReportShiftSnapshotStore::class)->get(auth()->user(), $this->reportDate, $this->shiftNumber)['payment_methods'] ?? [];
    }

    public function hasEsbSnapshot(): bool
    {
        return app(SalesReportShiftSnapshotStore::class)->get(auth()->user(), $this->reportDate, $this->shiftNumber) !== null;
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
