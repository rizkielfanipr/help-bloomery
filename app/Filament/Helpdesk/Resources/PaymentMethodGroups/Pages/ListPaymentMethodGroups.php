<?php

namespace App\Filament\Helpdesk\Resources\PaymentMethodGroups\Pages;

use App\Filament\Helpdesk\Resources\PaymentMethodGroups\PaymentMethodGroupResource;
use App\Models\Branch;
use App\Models\EsbPaymentMethodCache;
use App\Services\EsbService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListPaymentMethodGroups extends ListRecords
{
    protected static string $resource = PaymentMethodGroupResource::class;

    public bool $isSyncing = false;

    public string $syncDateFrom = '';

    public string $syncDateTo = '';

    /** @var list<int> */
    public array $syncBranchIds = [];

    public int $syncBranchIndex = 0;

    public int $syncCurrentPage = 0;

    public int $syncTotalPages = 0;

    public int $syncPaymentCount = 0;

    public int $syncFailed = 0;

    public string $syncLastError = '';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncEsb')
                ->label(fn () => $this->isSyncing ? 'Syncing...' : 'Sync ESB')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->disabled(fn () => $this->isSyncing)
                ->schema([
                    DatePicker::make('date_from')
                        ->label('From Date')
                        ->required()
                        ->default(today()->subDays(30)->toDateString())
                        ->maxDate(today()),
                    DatePicker::make('date_to')
                        ->label('To Date')
                        ->required()
                        ->default(today()->toDateString())
                        ->maxDate(today()),
                ])
                ->modalHeading('Sync Payment Methods from ESB')
                ->modalDescription('The system will fetch payment method data from all branches with ESB tokens within the selected date range. Processing is done page by page with no timeout.')
                ->modalSubmitActionLabel('Start Sync')
                ->action(function (array $data): void {
                    $this->startSync($data['date_from'], $data['date_to']);
                }),

            CreateAction::make()->label('Add Group'),
        ];
    }

    public function startSync(string $dateFrom, string $dateTo): void
    {
        $branchIds = Branch::whereNotNull('esb_branch_code')
            ->get()
            ->filter(fn (Branch $b) => ! empty($b->esb_token))
            ->pluck('id')
            ->values()
            ->all();

        if (empty($branchIds)) {
            Notification::make()
                ->title('No branches with ESB token configured')
                ->warning()
                ->send();

            return;
        }

        $this->syncDateFrom = $dateFrom;
        $this->syncDateTo = $dateTo;
        $this->syncBranchIds = $branchIds;
        $this->syncBranchIndex = 0;
        $this->syncCurrentPage = 0;
        $this->syncTotalPages = 0;
        $this->syncPaymentCount = 0;
        $this->syncFailed = 0;
        $this->syncLastError = '';
        $this->isSyncing = true;

        $this->dispatch('sync-payment-methods-next');
    }

    #[On('sync-payment-methods-next')]
    public function syncNext(): void
    {
        if (! $this->isSyncing) {
            return;
        }

        $branchId = $this->syncBranchIds[$this->syncBranchIndex] ?? null;

        if ($branchId === null) {
            $this->finishSync();

            return;
        }

        $branch = Branch::find($branchId);

        if (! $branch || empty($branch->esb_token)) {
            $this->advanceSyncBranch();

            return;
        }

        try {
            $nextPage = $this->syncCurrentPage + 1;

            ['data' => $rows, 'pageCount' => $pageCount] = (new EsbService)->getSalesPage(
                $branch->esb_branch_code,
                $this->syncDateFrom,
                $this->syncDateTo,
                $branch->esb_token,
                $nextPage,
            );

            $this->syncTotalPages = $pageCount;
            $this->syncCurrentPage = $nextPage;

            foreach ($rows as $sale) {
                $branchCode = $sale['branchCode'] ?? $branch->esb_branch_code;
                $branchName = $sale['branchName'] ?? $branch->name;
                foreach ($sale['salesPayments'] ?? [] as $payment) {
                    EsbPaymentMethodCache::upsertFromEsb($payment, $branchCode, $branchName);
                    $this->syncPaymentCount++;
                }
            }

            if ($this->syncCurrentPage < $this->syncTotalPages) {
                $this->dispatch('sync-payment-methods-next');

                return;
            }

            $this->advanceSyncBranch();
        } catch (\RuntimeException $e) {
            $this->syncFailed++;
            $this->syncLastError = $e->getMessage();
            $this->advanceSyncBranch();
        }
    }

    private function advanceSyncBranch(): void
    {
        $this->syncBranchIndex++;
        $this->syncCurrentPage = 0;
        $this->syncTotalPages = 0;

        if ($this->syncBranchIndex < count($this->syncBranchIds)) {
            $this->dispatch('sync-payment-methods-next');
        } else {
            $this->finishSync();
        }
    }

    private function finishSync(): void
    {
        $this->isSyncing = false;
        $totalCached = EsbPaymentMethodCache::count();

        if ($this->syncPaymentCount > 0) {
            Notification::make()
                ->title('Sync complete')
                ->body("{$totalCached} payment methods cached"
                    .($this->syncFailed > 0 ? " ({$this->syncFailed} branches failed)" : ''))
                ->success()
                ->send();
        } elseif ($this->syncFailed > 0) {
            Notification::make()
                ->title("{$this->syncFailed} branches failed to sync")
                ->body($this->syncLastError)
                ->danger()
                ->send();
        } else {
            Notification::make()
                ->title('No transaction data found in the selected date range')
                ->info()
                ->send();
        }
    }
}
