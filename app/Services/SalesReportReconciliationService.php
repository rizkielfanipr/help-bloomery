<?php

namespace App\Services;

use App\Models\SalesReport;
use App\Models\SalesReportReconciliation;

class SalesReportReconciliationService
{
    public function __construct(private readonly EsbService $esb) {}

    /**
     * Refresh a report's reconciliation rows: sums staff-reported store
     * amounts across all shifts per (label, payment method) — DINE IN and
     * TAKEAWAY are reconciled separately (always overwritten — it's a pure
     * reflection of what staff entered) — and pulls the day's ESB system
     * totals once, per label. On first creation, `store_amount` (the
     * Supervisor's working figure) is seeded from the reported total; on
     * later re-fetches it is left untouched so a Supervisor's correction
     * survives pulling ESB data again.
     *
     * @return bool true if ESB data was fetched successfully for every label.
     */
    public function reconcile(SalesReport $report): bool
    {
        $report->loadMissing(['branch.esbCodes', 'entries']);
        $branch = $report->branch;

        $reportedTotals = $report->entries
            ->groupBy(fn ($entry) => $entry->label.'|'.$entry->payment_method_name)
            ->map(fn ($entries) => (float) $entries->sum('sales_store_amount'));

        $systemTotals = collect();
        $fetchedOk = false;

        if ($branch?->hasEsbIntegration()) {
            $groups = $this->esb->getPaymentSummaryByLabelForBranch($branch, $report->report_date->toDateString());
            $fetchedOk = ! empty($groups) && collect($groups)->every(fn (array $group): bool => $group['ok']);

            foreach ($groups as $label => $group) {
                foreach ($group['rows'] as $row) {
                    $systemTotals->put($label.'|'.$row['name'], (float) $row['total']);
                }
            }
        }

        $keys = $reportedTotals->keys()->merge($systemTotals->keys())->unique();

        foreach ($keys as $key) {
            [$label, $name] = explode('|', $key, 2);
            $reported = round($reportedTotals[$key] ?? 0.0, 2);

            $reconciliation = SalesReportReconciliation::firstOrNew([
                'sales_report_id' => $report->id,
                'payment_method_name' => $name,
                'label' => $label,
            ]);

            $isNew = ! $reconciliation->exists;

            $reconciliation->reported_store_amount = $reported;
            if ($isNew) {
                $reconciliation->store_amount = $reported;
            }

            if ($fetchedOk) {
                $reconciliation->system_amount = round($systemTotals[$key] ?? 0.0, 2);
                $reconciliation->system_fetched_at = now();
            }

            $reconciliation->save();
        }

        return $fetchedOk;
    }
}
