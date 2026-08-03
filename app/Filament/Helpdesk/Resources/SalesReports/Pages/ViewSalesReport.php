<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Models\SalesReport;
use App\Models\SalesReportApproval;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewSalesReport extends Page
{
    protected static string $resource = SalesReportResource::class;

    protected string $view = 'filament.helpdesk.sales-reports.view';

    public SalesReport $record;

    public string $reviewNote = '';

    public string $rejectionReason = '';

    /** @var array<int, array{settlement: string, note: string}> */
    public array $settlementRows = [];

    /** @var array<int, array{sales_store: string, notes: string}> */
    public array $supervisorRows = [];

    private const SETTLEMENT_TOLERANCE = 100.0;

    public function mount(SalesReport $record): void
    {
        abort_unless(SalesReportResource::canView($record), 403);

        $this->record = $record->load([
            'branch', 'submittedBy', 'entries', 'supervisorReviewer',
            'financeReviewer', 'approvals.actor',
        ]);
        $this->loadSettlementRows();
        $this->loadSupervisorRows();
    }

    public function getTitle(): string
    {
        return collect([
            'Sales Report',
            $this->record->branch->name,
            'Shift '.$this->record->shift_number,
            $this->record->report_date->isoFormat('D MMMM Y'),
        ])->join(' · ');
    }

    public function canReviewAsSupervisor(): bool
    {
        $user = auth()->user();

        return $this->record->status === SalesReportStatus::PendingSupervisor
            && $user?->can('review sales reports as supervisor')
            && ($user->canAccessAllBranches() || $user->id !== $this->record->submitted_by)
            && ($user->canAccessAllBranches() || $user->canAccessBranch($this->record->branch_id));
    }

    public function canReviewAsFinance(): bool
    {
        $user = auth()->user();

        return $this->record->status === SalesReportStatus::PendingFinance
            && $user?->can('review sales reports as finance')
            && $user->can('input sales settlements')
            && ($user->canAccessAllBranches() || $user->id !== $this->record->submitted_by);
    }

    public function approveSupervisor(): void
    {
        abort_unless($this->canReviewAsSupervisor(), 403);

        foreach ($this->record->entries as $entry) {
            $this->validate([
                "supervisorRows.{$entry->id}.sales_store" => ['required', 'numeric', 'min:0'],
                "supervisorRows.{$entry->id}.notes" => ['nullable', 'string', 'max:2000'],
            ]);

            $row = $this->supervisorRows[$entry->id];
            if (
                abs((float) $row['sales_store'] - (float) $entry->sales_system_amount) > 0.009
                && trim($row['notes']) === ''
            ) {
                throw ValidationException::withMessages([
                    "supervisorRows.{$entry->id}.notes" => 'Supervisor notes are required while System Sales and corrected Store Sales differ.',
                ]);
            }
        }

        DB::transaction(function (): void {
            $report = SalesReport::query()->lockForUpdate()->findOrFail($this->record->id);
            abort_unless($report->status === SalesReportStatus::PendingSupervisor, 409);

            foreach ($report->entries as $entry) {
                $row = $this->supervisorRows[$entry->id];
                $entry->update([
                    'sales_store_amount' => (float) $row['sales_store'],
                    'notes' => trim($row['notes']) ?: null,
                    'settlement_amount' => null,
                    'mdr_percentage' => null,
                    'mdr_amount' => null,
                    'expected_settlement_amount' => null,
                    'settlement_difference' => null,
                    'reconciliation_status' => null,
                    'finance_note' => null,
                ]);
            }

            $report->update([
                'status' => SalesReportStatus::PendingFinance->value,
                'supervisor_reviewed_by' => auth()->id(),
                'supervisor_reviewed_at' => now(),
                'supervisor_note' => trim($this->reviewNote) ?: null,
                'rejection_reason' => null,
            ]);
            $this->recordApproval($report, 'supervisor', 'approved', trim($this->reviewNote) ?: null);
        });

        $this->refreshRecord();
        $this->reset(['reviewNote', 'rejectionReason']);

        Notification::make()->title('Status updated to Finance Review')->success()->send();
    }

    public function approveFinance(): void
    {
        abort_unless($this->canReviewAsFinance(), 403);

        foreach ($this->record->entries as $entry) {
            $this->validate([
                "settlementRows.{$entry->id}.settlement" => ['required', 'numeric', 'min:0'],
                "settlementRows.{$entry->id}.note" => ['nullable', 'string', 'max:2000'],
            ]);

            $row = $this->settlementRows[$entry->id];
            $mdrAmount = max(0, round((float) $entry->sales_system_amount - (float) $row['settlement'], 2));
            $expected = round((float) $entry->sales_system_amount - $mdrAmount, 2);
            $difference = round((float) $row['settlement'] - $expected, 2);

            if (abs($difference) > self::SETTLEMENT_TOLERANCE && trim($row['note']) === '') {
                throw ValidationException::withMessages([
                    "settlementRows.{$entry->id}.note" => 'Finance notes are required when the settlement difference exceeds Rp100.',
                ]);
            }
        }

        DB::transaction(function (): void {
            $report = SalesReport::query()->lockForUpdate()->findOrFail($this->record->id);
            abort_unless($report->status === SalesReportStatus::PendingFinance, 409);

            foreach ($report->entries as $entry) {
                $row = $this->settlementRows[$entry->id];
                $settlement = (float) $row['settlement'];
                $system = (float) $entry->sales_system_amount;
                $mdrAmount = max(0, round($system - $settlement, 2));
                $mdr = $system > 0 ? round(($mdrAmount / $system) * 100, 4) : 0;
                $expected = round((float) $entry->sales_system_amount - $mdrAmount, 2);
                $difference = round($settlement - $expected, 2);
                $status = match (true) {
                    abs($difference) <= self::SETTLEMENT_TOLERANCE => 'matched',
                    $difference < 0 => 'under',
                    default => 'over',
                };

                $entry->update([
                    'settlement_amount' => $settlement,
                    'mdr_percentage' => $mdr,
                    'mdr_amount' => $mdrAmount,
                    'expected_settlement_amount' => $expected,
                    'settlement_difference' => $difference,
                    'reconciliation_status' => $status,
                    'finance_note' => trim($row['note']) ?: null,
                ]);
            }

            $report->update([
                'status' => SalesReportStatus::Completed->value,
                'finance_reviewed_by' => auth()->id(),
                'finance_reviewed_at' => now(),
                'finance_note' => trim($this->reviewNote) ?: null,
                'rejection_reason' => null,
            ]);
            $this->recordApproval($report, 'finance', 'approved', trim($this->reviewNote) ?: null);
        });

        $this->refreshRecord();
        Notification::make()->title('Status updated to Completed')->success()->send();
    }

    public function rejectFinance(): void
    {
        abort_unless($this->canReviewAsFinance(), 403);
        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->transitionReview(
            expected: SalesReportStatus::PendingFinance,
            next: SalesReportStatus::PendingSupervisor,
            stage: 'finance',
            action: 'rejected',
            notes: trim($this->rejectionReason),
            values: [
                'finance_reviewed_by' => auth()->id(),
                'finance_reviewed_at' => now(),
                'rejection_reason' => trim($this->rejectionReason),
            ],
        );

        Notification::make()->title('Report returned to Supervisor Review')->warning()->send();
    }

    public function settlementPreview(int $entryId): array
    {
        $entry = $this->record->entries->firstWhere('id', $entryId);
        $row = $this->settlementRows[$entryId] ?? ['settlement' => ''];
        $system = (float) $entry?->sales_system_amount;
        $mdrAmount = $row['settlement'] === '' ? null : max(0, round($system - (float) $row['settlement'], 2));
        $mdrPercentage = $mdrAmount !== null && $system > 0 ? round(($mdrAmount / $system) * 100, 4) : null;
        $expected = $mdrAmount === null ? null : round($system - $mdrAmount, 2);
        $difference = $row['settlement'] === '' ? null : round((float) $row['settlement'] - $expected, 2);

        return compact('mdrAmount', 'mdrPercentage', 'expected', 'difference');
    }

    private function transitionReview(
        SalesReportStatus $expected,
        SalesReportStatus $next,
        string $stage,
        string $action,
        ?string $notes,
        array $values,
    ): void {
        DB::transaction(function () use ($expected, $next, $stage, $action, $notes, $values): void {
            $report = SalesReport::query()->lockForUpdate()->findOrFail($this->record->id);
            abort_unless($report->status === $expected, 409);
            $report->update(array_merge($values, ['status' => $next->value]));
            $this->recordApproval($report, $stage, $action, $notes);
        });

        $this->refreshRecord();
        $this->reset(['reviewNote', 'rejectionReason']);
    }

    private function recordApproval(SalesReport $report, string $stage, string $action, ?string $notes): void
    {
        SalesReportApproval::create([
            'sales_report_id' => $report->id,
            'stage' => $stage,
            'action' => $action,
            'actor_id' => auth()->id(),
            'notes' => $notes,
            'revision_number' => $report->revision_number,
        ]);
    }

    private function loadSettlementRows(): void
    {
        $this->settlementRows = $this->record->entries->mapWithKeys(fn ($entry): array => [
            $entry->id => [
                'settlement' => $entry->settlement_amount !== null ? (string) $entry->settlement_amount : '',
                'note' => $entry->finance_note ?? '',
            ],
        ])->all();
    }

    private function loadSupervisorRows(): void
    {
        $this->supervisorRows = $this->record->entries->mapWithKeys(fn ($entry): array => [
            $entry->id => [
                'sales_store' => (string) $entry->sales_store_amount,
                'notes' => $entry->notes ?? '',
            ],
        ])->all();
    }

    private function refreshRecord(): void
    {
        $this->record = $this->record->fresh([
            'branch', 'submittedBy', 'entries', 'supervisorReviewer',
            'financeReviewer', 'approvals.actor',
        ]);
        $this->loadSettlementRows();
        $this->loadSupervisorRows();
    }
}
