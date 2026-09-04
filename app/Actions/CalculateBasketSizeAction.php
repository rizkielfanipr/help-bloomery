<?php

namespace App\Actions;

use App\Models\BasketSizeRecord;
use App\Models\SalesReport;

class CalculateBasketSizeAction
{
    public function execute(SalesReport $report, int $shiftNumber): BasketSizeRecord
    {
        $report->loadMissing(['branch.activeSalesShifts', 'employees', 'esbTransactions']);
        $shift = $report->branch?->configuredSalesShift($shiftNumber);
        $employees = $report->employees->where('shift_number', $shiftNumber);
        $transactions = $report->esbTransactions->where('shift_number', $shiftNumber);
        $revenue = (float) $transactions->sum('revenue_total');
        $totalPax = (int) $transactions->sum('pax_total');
        $staffCount = $employees->count();
        $basketSize = $totalPax > 0 ? $revenue / $totalPax : null;

        $record = BasketSizeRecord::query()->updateOrCreate(
            ['sales_report_id' => $report->id, 'shift_number' => $shiftNumber],
            [
                'branch_id' => $report->branch_id,
                'branch_sales_shift_id' => $shift?->id,
                'report_date' => $report->report_date,
                'shift_name' => $shift?->name ?? 'Shift '.$shiftNumber,
                'shift_start_time' => $shift?->start_time ?? ($shiftNumber === 1 ? '07:00:00' : '15:00:00'),
                'shift_end_time' => $shift?->end_time ?? ($shiftNumber === 1 ? '15:00:00' : '23:00:00'),
                'revenue' => $revenue,
                'total_pax' => $totalPax,
                'basket_size' => $basketSize,
                'staff_count' => $staffCount,
                'calculated_at' => now(),
            ],
        );

        $keptIds = [];
        foreach ($employees as $employee) {
            $employeeRecord = $record->employeeRecords()->updateOrCreate(
                ['employee_id' => $employee->employee_id],
                [
                    'sales_report_id' => $report->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->employee_name ?: 'Unknown Employee',
                    'employee_position' => $employee->employee_position,
                    'basket_size_credit' => $basketSize,
                ],
            );
            $keptIds[] = $employeeRecord->id;
        }

        $record->employeeRecords()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();

        return $record->load('employeeRecords');
    }
}
