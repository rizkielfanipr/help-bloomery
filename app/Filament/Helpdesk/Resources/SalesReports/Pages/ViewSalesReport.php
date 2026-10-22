<?php

namespace App\Filament\Helpdesk\Resources\SalesReports\Pages;

use App\Actions\SalesReport\ApproveSalesReportAsSupervisorAction;
use App\Actions\SalesReport\RejectCompletedSalesReportAction;
use App\Filament\Helpdesk\Resources\SalesReports\SalesReportResource;
use App\Models\SalesReport;
use App\Models\SalesReportReconciliation;
use App\Services\SalesReport\SalesReportAccess;
use App\Services\SalesReportReconciliationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
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
        return app(SalesReportAccess::class)->canReviewAsSupervisor(auth()->user(), $this->record);
    }

    public function canRejectCompleted(): bool
    {
        return app(SalesReportAccess::class)->canRejectCompleted(auth()->user(), $this->record);
    }

    /**
     * Only the Supervisor refreshes System Sales, while the report still awaits their review.
     */
    public function canRefetchEsb(): bool
    {
        return $this->canReviewAsSupervisor();
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

    public function approveSupervisor(ApproveSalesReportAsSupervisorAction $approve): void
    {
        abort_unless($this->canReviewAsSupervisor(), 403);

        try {
            $approve->execute($this->record, auth()->user(), $this->supervisorRows, $this->reviewNote);
        } catch (ValidationException $exception) {
            if ($this->reportStatusChanged($exception)) {
                return;
            }

            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => [preg_replace('/^corrections\./', 'supervisorRows.', $key) => $messages])
                ->all());
        }

        $this->refreshRecord();
        $this->reset(['reviewNote', 'rejectionReason']);

        Notification::make()->title('Sales Report approved')->body('Status updated to Completed.')->success()->send();
    }

    public function rejectCompleted(RejectCompletedSalesReportAction $reject): void
    {
        abort_unless($this->canRejectCompleted(), 403);
        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:2000']]);

        try {
            $reject->execute($this->record, auth()->user(), $this->rejectionReason);
        } catch (ValidationException $exception) {
            if ($this->reportStatusChanged($exception)) {
                return;
            }

            throw ValidationException::withMessages(['rejectionReason' => $exception->errors()['reason'] ?? collect($exception->errors())->flatten()->all()]);
        }

        $this->refreshRecord();
        $this->reset(['reviewNote', 'rejectionReason']);

        Notification::make()->title('Completed Sales Report rejected')->danger()->send();
    }

    /**
     * Another request already moved the report (double click, second tab): show it and reload
     * instead of failing silently.
     */
    private function reportStatusChanged(ValidationException $exception): bool
    {
        if (! array_key_exists('status', $exception->errors())) {
            return false;
        }

        $this->refreshRecord();
        Notification::make()->title('Sales Report sudah diproses')->body($exception->errors()['status'][0])->warning()->send();

        return true;
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
            'voids.submittedBy',
        ]);
    }

    private function refreshRecord(): void
    {
        $this->record = $this->loadRecord($this->record->fresh());
        $this->loadSupervisorRows();
    }
}
