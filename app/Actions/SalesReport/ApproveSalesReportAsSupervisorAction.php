<?php

namespace App\Actions\SalesReport;

use App\Enums\SalesReportStatus;
use App\Models\SalesReport;
use App\Models\SalesReportApproval;
use App\Models\SalesReportReconciliation;
use App\Models\User;
use App\Services\SalesReport\SalesReportAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Supervisor approval (docs/products/finance/sales-report-compliment-void-remediation/prd.md §7.3): the only
 * positive approval. It saves the correction rows and moves `PendingSupervisor` straight to
 * `Completed` (score 100) in one locked transaction with exactly one audit row; Finance fields
 * are left untouched. A second click finds the status already changed and is refused.
 */
class ApproveSalesReportAsSupervisorAction
{
    public function __construct(private readonly SalesReportAccess $access) {}

    /**
     * @param  array<int|string, array{store_amount: mixed, notes: ?string}>  $corrections  keyed by reconciliation ID
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(SalesReport $report, User $actor, array $corrections, ?string $note): SalesReport
    {
        if (! $this->access->canReviewAsSupervisor($actor, $report)) {
            throw new AuthorizationException('Anda tidak berhak menyetujui Sales Report ini.');
        }

        $note = filled($note) ? trim((string) $note) : null;
        $reconciliations = $report->reconciliations()->get();
        $this->validateCorrections($reconciliations->all(), $corrections, $note);

        return DB::transaction(function () use ($report, $actor, $corrections, $note): SalesReport {
            $locked = SalesReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($locked->status !== SalesReportStatus::PendingSupervisor) {
                throw ValidationException::withMessages(['status' => 'Sales Report sudah diproses. Muat ulang halaman.']);
            }

            foreach ($locked->reconciliations()->get() as $reconciliation) {
                $row = $corrections[$reconciliation->id];
                $reconciliation->update([
                    'store_amount' => (float) $row['store_amount'],
                    'supervisor_notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                ]);
            }

            $locked->update([
                'status' => SalesReportStatus::Completed->value,
                'supervisor_reviewed_by' => $actor->id,
                'supervisor_reviewed_at' => now(),
                'supervisor_note' => $note,
            ]);

            SalesReportApproval::query()->create([
                'sales_report_id' => $locked->id,
                'stage' => 'supervisor',
                'action' => 'approved',
                'actor_id' => $actor->id,
                'notes' => $note,
                'revision_number' => $locked->revision_number,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Same rules as before the extraction: every row needs a non-negative amount, and a note
     * while the corrected Store Sales still differ from System Sales.
     *
     * @param  list<SalesReportReconciliation>  $reconciliations
     * @param  array<int|string, array<string, mixed>>  $corrections
     *
     * @throws ValidationException
     */
    private function validateCorrections(array $reconciliations, array $corrections, ?string $note): void
    {
        $rules = [];
        foreach ($reconciliations as $reconciliation) {
            $rules["corrections.{$reconciliation->id}.store_amount"] = ['required', 'numeric', 'min:0'];
            $rules["corrections.{$reconciliation->id}.notes"] = ['nullable', 'string', 'max:2000'];
        }
        Validator::make(['corrections' => $corrections, 'note' => $note], $rules + ['note' => ['nullable', 'string', 'max:2000']])->validate();

        foreach ($reconciliations as $reconciliation) {
            $row = $corrections[$reconciliation->id];
            if ($reconciliation->system_amount !== null
                && abs((float) $row['store_amount'] - (float) $reconciliation->system_amount) > 0.009
                && trim((string) ($row['notes'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "corrections.{$reconciliation->id}.notes" => 'Supervisor notes are required while System Sales and corrected Store Sales differ.',
                ]);
            }
        }
    }
}
