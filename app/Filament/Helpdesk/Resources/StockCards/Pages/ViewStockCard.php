<?php

namespace App\Filament\Helpdesk\Resources\StockCards\Pages;

use App\Enums\StockCardStatus;
use App\Filament\Helpdesk\Concerns\HasStockMovementTable;
use App\Filament\Helpdesk\Resources\StockCards\StockCardResource;
use App\Models\Branch;
use App\Models\StockCard;
use App\Models\StockCardApproval;
use App\Services\EsbStockMovementService;
use App\Services\StockCardCategoryFilter;
use App\Services\StockCardEsbSynchronizer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ViewStockCard extends Page
{
    use HasStockMovementTable;

    protected static string $resource = StockCardResource::class;

    protected string $view = 'filament.helpdesk.stock-cards.view';

    public StockCard $record;

    #[Locked]
    public array $detailCategoryRules = [];

    #[Locked]
    public array $detailCategoryMappings = [];

    #[Locked]
    public array $categoryMappingFailures = [];

    public string $reviewNote = '';

    public string $rejectionReason = '';

    /** @var array<int, array{actual_qty: string, supervisor_notes: string}> */
    public array $entryRows = [];

    protected function authorizeMovementAccess(): void
    {
        abort_unless(StockCardResource::canView($this->record), 403);
    }

    protected function movementBranch(): Branch
    {
        return $this->record->branch;
    }

    protected function movementDate(): string
    {
        return $this->record->report_date->toDateString();
    }

    protected function movementUnit(): string
    {
        return $this->record->flag_unit;
    }

    public function hasDetailCategoryFilter(): bool
    {
        return collect($this->detailCategoryRules)->contains(fn (array $rule): bool => ! ($rule['all_categories'] ?? true) || ! ($rule['show_uncategorized'] ?? true));
    }

    protected function prepareMovementProducts(): void
    {
        $this->detailCategoryMappings = [];
        $this->categoryMappingFailures = [];
        if (! $this->hasDetailCategoryFilter()) {
            return;
        }
        foreach (array_keys($this->detailCategoryRules) as $company) {
            try {
                $this->detailCategoryMappings[$company] = app(EsbStockMovementService::class)->categories($company);
            } catch (\Throwable $exception) {
                report($exception);
                $this->categoryMappingFailures[] = $company;
            }
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function movementProducts(): Collection
    {
        $entries = $this->record->entries->keyBy('product_code');
        $products = $entries->mapWithKeys(fn ($entry): array => [
            $entry->product_code => [
                'productCode' => $entry->product_code, 'productName' => $entry->product_name,
                'unit' => $entry->system_unit, 'totalQty' => $entry->system_qty, 'live' => false,
            ],
        ]);
        foreach ($this->movementBalances as $product) {
            $products->put($product['productCode'], $product + ['live' => true]);
        }
        if (! $this->hasDetailCategoryFilter()) {
            return $products->values();
        }
        $filter = app(StockCardCategoryFilter::class);

        return $products->filter(function (array $product, string $code) use ($entries, $filter): bool {
            $entry = $entries->get($code);
            if ($entry && ($entry->actual_qty !== null || $entry->reported_qty !== null || filled($entry->notes) || filled($entry->supervisor_notes))) {
                return true;
            }
            $sources = [];
            foreach ($product['companies'] ?? array_keys($this->detailCategoryRules) as $company) {
                if (isset($this->detailCategoryMappings[$company])) {
                    $sources[$company] = $this->detailCategoryMappings[$company][$code] ?? '';
                } elseif (! ($product['live'] ?? false) && $entry) {
                    $sources[$company] = $entry->product_category ?? '';
                }
            }
            if ($sources === []) {
                return false;
            }

            return $filter->filter([['category_sources' => $sources]], $this->detailCategoryRules) !== [];
        })->values();
    }

    private const VARIANCE_TOLERANCE = 0.0001;

    public function mount(StockCard $record): void
    {
        abort_unless(StockCardResource::canView($record), 403);

        $this->record = $this->loadRecord($record);
        $this->detailCategoryRules = $this->record->category_settings_snapshot ?? app(StockCardCategoryFilter::class)->snapshot($this->record->branch);
        $this->loadEntryRows();
        if ($this->record->movement_snapshot !== null) {
            $this->applyTransactionBreakdown($this->record->movement_snapshot);
            $this->transactionsFetchedAt = $this->record->system_fetched_at?->format('d M Y H:i:s');
        }
    }

    public function getTitle(): string
    {
        return collect([
            'Stock Card',
            $this->record->branch->name,
            $this->record->report_date->isoFormat('D MMMM Y'),
        ])->join(' · ');
    }

    public function canReviewAsSupervisor(): bool
    {
        $user = auth()->user();

        return $this->record->status === StockCardStatus::PendingSupervisor
            && $user?->can('review stock cards as supervisor')
            && ($user->canAccessAllBranches() || $user->id !== $this->record->submitted_by)
            && ($user->canAccessAllBranches() || $user->canAccessBranch($this->record->branch_id));
    }

    public function canReviewAsFinance(): bool
    {
        $user = auth()->user();

        return $this->record->status === StockCardStatus::PendingFinance
            && $user?->can('review stock cards as finance')
            && ($user->canAccessAllBranches() || $user->id !== $this->record->submitted_by);
    }

    public function canRefetchEsb(): bool
    {
        return app(StockCardEsbSynchronizer::class)->canRefresh($this->record);
    }

    public function refetchEsb(): void
    {
        abort_unless($this->canRefetchEsb(), 403);
        try {
            $result = app(StockCardEsbSynchronizer::class)->refresh($this->record);
            $this->applyTransactionBreakdown($result);
            $this->refreshRecord();
            Notification::make()->title('Data sistem berhasil diperbarui dari ESB')->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->errors()['esb'][0])->warning()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('Gagal mengambil Stock Movement')->body($exception->getMessage())->danger()->send();
        }
    }

    public function approveSupervisor(): void
    {
        abort_unless($this->canReviewAsSupervisor(), 403);
        abort_unless($this->record->system_fetched_at !== null, 403);

        foreach ($this->record->entries as $entry) {
            $this->validate([
                "entryRows.{$entry->id}.actual_qty" => ['required', 'numeric', 'min:0'],
                "entryRows.{$entry->id}.supervisor_notes" => ['nullable', 'string', 'max:2000'],
            ]);

            $row = $this->entryRows[$entry->id];

            if (
                $entry->system_qty !== null
                && abs((float) $row['actual_qty'] - (float) $entry->system_qty) > self::VARIANCE_TOLERANCE
                && trim($row['supervisor_notes']) === ''
            ) {
                throw ValidationException::withMessages([
                    "entryRows.{$entry->id}.supervisor_notes" => 'Notes are required while System Qty and corrected qty differ.',
                ]);
            }
        }

        DB::transaction(function (): void {
            $card = StockCard::query()->lockForUpdate()->findOrFail($this->record->id);
            abort_unless($card->status === StockCardStatus::PendingSupervisor, 409);

            foreach ($card->entries as $entry) {
                $row = $this->entryRows[$entry->id];
                $entry->update([
                    'actual_qty' => (float) $row['actual_qty'],
                    'supervisor_notes' => trim($row['supervisor_notes']) ?: null,
                ]);
            }

            $card->update([
                'status' => StockCardStatus::PendingFinance->value,
                'supervisor_reviewed_by' => auth()->id(),
                'supervisor_reviewed_at' => now(),
                'supervisor_note' => trim($this->reviewNote) ?: null,
            ]);

            $this->recordApproval($card, 'supervisor', 'approved', trim($this->reviewNote) ?: null);
        });

        $this->refreshRecord();
        $this->reset(['reviewNote', 'rejectionReason']);

        Notification::make()->title('Status updated to Finance Review')->success()->send();
    }

    public function approveFinance(): void
    {
        abort_unless($this->canReviewAsFinance(), 403);

        DB::transaction(function (): void {
            $card = StockCard::query()->lockForUpdate()->findOrFail($this->record->id);
            abort_unless($card->status === StockCardStatus::PendingFinance, 409);

            $card->update([
                'status' => StockCardStatus::Completed->value,
                'finance_reviewed_by' => auth()->id(),
                'finance_reviewed_at' => now(),
                'finance_note' => trim($this->reviewNote) ?: null,
            ]);

            $this->recordApproval($card, 'finance', 'approved', trim($this->reviewNote) ?: null);
        });

        $this->refreshRecord();
        $this->reset(['reviewNote', 'rejectionReason']);

        Notification::make()->title('Status updated to Completed')->success()->send();
    }

    public function rejectFinance(): void
    {
        abort_unless($this->canReviewAsFinance(), 403);
        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:2000']]);

        DB::transaction(function (): void {
            $card = StockCard::query()->lockForUpdate()->findOrFail($this->record->id);
            abort_unless($card->status === StockCardStatus::PendingFinance, 409);

            $card->update([
                'status' => StockCardStatus::PendingSupervisor->value,
                'finance_reviewed_by' => auth()->id(),
                'finance_reviewed_at' => now(),
                'revision_number' => $card->revision_number + 1,
            ]);

            $this->recordApproval($card, 'finance', 'rejected', trim($this->rejectionReason));
        });

        $this->refreshRecord();
        $this->reset(['reviewNote', 'rejectionReason']);

        Notification::make()->title('Report returned to Supervisor Review')->warning()->send();
    }

    private function recordApproval(StockCard $card, string $stage, string $action, ?string $notes): void
    {
        StockCardApproval::create([
            'stock_card_id' => $card->id,
            'stage' => $stage,
            'action' => $action,
            'actor_id' => auth()->id(),
            'notes' => $notes,
            'revision_number' => $card->revision_number,
        ]);
    }

    private function loadEntryRows(): void
    {
        $this->entryRows = $this->record->entries->mapWithKeys(fn ($entry) => [
            $entry->id => [
                'actual_qty' => (string) $entry->actual_qty,
                'supervisor_notes' => $entry->supervisor_notes ?? '',
            ],
        ])->all();
    }

    private function loadRecord(StockCard $record): StockCard
    {
        return $record->load(['branch', 'submittedBy', 'supervisorReviewer', 'financeReviewer', 'entries', 'employees', 'approvals.actor']);
    }

    private function refreshRecord(): void
    {
        $this->record = $this->loadRecord($this->record->fresh());
        $this->loadEntryRows();
    }
}
