<?php

namespace App\Filament\Casual\Pages;

use App\Models\PaymentMethod;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;

class SalesReportPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.sales-report-page';

    public string $reportDate = '';

    public string $modalShift1 = '';

    public string $modalShift2 = '';

    /** @var array<int, array{shift_1: string, shift_2: string, notes: string}> */
    public array $entries = [];

    public function mount(): void
    {
        $this->reportDate = now()->toDateString();
        $this->loadReport();
    }

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function updatedReportDate(): void
    {
        $this->loadReport();
    }

    private function loadReport(): void
    {
        $user = auth()->user();
        $branchId = $user->branch_id;

        $methods = PaymentMethod::active()->get();

        // Initialize entries with zeros
        $this->entries = [];
        foreach ($methods as $method) {
            $this->entries[$method->id] = [
                'shift_1' => '',
                'shift_2' => '',
                'notes' => '',
            ];
        }

        if (! $branchId) {
            $this->modalShift1 = '';
            $this->modalShift2 = '';

            return;
        }

        $report = SalesReport::with('entries')
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if ($report) {
            $this->modalShift1 = $report->modal_shift_1 > 0 ? (string) $report->modal_shift_1 : '';
            $this->modalShift2 = $report->modal_shift_2 > 0 ? (string) $report->modal_shift_2 : '';

            foreach ($report->entries as $entry) {
                if (isset($this->entries[$entry->payment_method_id])) {
                    $this->entries[$entry->payment_method_id] = [
                        'shift_1' => $entry->shift_1_amount > 0 ? (string) $entry->shift_1_amount : '',
                        'shift_2' => $entry->shift_2_amount > 0 ? (string) $entry->shift_2_amount : '',
                        'notes' => $entry->notes ?? '',
                    ];
                }
            }
        } else {
            $this->modalShift1 = '';
            $this->modalShift2 = '';
        }
    }

    #[Computed]
    public function paymentMethods(): Collection
    {
        return PaymentMethod::active()->get();
    }

    public function totalShift1(): float
    {
        return collect($this->entries)->sum(fn ($e) => (float) ($e['shift_1'] ?? 0));
    }

    public function totalShift2(): float
    {
        return collect($this->entries)->sum(fn ($e) => (float) ($e['shift_2'] ?? 0));
    }

    public function save(): void
    {
        $user = auth()->user();

        if (! $user->branch_id) {
            Notification::make()
                ->title('Akun kamu belum terhubung ke cabang')
                ->danger()
                ->send();

            return;
        }

        $this->validate([
            'reportDate' => ['required', 'date'],
            'modalShift1' => ['nullable', 'numeric', 'min:0'],
            'modalShift2' => ['nullable', 'numeric', 'min:0'],
            'entries.*.shift_1' => ['nullable', 'numeric', 'min:0'],
            'entries.*.shift_2' => ['nullable', 'numeric', 'min:0'],
        ]);

        $report = SalesReport::updateOrCreate(
            ['branch_id' => $user->branch_id, 'report_date' => $this->reportDate],
            [
                'submitted_by' => $user->id,
                'modal_shift_1' => (float) ($this->modalShift1 ?: 0),
                'modal_shift_2' => (float) ($this->modalShift2 ?: 0),
            ]
        );

        foreach ($this->entries as $methodId => $data) {
            SalesReportEntry::updateOrCreate(
                ['sales_report_id' => $report->id, 'payment_method_id' => $methodId],
                [
                    'shift_1_amount' => (float) ($data['shift_1'] ?? 0),
                    'shift_2_amount' => (float) ($data['shift_2'] ?? 0),
                    'notes' => $data['notes'] ?: null,
                ]
            );
        }

        Notification::make()
            ->title('Sales report berhasil disimpan')
            ->success()
            ->send();
    }
}
