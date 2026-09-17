<?php

namespace App\Console\Commands;

use App\Enums\SalesReportStatus;
use App\Models\SalesReportSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('sales-reports:auto-reject')]
#[Description('Reject sales reports awaiting Supervisor review beyond the configured calendar-day window')]
class SalesReportAutoRejectCommand extends Command
{
    public function handle(): int
    {
        $settings = SalesReportSettings::instance();
        if (! $settings->auto_reject_enabled) {
            $this->info('Sales Report auto-reject is disabled.');

            return self::SUCCESS;
        }
        $count = 0;
        $settings->eligibleReports()->select('id')->chunkById(100, function ($reports) use ($settings, &$count): void {
            foreach ($reports as $candidate) {
                $count += DB::transaction(function () use ($candidate, $settings): int {
                    $report = $settings->eligibleReports()->whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $report) {
                        return 0;
                    }
                    $reason = $settings->formattedAutoRejectReason();
                    $report->update(['status' => SalesReportStatus::RejectedBySystem, 'supervisor_reviewed_by' => null,
                        'supervisor_reviewed_at' => now(), 'supervisor_note' => $reason]);
                    $report->approvals()->create(['stage' => 'supervisor', 'action' => 'rejected', 'actor_id' => null,
                        'notes' => $reason, 'revision_number' => $report->revision_number,
                        'metadata' => ['source' => 'system', 'status' => SalesReportStatus::RejectedBySystem->value,
                            'auto_reject_after_days' => $settings->auto_reject_after_days,
                            'effective_from' => $settings->effective_from->toDateString(), 'submitted_at' => $report->submitted_at->toIso8601String()]]);

                    return 1;
                });
            }
        });
        $this->info("Auto-rejected {$count} sales report(s).");

        return self::SUCCESS;
    }
}
