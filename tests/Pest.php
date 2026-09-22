<?php

use App\Actions\CalculateBasketSizeAction;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

beforeEach(function (): void {
    if ($this instanceof TestCase) {
        Http::preventStrayRequests();
    }
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Basket size helpers
|--------------------------------------------------------------------------
*/

function finalizeSale(string $number, string $dateOut, int $pax, int $revenue): array
{
    return [
        'salesNum' => $number,
        'salesDateOut' => $dateOut,
        'paxTotal' => $pax,
        'grandTotal' => $revenue,
        'salesPayments' => [[
            'paymentMethodName' => 'QRIS',
            'paymentMethodTypeName' => 'E-Wallet',
            'paymentAmount' => $revenue,
        ]],
    ];
}

function fakeEsbSales(array $sales, int $status = 200): void
{
    Http::fake(['https://esb.test/*' => Http::response($sales, $status, ['X-Pagination-Page-Count' => '1'])]);
}

/**
 * A shift whose sales report was submitted early, so its basket size only covers part of the shift.
 */
function submittedShift(Branch $branch, string $date, string $start, string $end, array $partialSales = []): BasketSizeRecord
{
    $branch->salesShifts()->firstOrCreate(['shift_number' => 1], ['name' => 'Shift 1', 'start_time' => $start, 'end_time' => $end, 'is_active' => true]);
    $report = SalesReport::factory()->create(['branch_id' => $branch->id, 'report_date' => $date]);
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $report->employees()->create([
        'shift_number' => 1,
        'employee_id' => $employee->id,
        'employee_code' => $employee->employee_code,
        'employee_name' => $employee->name,
        'employee_position' => $employee->position,
    ]);
    $report->replaceEsbTransactions(1, $partialSales);

    return app(CalculateBasketSizeAction::class)->execute($report->refresh(), 1);
}

/**
 * Drives the batched recalculation the way the browser does: start, then process batches until the queue is empty.
 */
function runRecalculation($page)
{
    $page->call('startRecalculation');

    for ($guard = 0; $guard < 100 && $page->get('recalculationQueue') !== []; $guard++) {
        $page->call('processRecalculationBatch');
    }

    return $page;
}
