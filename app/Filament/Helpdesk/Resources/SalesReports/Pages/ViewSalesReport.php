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

    private const SETTLEMENT_TOLERANCE = 100.0;

    public function mount(SalesReport $record): void
    {
        abort_unless(SalesReportResource::canView($record), 403);

        $this->record = $record->load([
            'branch', 'submittedBy', 'entries', 'supervisorReviewer',
            'financeReviewer', 'approvals.actor',
        ]);
        $this->loadSettlementRows();
    }

    public function getTitle(): string
    {
        return 'Sales Report Shift '.$this->record->shift_number.' — '.$this->record->branch->name.' '.$this->record->report_date->isoFormat('D MMMM Y');
    }

    public function canReviewAsSupervisor(): bool
    {
        $user = auth()->user();

        return $this->record->status === SalesReportStatus::PendingSupervisor
            && $user?->can('review sales reports as supervisor')
            && ($user->hasRole('SUPERADMIN') || $user->id !== $this->record->submitted_by)
            && ($user->hasRole('SUPERADMIN') || $user->canAccessBranch($this->record->branch_id));
    }

    public function canReviewAsFinance(): bool
    {
        $user = auth()->user();

        return $this->record->status === SalesReportStatus::PendingFinance
            && $user?->can('review sales reports as finance')
            && $user->can('input sales settlements')
            && ($user->hasRole('SUPERADMIN') || $user->id !== $this->record->submitted_by);
    }

    public function approveSupervisor(): void
    {
        abort_unless($this->canReviewAsSupervisor(), 403);

        $hasDifference = $this->record->entries->contains(
            fn ($entry): bool => abs((float) $entry->sales_store_amount - (float) $entry->sales_system_amount) > 0.009
        );
        if ($hasDifference && trim($this->reviewNote) === '') {
            throw ValidationException::withMessages([
                'reviewNote' => 'Catatan wajib diisi karena terdapat selisih Sales Store dan Sales System.',
            ]);
        }

        $this->transitionReview(
            expected: SalesReportStatus::PendingSupervisor,
            next: SalesReportStatus::PendingFinance,
            stage: 'supervisor',
            action: 'approved',
            notes: trim($this->reviewNote) ?: null,
            values: [
                'supervisor_reviewed_by' => auth()->id(),
                'supervisor_reviewed_at' => now(),
                'supervisor_note' => trim($this->reviewNote) ?: null,
                'rejection_reason' => null,
            ],
        );

        Notification::make()->title('Sales Report disetujui SPV')->success()->send();
    }

    public function rejectSupervisor(): void
    {
        abort_unless($this->canReviewAsSupervisor(), 403);
        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->transitionReview(
            expected: SalesReportStatus::PendingSupervisor,
            next: SalesReportStatus::RejectedBySupervisor,
            stage: 'supervisor',
            action: 'rejected',
            notes: trim($this->rejectionReason),
            values: [
                'supervisor_reviewed_by' => auth()->id(),
                'supervisor_reviewed_at' => now(),
                'rejection_reason' => trim($this->rejectionReason),
            ],
        );

        Notification::make()->title('Sales Report ditolak SPV')->danger()->send();
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
                    "settlementRows.{$entry->id}.note" => 'Catatan wajib untuk selisih settlement di atas Rp100.',
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
        Notification::make()->title('Rekonsiliasi Finance selesai')->success()->send();
    }

    public function rejectFinance(): void
    {
        abort_unless($this->canReviewAsFinance(), 403);
        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->transitionReview(
            expected: SalesReportStatus::PendingFinance,
            next: SalesReportStatus::RejectedByFinance,
            stage: 'finance',
            action: 'rejected',
            notes: trim($this->rejectionReason),
            values: [
                'finance_reviewed_by' => auth()->id(),
                'finance_reviewed_at' => now(),
                'rejection_reason' => trim($this->rejectionReason),
            ],
        );

        Notification::make()->title('Sales Report ditolak Finance')->danger()->send();
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

    private function refreshRecord(): void
    {
        $this->record = $this->record->fresh([
            'branch', 'submittedBy', 'entries', 'supervisorReviewer',
            'financeReviewer', 'approvals.actor',
        ]);
        $this->loadSettlementRows();
    }
}
