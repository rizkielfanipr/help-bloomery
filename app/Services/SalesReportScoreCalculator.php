<?php

namespace App\Services;

use App\Enums\SalesReportStatus;
use App\Models\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class SalesReportScoreCalculator
{
    /** @return array<int, array<string, mixed>> */
    public function calculate(Collection $branches, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();
        $end = $start->endOfMonth();
        $today = CarbonImmutable::today(config('app.timezone'));
        $reports = SalesReport::query()->whereIn('branch_id', $branches->modelKeys())
            ->whereDate('report_date', '>=', $start->toDateString())
            ->whereDate('report_date', '<=', $end->toDateString())
            ->with('shiftSubmissions')->get()->groupBy('branch_id');
        $branches->loadMissing('activeSalesShifts');
        $results = [];
        foreach ($branches as $branch) {
            $byDate = ($reports->get($branch->id) ?? collect())->keyBy(fn (SalesReport $report): string => $report->report_date->format('Y-m-d'));
            $exceptions = collect($branch->sales_assessment_excluded_dates ?? [])->keyBy('date');
            $days = [];
            $counts = ['required' => 0, 'passed' => 0, 'rejected' => 0, 'missing' => 0, 'pending' => 0];
            foreach ($start->daysUntil($end) as $date) {
                $key = $date->toDateString();
                $report = $byDate->get($key);
                $required = $branch->sales_assessment_started_at !== null
                    && $date->greaterThanOrEqualTo($branch->sales_assessment_started_at)
                    && $date->lessThan($today) && ! $exceptions->has($key);
                $passed = $report && $report->status === SalesReportStatus::Completed;
                $category = $passed ? 'passed' : (! $report ? 'missing' : (in_array($report->status, [SalesReportStatus::Rejected, SalesReportStatus::RejectedBySystem], true) ? 'rejected' : 'pending'));
                if ($required) {
                    $counts['required']++;
                    $counts[$category]++;
                }
                $reason = $exceptions->get($key)['reason'] ?? null;
                $days[] = [
                    'date' => $key, 'report_id' => $report?->id, 'status' => $report?->status->value ?? 'missing',
                    'required' => $required, 'score' => $required ? ($passed ? 100 : 0) : null,
                    'reason' => $reason ?? ($date->isSameDay($today) ? 'Berjalan' : (! $required ? 'Di luar periode penilaian' : null)),
                    'submitted_shifts' => $report?->shiftSubmissions->whereIn('shift_number', $branch->salesShiftNumbers())->unique('shift_number')->count() ?? 0,
                    'required_shifts' => count($branch->salesShiftNumbers()),
                ];
            }
            $results[$branch->id] = ['branch_id' => $branch->id, 'name' => $branch->name, 'started_at' => $branch->sales_assessment_started_at?->toDateString(),
                ...$counts, 'score' => $counts['required'] ? round($counts['passed'] / $counts['required'] * 100, 2) : null, 'days' => $days];
        }

        return $results;
    }
}
