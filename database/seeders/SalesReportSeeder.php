<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use Illuminate\Database\Seeder;

class SalesReportSeeder extends Seeder
{
    public function run(): void
    {
        $paymentMethodIds = PaymentMethod::pluck('id');

        SalesReport::factory(30)->create()->each(function (SalesReport $report) use ($paymentMethodIds) {
            foreach ($paymentMethodIds as $methodId) {
                SalesReportEntry::factory()->create([
                    'sales_report_id' => $report->id,
                    'payment_method_id' => $methodId,
                ]);
            }
        });
    }
}
