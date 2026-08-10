<?php

namespace Database\Seeders;

use App\Enums\SalesReportStatus;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Models\SalesReportReconciliation;
use App\Models\SalesReportShiftSubmission;
use App\Models\User;
use App\Services\EsbService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SalesReportSeeder extends Seeder
{
    public function run(): void
    {
        SalesReportReconciliation::query()->delete();
        SalesReportShiftSubmission::query()->delete();
        SalesReportEntry::query()->delete();
        SalesReport::query()->delete();

        $esb = app(EsbService::class);

        if (! $esb->isConfigured()) {
            $this->command->warn('ESB not configured, skipping SalesReportSeeder.');

            return;
        }

        $branches = Branch::whereNotNull('esb_branch_code')
            ->whereNotNull('esb_comcode')
            ->get()
            ->filter(fn (Branch $b) => $b->esb_token !== '');

        if ($branches->isEmpty()) {
            $this->command->warn('No branches with esb_branch_code + configured ESB token found.');

            return;
        }

        $submittedBy = User::first()?->id;

        $dates = collect();
        for ($i = 13; $i >= 0; $i--) {
            $dates->push(Carbon::today()->subDays($i)->format('Y-m-d'));
        }

        foreach ($branches as $branch) {
            $this->command->info("Seeding branch: {$branch->name} ({$branch->esb_branch_code})");
            $shiftNumbers = $branch->salesShiftNumbers();

            foreach ($dates as $date) {
                try {
                    $rows = $esb->getPaymentSummary($branch->esb_branch_code, $date, $branch->esb_token);
                } catch (\RuntimeException $e) {
                    $this->command->warn("  [$date] ESB error: {$e->getMessage()}");

                    continue;
                }

                if (empty($rows)) {
                    $this->command->line("  [$date] No ESB data, skipping.");

                    continue;
                }

                $report = SalesReport::create([
                    'branch_id' => $branch->id,
                    'report_date' => $date,
                    'submitted_at' => Carbon::parse($date)->setTime(17, 0, 0),
                    'status' => SalesReportStatus::PendingSupervisor->value,
                ]);

                foreach ($shiftNumbers as $shiftNumber) {
                    SalesReportShiftSubmission::create([
                        'sales_report_id' => $report->id,
                        'shift_number' => $shiftNumber,
                        'submitted_by' => $submittedBy,
                        'submitted_at' => Carbon::parse($date)->setTime(17, 0, 0),
                    ]);
                }

                foreach ($rows as $row) {
                    $systemAmount = (float) $row['total'];

                    // Simulate realistic store input: ±5% variance or exact match,
                    // split evenly across however many shifts this branch has.
                    $variance = fake()->randomElement([0, 0, 0, fake()->numberBetween(-50000, 50000)]);
                    $storeAmount = max(0, $systemAmount + $variance);
                    $perShiftAmount = round($storeAmount / count($shiftNumbers), 2);

                    $selisih = $systemAmount - $storeAmount;
                    $notes = $selisih < 0
                        ? fake()->randomElement([
                            'Selisih cashier shift sore',
                            'Kurang setor QRIS',
                            'Kesalahan input kasir',
                            'Pending settlement bank',
                        ])
                        : null;

                    foreach ($shiftNumbers as $shiftNumber) {
                        SalesReportEntry::create([
                            'sales_report_id' => $report->id,
                            'shift_number' => $shiftNumber,
                            'payment_method_name' => $row['name'],
                            'sales_store_amount' => $perShiftAmount,
                            'notes' => $notes,
                        ]);
                    }

                    SalesReportReconciliation::create([
                        'sales_report_id' => $report->id,
                        'payment_method_name' => $row['name'],
                        'reported_store_amount' => $storeAmount,
                        'store_amount' => $storeAmount,
                        'system_amount' => $systemAmount,
                        'supervisor_notes' => $notes,
                        'system_fetched_at' => now(),
                    ]);
                }

                $this->command->info("  [$date] Seeded ".count($rows).' entries.');
            }
        }
    }
}
