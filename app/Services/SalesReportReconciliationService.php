<?php

namespace App\Services;

use App\Models\SalesReport;
use App\Models\SalesReportReconciliation;

class SalesReportReconciliationService
{
    public function __construct(private readonly EsbService $esb) {}

    /**
     * Refresh a report's reconciliation rows: sums staff-reported store
     * amounts across all shifts per payment method (always overwritten —
     * it's a pure reflection of what staff entered), and pulls the day's
     * ESB system totals once. On first creation, `store_amount` (the
     * Supervisor's working figure) is seeded from the reported total; on
     * later re-fetches it is left untouched so a Supervisor's correction
     * survives pulling ESB data again.
     *
     * @return bool true if ESB data was fetched successfully.
     */
    public function reconcile(SalesReport $report): bool
    {
        $report->loadMissing(['branch', 'entries']);
        $branch = $report->branch;

        $reportedTotals = $report->entries
            ->groupBy('payment_method_name')
            ->map(fn ($entries) => (float) $entries->sum('sales_store_amount'));

        $systemTotals = collect();
        $fetchedOk = false;

        if ($branch?->esb_branch_code && $branch->esb_token) {
            try {
                $rows = $this->esb->getPaymentSummary(
                    $branch->esb_branch_code,
                    $report->report_date->toDateString(),
                    $branch->esb_token,
                );
                $systemTotals = collect($rows)->pluck('total', 'name')->map(fn ($v) => (float) $v);
                $fetchedOk = true;
            } catch (\RuntimeException) {
                $fetchedOk = false;
            }
        }

        $names = $reportedTotals->keys()->merge($systemTotals->keys())->unique();

        foreach ($names as $name) {
            $reported = round($reportedTotals[$name] ?? 0.0, 2);

            $reconciliation = SalesReportReconciliation::firstOrNew([
                'sales_report_id' => $report->id,
                'payment_method_name' => $name,
            ]);

            $isNew = ! $reconciliation->exists;

            $reconciliation->reported_store_amount = $reported;
            if ($isNew) {
                $reconciliation->store_amount = $reported;
            }

            if ($fetchedOk) {
                $reconciliation->system_amount = round($systemTotals[$name] ?? 0.0, 2);
                $reconciliation->system_fetched_at = now();
            }

            $reconciliation->save();
        }

        return $fetchedOk;
    }
}
