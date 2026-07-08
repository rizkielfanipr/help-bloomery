<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;

class SalesReportSeeder extends Seeder
{
    public function run(): void
    {
        $paymentMethodIds = PaymentMethod::pluck('id');

        $created = collect();
        $attempts = 0;

        while ($created->count() < 30 && $attempts < 100) {
            try {
                $created->push(SalesReport::factory()->create());
            } catch (UniqueConstraintViolationException) {
                // skip duplicate branch_id + report_date combination
            }
            $attempts++;
        }

        $created->each(function (SalesReport $report) use ($paymentMethodIds) {
            foreach ($paymentMethodIds as $methodId) {
                SalesReportEntry::factory()->create([
                    'sales_report_id' => $report->id,
                    'payment_method_id' => $methodId,
                ]);
            }
        });
    }
}
