<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Branch;
use App\Services\EsbService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class EsbSalesPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.helpdesk.pages.esb-sales-page';

    public ?int $selectedBranchId = null;

    public string $selectedDate = '';

    /** @var array<int, array{name: string, type: string, total: float}> */
    public array $esbRows = [];

    public bool $fetched = false;

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
    }

    public static function getNavigationLabel(): string
    {
        return 'ESB Sales';
    }

    public function getTitle(): string
    {
        return 'ESB Sales Summary';
    }

    /** @return array<int, Branch> */
    public function getBranches(): array
    {
        return Branch::with('esbCodes')
            ->whereHas('esbCodes', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function fetch(): void
    {
        $this->validate([
            'selectedBranchId' => ['required', 'integer', 'exists:branches,id'],
            'selectedDate' => ['required', 'date'],
        ], [
            'selectedBranchId.required' => 'Pilih branch terlebih dahulu.',
            'selectedDate.required' => 'Pilih tanggal terlebih dahulu.',
        ]);

        $branch = Branch::with('esbCodes')->find($this->selectedBranchId);

        if (! $branch || ! $branch->hasEsbIntegration()) {
            Notification::make()->title('Branch ini belum memiliki konfigurasi ESB')->warning()->send();

            return;
        }

        $service = new EsbService;
        $result = $service->getPaymentSummaryForBranch($branch, $this->selectedDate);
        $this->esbRows = $result['rows'];
        $this->fetched = true;

        if (empty($this->esbRows)) {
            Notification::make()->title('Tidak ada data penjualan pada tanggal tersebut')->info()->send();
        } elseif (! $result['ok']) {
            Notification::make()
                ->title('Data dimuat sebagian')
                ->body('Salah satu atau lebih pasangan kode ESB branch ini gagal diambil.')
                ->warning()
                ->send();
        }
    }

    public function grandTotal(): float
    {
        return array_sum(array_column($this->esbRows, 'total'));
    }
}
