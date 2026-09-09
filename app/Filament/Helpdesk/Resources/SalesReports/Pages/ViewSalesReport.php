<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Enums\SalesReportStatus;
use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Models\SalesReport;
use App\Models\SalesReportApproval;
use App\Models\SalesReportReconciliation;
use App\Services\SalesReportReconciliationService;
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

    /** @var array<int, array{store_amount: string, notes: string}> */
    public array $supervisorRows = [];

    public function mount(SalesReport $record): void
    {
        abort_unless(SalesReportResource::canView($record), 403);

        $this->record = $this->loadRecord($record);
        $this->loadSupervisorRows();
    }

    public function getTitle(): string
    {
        return collect([
            'Sales Report',
            $this->record->branch->name,
            $this->record->report_date->isoFormat('D MMMM Y'),
        ])->join(' · ');
    }

    public function canReviewAsSupervisor(): bool
    {
        $user = auth()->user();

        return $this->record->status === SalesReportStatus::PendingSupervisor
            && $user?->can('review sales reports as supervisor')
            && ($user->canAccessAllBranches() || ! $this->record->shiftSubmissions->contains('submitted_by', $user->id))
            && ($user->canAccessAllBranches() || $user->canAccessBranch($this->record->branch_id));
    }

    public function canReviewAsFinance(): bool
    {
        $user = auth()->user();

        return $this->record->status === SalesReportStatus::PendingFinance
            && $user?->can('review sales reports as finance')
            && ($user->canAccessAllBranches() || ! $this->record->shiftSubmissions->contains('submitted_by', $user->id));
    }

    public function canRefetchEsb(): bool
    {
        return in_array($this->record->status, [SalesReportStatus::PendingSupervisor, SalesReportStatus::PendingFinance], true)
            && ($this->canReviewAsSupervisor() || $this->canReviewAsFinance());
    }

    public function refetchEsb(SalesReportReconciliationService $reconciliationService): void
    {
        abort_unless($this->canRefetchEsb(), 403);

        $fetchedOk = $reconciliationService->reconcile($this->record);

        $this->refreshRecord();

        if ($fetchedOk) {
            Notification::make()->title('Data sistem berhasil diperbarui dari ESB')->success()->send();
        } else {
            Notification::make()->title('Gagal mengambil data dari ESB')->danger()->send();
        }
    }

    public function approveSupervisor(): void
    {
        abort_unless($this->canReviewAsSupervisor(), 403);

        foreach ($this->record->reconciliations as $reconciliation) {
            $this->validate([
                "supervisorRows.{$reconciliation->id}.store_amount" => ['required', 'numeric', 'min:0'],
                "supervisorRows.{$reconciliation->id}.notes" => ['nullable', 'string', 'max:2000'],
            ]);

            $row = $this->supervisorRows[$reconciliation->id];
            if (
                $reconciliation->system_amount !== null
                && abs((float) $row['store_amount'] - (float) $reconciliation->system_amount) > 0.009
                && trim($row['notes']) === ''
            ) {
                throw ValidationException::withMessages([
                    "supervisorRows.{$reconciliation->id}.notes" => 'Supervisor notes are required while System Sales and corrected Store Sales differ.',
                ]);
            }
        }

        DB::transaction(function (): void {
            $report = SalesReport::query()->lockForUpdate()->findOrFail($this->record->id);
            abort_unless($report->status === SalesReportStatus::PendingSupervisor, 409);

            foreach ($report->reconciliations as $reconciliation) {
                $row = $this->supervisorRows[$reconciliation->id];
                $reconciliation->update([
                    'store_amount' => (float) $row['store_amount'],
                    'supervisor_notes' => trim($row['notes']) ?: null,
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

        $this->transitionReview(
            expected: SalesReportStatus::PendingFinance,
            next: SalesReportStatus::Completed,
            stage: 'finance',
            action: 'approved',
            notes: trim($this->reviewNote) ?: null,
            values: [
                'status' => SalesReportStatus::Completed->value,
                'finance_reviewed_by' => auth()->id(),
                'finance_reviewed_at' => now(),
                'finance_note' => trim($this->reviewNote) ?: null,
            ],
        );
        Notification::make()->title('Status updated to Completed')->success()->send();
    }

    public function rejectFinance(): void
    {
        abort_unless($this->canReviewAsFinance(), 403);
        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->transitionReview(
            expected: SalesReportStatus::PendingFinance,
            next: SalesReportStatus::Rejected,
            stage: 'finance',
            action: 'rejected',
            notes: trim($this->rejectionReason),
            values: [
                'finance_reviewed_by' => auth()->id(),
                'finance_reviewed_at' => now(),
            ],
        );

        Notification::make()->title('Sales Report rejected')->danger()->send();
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

    private function loadSupervisorRows(): void
    {
        $this->supervisorRows = $this->record->reconciliations->mapWithKeys(fn (SalesReportReconciliation $reconciliation): array => [
            $reconciliation->id => [
                'store_amount' => (string) $reconciliation->store_amount,
                'notes' => $reconciliation->supervisor_notes ?? '',
            ],
        ])->all();
    }

    private function loadRecord(SalesReport $record): SalesReport
    {
        return $record->load([
            'branch', 'entries', 'employees', 'reconciliations',
            'shiftSubmissions.submittedBy', 'supervisorReviewer', 'financeReviewer',
            'approvals.actor',
            'basketSizeRecords.employeeRecords',
            'compliments.submittedBy',
        ]);
    }

    private function refreshRecord(): void
    {
        $this->record = $this->loadRecord($this->record->fresh());
        $this->loadSupervisorRows();
    }
}
