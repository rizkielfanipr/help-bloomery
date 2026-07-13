<?php

namespace App\Filament\Casual\Pages;

use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Services\EsbService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class SalesReportShiftPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.sales-report-shift-page';

    #[Url(as: 'date')]
    public string $reportDate = '';

    public bool $isSubmitted = false;

    public bool $showConfirm = false;

    public bool $esbFetched = false;

    /** @var array<int, array{name: string, sales_system: float, sales_store: string, notes: string}> */
    public array $rows = [];

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

        $this->loadData();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sales Report Harian';
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

        $report = SalesReport::with('entries')
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if (! $report) {
            return;
        }

        $this->isSubmitted = (bool) $report->submitted_at;

        $this->rows = $report->entries->map(fn ($entry) => [
            'name' => $entry->payment_method_name,
            'sales_system' => (float) $entry->sales_system_amount,
            'sales_store' => $entry->sales_store_amount > 0 ? (string) $entry->sales_store_amount : '',
            'notes' => $entry->notes ?? '',
        ])->values()->all();

        if (! empty($this->rows)) {
            $this->esbFetched = true;
        }
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
            $esbRows = (new EsbService)->getPaymentSummary($branch->esb_branch_code, $this->reportDate, $esbToken);

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

        $this->validate([
            'reportDate' => ['required', 'date'],
            'rows.*.sales_store' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($this->rows as $idx => $row) {
            $selisih = (float) $row['sales_system'] - (float) ($row['sales_store'] ?? 0);
            if ($selisih < 0 && empty(trim($row['notes'] ?? ''))) {
                $this->addError("rows.{$idx}.notes", 'Catatan wajib diisi karena selisih negatif.');
            }
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
        if ($this->isSubmitted) {
            return;
        }

        $this->showConfirm = false;

        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Akun kamu belum terhubung ke cabang')->danger()->send();

            return;
        }

        $report = SalesReport::updateOrCreate(
            ['branch_id' => $user->branch_id, 'report_date' => $this->reportDate],
            ['submitted_by' => $user->id, 'submitted_at' => now()]
        );

        foreach ($this->rows as $row) {
            SalesReportEntry::updateOrCreate(
                ['sales_report_id' => $report->id, 'payment_method_name' => $row['name']],
                [
                    'sales_system_amount' => (float) $row['sales_system'],
                    'sales_store_amount' => (float) ($row['sales_store'] ?? 0),
                    'notes' => trim($row['notes'] ?? '') ?: null,
                ]
            );
        }

        $this->isSubmitted = true;

        Notification::make()
            ->title('Laporan harian berhasil disimpan')
            ->body('Data sudah terkunci dan tidak dapat diubah kembali.')
            ->success()
            ->send();
    }
}
