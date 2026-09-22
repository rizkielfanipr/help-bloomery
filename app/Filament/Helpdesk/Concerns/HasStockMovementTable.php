<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Models\Branch;
use App\Services\EsbStockMovementService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

trait HasStockMovementTable
{
    abstract protected function authorizeMovementAccess(): void;

    abstract protected function movementBranch(): Branch;

    abstract protected function movementDate(): string;

    abstract protected function movementUnit(): string;

    /** @var list<string> */
    #[Locked]
    public array $transactionTypes = [];

    /** @var array<string, array<string, array{qty_in:float,qty_out:float}>> */
    #[Locked]
    public array $transactionQuantities = [];

    /** @var array<string, string> */
    #[Locked]
    public array $transactionUnits = [];

    #[Locked]
    public bool $transactionsLoaded = false;

    #[Locked]
    public ?string $transactionError = null;

    #[Locked]
    public ?string $transactionsFetchedAt = null;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $movementBalances = [];

    public string $movementSearch = '';

    public int $movementPage = 1;

    public function updatedMovementSearch(): void
    {
        $this->movementPage = 1;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function filteredMovementBalances(): Collection
    {
        $search = mb_strtolower(trim($this->movementSearch));

        return $this->movementProducts()->filter(fn (array $row): bool => $search === ''
            || str_contains(mb_strtolower($row['productCode'].' '.($row['productName'] ?? '')), $search))
            ->sort(function (array $left, array $right): int {
                $categoryComparison = strnatcasecmp(
                    (string) ($left['productCategory'] ?? 'Tanpa Kategori'),
                    (string) ($right['productCategory'] ?? 'Tanpa Kategori'),
                );

                return $categoryComparison !== 0
                    ? $categoryComparison
                    : strnatcasecmp(
                        (string) ($left['productName'] ?? $left['productCode']),
                        (string) ($right['productName'] ?? $right['productCode']),
                    );
            })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function movementProducts(): Collection
    {
        return collect($this->movementBalances);
    }

    public function goToMovementPage(int $page): void
    {
        $lastPage = max(1, (int) ceil($this->filteredMovementBalances()->count() / 25));
        $this->movementPage = min(max(1, $page), $lastPage);
    }

    public function loadTransactionBreakdown(): void
    {
        $this->reset(['transactionTypes', 'transactionQuantities', 'transactionUnits', 'transactionsLoaded', 'transactionsFetchedAt', 'transactionError', 'movementBalances', 'movementPage']);
        $this->authorizeMovementAccess();
        try {
            $result = (new EsbStockMovementService)->balancesForBranch(
                $this->movementBranch(),
                $this->movementDate(),
                $this->movementUnit(),
            );
            $this->applyTransactionBreakdown($result);
        } catch (\Throwable $exception) {
            report($exception);
            $this->transactionError = $exception->getMessage();
        }
    }

    /** @param array<string, mixed> $result */
    protected function prepareMovementProducts(): void {}

    private function applyTransactionBreakdown(array $result): void
    {
        $this->movementBalances = $result['rows'];
        $this->prepareMovementProducts();
        $this->movementPage = 1;
        $this->transactionTypes = app(EsbStockMovementService::class)->transactionTypes($result['types']);
        $this->transactionQuantities = $result['transactions'];
        $this->transactionUnits = $result['units'];
        $this->transactionsLoaded = true;
        $this->transactionError = null;
        $this->transactionsFetchedAt = now()->format('d M Y H:i:s');
    }
}
