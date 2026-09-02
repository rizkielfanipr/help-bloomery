<?php

namespace Database\Seeders;

use App\Actions\CalculateBasketSizeAction;
use App\Enums\SalesReportStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BasketSizeDemoSeeder extends Seeder
{
    private const BRANCH_NAME = 'Bloomery Demo Basket Size';

    /** @var array<int, array{code:string,name:string,position:string}> */
    private const EMPLOYEES = [
        ['code' => 'BS-DEMO-001', 'name' => 'Ayu Pratiwi', 'position' => 'Store Leader'],
        ['code' => 'BS-DEMO-002', 'name' => 'Bima Saputra', 'position' => 'Cashier'],
        ['code' => 'BS-DEMO-003', 'name' => 'Citra Lestari', 'position' => 'Barista'],
        ['code' => 'BS-DEMO-004', 'name' => 'Dimas Ramadhan', 'position' => 'Service Crew'],
        ['code' => 'BS-DEMO-005', 'name' => 'Eka Wulandari', 'position' => 'Service Crew'],
    ];

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        DB::transaction(function (): void {
            $branch = Branch::query()->updateOrCreate(
                ['name' => self::BRANCH_NAME],
                ['address' => 'Data simulasi Basket Size', 'is_active' => true, 'sales_shift_count' => 2],
            );

            $shifts = collect([
                ['shift_number' => 1, 'name' => 'Pagi', 'start_time' => '07:00:00', 'end_time' => '15:00:00'],
                ['shift_number' => 2, 'name' => 'Sore', 'start_time' => '15:00:00', 'end_time' => '23:00:00'],
            ])->map(fn (array $shift) => $branch->salesShifts()->updateOrCreate(
                ['shift_number' => $shift['shift_number']],
                $shift + ['is_active' => true],
            ));

            $employees = collect(self::EMPLOYEES)->map(fn (array $employee) => Employee::query()->updateOrCreate(
                ['employee_code' => $employee['code']],
                ['branch_id' => $branch->id, 'name' => $employee['name'], 'position' => $employee['position'], 'is_active' => true],
            ));

            $financeUser = User::query()->updateOrCreate(
                ['username' => 'DemoBasketFinance'],
                [
                    'name' => 'Demo Finance Basket Size',
                    'email' => null,
                    'password' => Hash::make('DemoBasket123!'),
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ],
            );
            $financeUser->syncRoles(['FINANCE_STAFF']);

            $calculator = app(CalculateBasketSizeAction::class);

            foreach (range(13, 0) as $daysAgo) {
                $date = CarbonImmutable::today()->subDays($daysAgo);
                $report = SalesReport::query()
                    ->whereBelongsTo($branch)
                    ->whereDate('report_date', $date->toDateString())
                    ->first() ?? new SalesReport(['branch_id' => $branch->id, 'report_date' => $date]);
                $report->fill([
                    'submitted_at' => $date->setTime(23, 5),
                    'status' => SalesReportStatus::Completed->value,
                ])->save();

                $report->entries()->delete();
                $report->shiftSubmissions()->delete();
                $report->employees()->delete();
                $report->esbTransactions()->delete();

                foreach ($shifts as $shift) {
                    $shiftNumber = $shift->shift_number;
                    $employeeCount = 2 + (($daysAgo + $shiftNumber) % 2);
                    $employeeOffset = ($daysAgo + $shiftNumber) % $employees->count();
                    $shiftEmployees = $employees->concat($employees)
                        ->slice($employeeOffset, $employeeCount)
                        ->values();
                    $revenue = 4200000 + (($daysAgo * 175000) + ($shiftNumber * 650000));
                    $pax = 70 + (($daysAgo * 3) + ($shiftNumber * 14));
                    $completedAt = $date->setTime($shiftNumber === 1 ? 12 : 19, 30);

                    $report->shiftSubmissions()->create([
                        'shift_number' => $shiftNumber,
                        'submitted_by' => $financeUser->id,
                        'submitted_at' => $completedAt,
                    ]);

                    $report->employees()->createMany($shiftEmployees->map(fn (Employee $employee): array => [
                        'shift_number' => $shiftNumber,
                        'employee_id' => $employee->id,
                        'employee_code' => $employee->employee_code,
                        'employee_name' => $employee->name,
                        'employee_position' => $employee->position,
                    ])->all());

                    foreach (['DINE IN', 'TAKEAWAY'] as $index => $label) {
                        $portion = $index === 0 ? 0.65 : 0.35;
                        $report->entries()->create([
                            'shift_number' => $shiftNumber,
                            'label' => $label,
                            'payment_method_name' => $index === 0 ? 'CASH' : 'QRIS',
                            'sales_store_amount' => round($revenue * $portion, 2),
                            'notes' => 'Data demo Basket Size',
                        ]);
                    }

                    $report->esbTransactions()->create([
                        'shift_number' => $shiftNumber,
                        'source_branch_code' => 'BS-DEMO',
                        'source_comcode' => 'BLSS',
                        'sales_num' => sprintf('BS-%s-%d', $date->format('Ymd'), $shiftNumber),
                        'sales_date_out' => $completedAt,
                        'payment_total' => $revenue,
                        'pax_total' => $pax,
                        'revenue_total' => $revenue,
                    ]);

                    $calculator->execute($report->fresh(), $shiftNumber);
                }
            }
        });

        $this->command?->info('Demo Basket Size siap: 14 hari, 2 shift, dan 5 employee.');
        $this->command?->line('Login Finance: DemoBasketFinance / DemoBasket123!');
    }
}
