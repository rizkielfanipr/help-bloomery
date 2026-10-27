<?php

namespace App\Actions\SalesReport;

use App\Enums\SalesReportStatus;
use App\Models\SalesReport;
use App\Models\SalesReportApproval;
use App\Models\User;
use App\Services\SalesReport\SalesReportAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Finance rejection of a `Completed` report (docs/products/finance/sales-report-compliment-void-remediation/prd.md
 * §7.4). Final in this scope: the date scores 0 through the existing calculator. Corrections,
 * settlement/MDR, Compliment, Void, and attachments are left untouched.
 */
class RejectCompletedSalesReportAction
{
    public function __construct(private readonly SalesReportAccess $access) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(SalesReport $report, User $actor, ?string $reason): SalesReport
    {
        if (! $this->access->canRejectCompleted($actor, $report)) {
            throw new AuthorizationException('Anda tidak berhak menolak Sales Report ini.');
        }

        $reason = trim((string) $reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();

        return DB::transaction(function () use ($report, $actor, $reason): SalesReport {
            $locked = SalesReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($locked->status !== SalesReportStatus::Completed) {
                throw ValidationException::withMessages(['status' => 'Sales Report sudah tidak berstatus Completed. Muat ulang halaman.']);
            }

            $locked->update([
                'status' => SalesReportStatus::Rejected->value,
                'finance_reviewed_by' => $actor->id,
                'finance_reviewed_at' => now(),
            ]);

            SalesReportApproval::query()->create([
                'sales_report_id' => $locked->id,
                'stage' => 'finance',
                'action' => 'rejected',
                'actor_id' => $actor->id,
                'notes' => $reason,
                'revision_number' => $locked->revision_number,
                'metadata' => ['previous_status' => SalesReportStatus::Completed->value],
            ]);

            return $locked->refresh();
        });
    }
}
