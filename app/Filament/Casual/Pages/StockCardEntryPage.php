<?php

namespace App\Filament\Casual\Pages;

use App\Models\StockCard;
use App\Models\StockCardEntry;
use App\Services\EsbService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class StockCardEntryPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.stock-card-entry-page';

    #[Url(as: 'date')]
    public string $reportDate = '';

    public bool $isSubmitted = false;

    public bool $showConfirm = false;

    public bool $esbFetched = false;

    public string $flagUnit = 'stockUnit';

    /** @var array<int, array{product_code: string, product_name: string, system_qty: float, system_unit: string, actual_qty: string, notes: string}> */
    public array $rows = [];

    /** @var array<string, string> */
    public const FLAG_UNITS = [
        'stockUnit' => 'Stock Unit',
        'purchaseUnit' => 'Purchase Unit',
        'baseUnit' => 'Base Unit',
        'transferUnit' => 'Transfer Unit',
        'salesUnit' => 'Sales Unit',
    ];

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

        $card = StockCard::with('entries')
            ->where('branch_id', $user->branch_id)
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if (! $card) {
            return;
        }

        $this->isSubmitted = (bool) $card->submitted_at;
        $this->flagUnit = $card->flag_unit;

        $this->rows = $card->entries->map(fn ($entry) => [
            'product_code' => $entry->product_code,
            'product_name' => $entry->product_name,
            'system_qty' => (float) $entry->system_qty,
            'system_unit' => $entry->system_unit,
            'actual_qty' => $entry->actual_qty !== null ? rtrim(rtrim((string) $entry->actual_qty, '0'), '.') : '',
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

        if (! $branch?->hasEsbIntegration()) {
            Notification::make()->title('Branch belum memiliki konfigurasi ESB')->warning()->send();

            return;
        }

        $result = (new EsbService)->getDailySalesMaterialUsageForBranch($branch, $this->reportDate, $this->flagUnit);
        $items = $result['rows'];

        if (empty($items)) {
            Notification::make()->title('Tidak ada data material untuk tanggal ini')->info()->send();

            return;
        }

        $existingByCode = collect($this->rows)->keyBy('product_code');

        $this->rows = array_map(function ($item) use ($existingByCode) {
            $code = (string) ($item['productCode'] ?? '');
            $existing = $existingByCode->get($code);

            return [
                'product_code' => $code,
                'product_name' => (string) ($item['productName'] ?? 'Unknown'),
                'system_qty' => (float) ($item['totalQty'] ?? 0),
                'system_unit' => (string) ($item['unit'] ?? ''),
                'actual_qty' => $existing['actual_qty'] ?? '',
                'notes' => $existing['notes'] ?? '',
            ];
        }, $items);

        $this->esbFetched = true;

        if ($result['ok']) {
            Notification::make()
                ->title('Data ESB berhasil dimuat')
                ->body(count($this->rows).' item material ditemukan')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Data ESB dimuat sebagian')
                ->body('Salah satu atau lebih pasangan kode ESB branch ini gagal diambil.')
                ->warning()
                ->send();
        }
    }

    public function getVariance(int $idx): ?float
    {
        $row = $this->rows[$idx] ?? null;

        if (! $row || $row['actual_qty'] === '') {
            return null;
        }

        return (float) $row['actual_qty'] - (float) $row['system_qty'];
    }

    public function requestConfirm(): void
    {
        if (! $this->esbFetched) {
            Notification::make()->title('Fetch data ESB terlebih dahulu')->warning()->send();

            return;
        }

        foreach ($this->rows as $idx => $row) {
            if ($row['actual_qty'] === '' || $row['actual_qty'] === null) {
                Notification::make()
                    ->title('Ada qty aktual yang belum diisi')
                    ->body('Pastikan semua item sudah diisi qty aktualnya.')
                    ->warning()
                    ->send();

                return;
            }

            $variance = (float) $row['actual_qty'] - (float) $row['system_qty'];

            if ($variance != 0 && empty(trim($row['notes']))) {
                Notification::make()
                    ->title('Catatan wajib untuk item dengan selisih')
                    ->body("Item \"{$row['product_name']}\" memiliki selisih, harap isi catatan.")
                    ->warning()
                    ->send();

                return;
            }
        }

        $this->showConfirm = true;
    }

    public function cancelConfirm(): void
    {
        $this->showConfirm = false;
    }

    public function save(): void
    {
        $user = auth()->user();

        $card = StockCard::updateOrCreate(
            ['branch_id' => $user->branch_id, 'report_date' => $this->reportDate],
            ['submitted_by' => $user->id, 'flag_unit' => $this->flagUnit, 'submitted_at' => now()]
        );

        foreach ($this->rows as $row) {
            StockCardEntry::updateOrCreate(
                ['stock_card_id' => $card->id, 'product_code' => $row['product_code']],
                [
                    'product_name' => $row['product_name'],
                    'system_qty' => $row['system_qty'],
                    'system_unit' => $row['system_unit'],
                    'actual_qty' => $row['actual_qty'] !== '' ? (float) $row['actual_qty'] : null,
                    'notes' => $row['notes'] !== '' ? $row['notes'] : null,
                ]
            );
        }

        $this->isSubmitted = true;
        $this->showConfirm = false;

        Notification::make()->title('Stock Card berhasil disimpan')->success()->send();
    }
}
