<?php

namespace App\Services\SalesReport;

use App\Enums\SalesReportStatus;
use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of Sales Report record access (docs/sales-report-compliment-void-remediation-prd.md
 * §16): branch scope for lists, detail, export, and evidence, plus the review rules. Pages only
 * use it to show or hide controls; every Action calls it again server-side.
 */
class SalesReportAccess
{
    /**
     * Limits a `sales_reports` query to the branches the user may see.
     */
    public function scopeReports(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $user->canAccessAllBranches()
            ? $query
            : $query->whereIn($query->qualifyColumn('branch_id'), $user->accessibleBranchIds());
    }

    /**
     * Limits a query of a Sales Report child (Compliment, Void) to accessible branches.
     */
    public function scopeChildren(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $user->canAccessAllBranches()
            ? $query
            : $query->whereHas('salesReport', fn (Builder $reports): Builder => $reports->whereIn('branch_id', $user->accessibleBranchIds()));
    }

    public function canView(?User $user, SalesReport $report): bool
    {
        return (bool) $user?->can('view sales reports')
            && ($user->canAccessAllBranches() || $user->canAccessBranch($report->branch_id));
    }

    /**
     * Compliment/Void evidence: back office viewers within scope, or Store staff of the report's
     * own branch (the Store app loads reports by the user's branch the same way).
     */
    public function canViewEvidence(?User $user, SalesReport $report): bool
    {
        return $this->canView($user, $report)
            || ($user !== null && $user->branch_id !== null && (int) $user->branch_id === (int) $report->branch_id);
    }

    public function canReviewAsSupervisor(?User $user, SalesReport $report): bool
    {
        return $report->status === SalesReportStatus::PendingSupervisor
            && (bool) $user?->can('review sales reports as supervisor')
            && $this->canView($user, $report)
            && ! $this->isOwnSubmission($user, $report);
    }

    public function canRejectCompleted(?User $user, SalesReport $report): bool
    {
        return $report->status === SalesReportStatus::Completed
            && (bool) $user?->can('review sales reports as finance')
            && $this->canView($user, $report)
            && ! $this->isOwnSubmission($user, $report);
    }

    /**
     * Self-review guard: a submitter may not review their own report unless they have the
     * existing full-access exception (administrators testing the flow).
     */
    private function isOwnSubmission(User $user, SalesReport $report): bool
    {
        if ($user->canAccessAllBranches()) {
            return false;
        }

        return $report->shiftSubmissions()->where('submitted_by', $user->id)->exists();
    }
}
