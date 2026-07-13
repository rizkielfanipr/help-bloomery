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
        return Branch::whereNotNull('esb_branch_code')
            ->where('esb_branch_code', '!=', '')
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

        $branch = Branch::find($this->selectedBranchId);

        if (! $branch || ! $branch->esb_branch_code) {
            Notification::make()->title('Branch ini belum memiliki ESB Branch Code')->warning()->send();

            return;
        }

        $esbToken = $branch->esb_token;

        if (! $esbToken) {
            Notification::make()->title('Token ESB untuk branch ini belum dikonfigurasi')->warning()->send();

            return;
        }

        try {
            $service = new EsbService;
            $this->esbRows = $service->getPaymentSummary($branch->esb_branch_code, $this->selectedDate, $esbToken);
            $this->fetched = true;

            if (empty($this->esbRows)) {
                Notification::make()->title('Tidak ada data penjualan pada tanggal tersebut')->info()->send();
            }
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Gagal mengambil data ESB')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function grandTotal(): float
    {
        return array_sum(array_column($this->esbRows, 'total'));
    }
}
