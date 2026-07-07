<?php

namespace App\Filament\Casual\Pages;

use App\Models\PaymentMethod;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class SalesReportShiftPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.sales-report-shift-page';

    #[Url(as: 'shift')]
    public int $shift = 1;

    #[Url(as: 'date')]
    public string $reportDate = '';

    public string $modalShift = '';

    /** @var array<int, array{amount: string}> */
    public array $entries = [];

    public bool $isSubmitted = false;

    public bool $showConfirm = false;

    public function mount(): void
    {
        if (! $this->reportDate) {
            $this->reportDate = now()->toDateString();
        }

        $this->shift = in_array($this->shift, [1, 2]) ? $this->shift : 1;

        $this->loadData();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sales Report – Shift '.$this->shift;
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

        $methods = PaymentMethod::active()->get();

        $this->entries = [];
        foreach ($methods as $method) {
            $this->entries[$method->id] = ['amount' => ''];
        }

        if (! $branchId) {
            $this->modalShift = '';
            $this->isSubmitted = false;

            return;
        }

        $report = SalesReport::with('entries')
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if (! $report) {
            $this->modalShift = '';
            $this->isSubmitted = false;

            return;
        }

        if ($this->shift === 1) {
            $this->isSubmitted = (bool) $report->shift_1_submitted_at;
            $this->modalShift = $report->modal_shift_1 > 0 ? (string) $report->modal_shift_1 : '';

            foreach ($report->entries as $entry) {
                if (isset($this->entries[$entry->payment_method_id])) {
                    $this->entries[$entry->payment_method_id] = [
                        'amount' => $entry->shift_1_amount > 0 ? (string) $entry->shift_1_amount : '',
                    ];
                }
            }
        } else {
            $this->isSubmitted = (bool) $report->shift_2_submitted_at;
            $this->modalShift = $report->modal_shift_2 > 0 ? (string) $report->modal_shift_2 : '';

            foreach ($report->entries as $entry) {
                if (isset($this->entries[$entry->payment_method_id])) {
                    $this->entries[$entry->payment_method_id] = [
                        'amount' => $entry->shift_2_amount > 0 ? (string) $entry->shift_2_amount : '',
                    ];
                }
            }
        }
    }

    #[Computed]
    public function paymentMethods(): Collection
    {
        return PaymentMethod::active()->get();
    }

    public function total(): float
    {
        return collect($this->entries)->sum(fn ($e) => (float) ($e['amount'] ?? 0));
    }

    public function requestConfirm(): void
    {
        if ($this->isSubmitted) {
            return;
        }

        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()->title('Akun kamu belum terhubung ke cabang')->danger()->send();

            return;
        }

        $this->validate([
            'reportDate' => ['required', 'date'],
            'modalShift' => ['nullable', 'numeric', 'min:0'],
            'entries.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

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

        if ($this->shift === 1) {
            $report = SalesReport::updateOrCreate(
                ['branch_id' => $user->branch_id, 'report_date' => $this->reportDate],
                [
                    'submitted_by' => $user->id,
                    'modal_shift_1' => (float) ($this->modalShift ?: 0),
                    'shift_1_submitted_at' => now(),
                ]
            );

            foreach ($this->entries as $methodId => $data) {
                SalesReportEntry::updateOrCreate(
                    ['sales_report_id' => $report->id, 'payment_method_id' => $methodId],
                    ['shift_1_amount' => (float) ($data['amount'] ?? 0)]
                );
            }
        } else {
            $report = SalesReport::updateOrCreate(
                ['branch_id' => $user->branch_id, 'report_date' => $this->reportDate],
                [
                    'submitted_by' => $user->id,
                    'modal_shift_2' => (float) ($this->modalShift ?: 0),
                    'shift_2_submitted_at' => now(),
                ]
            );

            foreach ($this->entries as $methodId => $data) {
                SalesReportEntry::updateOrCreate(
                    ['sales_report_id' => $report->id, 'payment_method_id' => $methodId],
                    ['shift_2_amount' => (float) ($data['amount'] ?? 0)]
                );
            }
        }

        $this->isSubmitted = true;

        Notification::make()
            ->title('Shift '.$this->shift.' berhasil disimpan')
            ->body('Data sudah terkunci dan tidak dapat diubah kembali.')
            ->success()
            ->send();
    }
}
