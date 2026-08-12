<?php

namespace App\Filament\Helpdesk\Resources\StockCards\Pages;

use App\Enums\StockCardStatus;
use App\Filament\Helpdesk\Resources\StockCards\StockCardResource;
use App\Models\StockCard;
use App\Models\StockCardApproval;
use App\Services\EsbService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewStockCard extends Page
{
    protected static string $resource = StockCardResource::class;

    protected string $view = 'filament.helpdesk.stock-cards.view';

    public StockCard $record;

    public string $reviewNote = '';

    public string $rejectionReason = '';

    /** @var array<int, array{actual_qty: string, supervisor_notes: string}> */
    public array $entryRows = [];

    private const VARIANCE_TOLERANCE = 0.0001;

    public function mount(StockCard $record): void
    {
        abort_unless(StockCardResource::canView($record), 403);

        $this->record = $this->loadRecord($record);
        $this->loadEntryRows();
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
        return in_array($this->record->status, [StockCardStatus::PendingSupervisor, StockCardStatus::PendingFinance], true)
            && ($this->canReviewAsSupervisor() || $this->canReviewAsFinance());
    }

    public function refetchEsb(): void
    {
        abort_unless($this->canRefetchEsb(), 403);

        $branch = $this->record->branch;

        if (! $branch?->hasEsbIntegration()) {
            Notification::make()->title('Branch belum memiliki konfigurasi ESB')->warning()->send();

            return;
        }

        $result = (new EsbService)->getDailySalesMaterialUsageForBranch(
            $branch,
            $this->record->report_date->toDateString(),
            $this->record->flag_unit,
        );

        if (empty($result['rows'])) {
            // ESB only computes this report after the branch's daily closing —
            // an empty result usually means closing hasn't happened yet, not
            // that every product truly had zero usage. Leave system_qty and
            // system_fetched_at untouched so approval stays gated and the
            // Supervisor knows to come back and refresh again after closing.
            Notification::make()
                ->title('Belum ada data ESB untuk tanggal ini')
                ->body('Kemungkinan toko belum melakukan closing harian. Coba refresh lagi setelah closing dilakukan.')
                ->warning()
                ->send();

            return;
        }

        $usageByCode = collect($result['rows'])->keyBy('productCode');

        foreach ($this->record->entries as $entry) {
            $usage = $usageByCode->get($entry->product_code);
            $entry->update(['system_qty' => $usage ? (float) $usage['totalQty'] : 0]);
        }

        $this->record->update(['system_fetched_at' => now()]);
        $this->refreshRecord();

        if ($result['ok']) {
            Notification::make()->title('Data sistem berhasil diperbarui dari ESB')->success()->send();
        } else {
            Notification::make()
                ->title('Data sistem dimuat sebagian')
                ->body('Salah satu atau lebih pasangan kode ESB branch ini gagal diambil.')
                ->warning()
                ->send();
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
