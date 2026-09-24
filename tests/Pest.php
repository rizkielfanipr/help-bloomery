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

/*
|--------------------------------------------------------------------------
| R&D Internal Memo BOM fixtures (docs/rnd-internal-memo-prd.md Phase 0 contract report)
|--------------------------------------------------------------------------
*/

/**
 * Phase 0 (docs/rnd-internal-memo-prd.md §19) — Contract validation.
 *
 * No live ESB Master Menu / ESB Core BOM response was available to sample directly from
 * this environment. These fixtures are reconstructed only from fields already read or
 * asserted by production consumers of `GET {ESB_CORE_BASE_URL}/product/bom/{bomID}`:
 *
 * - app/Services/EsbBillOfMaterialService.php (getBillOfMaterial, createAssembly bomTypeID)
 * - app/Filament/Helpdesk/Pages/CreateBomRecipePage.php / EditBomRecipePage.php
 * - app/Services/RndProjectMaterialForecastService.php (recursive WIP/Assembly resolution)
 * - tests/Feature/EsbBillOfMaterialServiceTest.php, tests/Feature/RndProjectMaterialForecastTest.php
 *
 * Fields NOT present anywhere in those consumers (output quantity/yield for the whole BOM,
 * a separate waste percentage, component-level `yieldPercent` read back from a GET response)
 * are deliberately left out of the "confirmed" fixtures below rather than invented. See the
 * Phase 0 report for the full list of open items that still require a real sample response.
 */
function internalMemoBomDetailFixture(string $bomTypeName, array $overrides = []): array
{
    return array_replace_recursive([
        'bomID' => 42,
        'bomCode' => 'BOM-000042',
        'bomName' => 'Croissant Butter',
        'bomTypeName' => $bomTypeName,
        'productID' => 9001,
        'productDetailID' => 15001,
        'productCode' => 'FG-CROBUT',
        'bomDetails' => [
            [
                'productDetailID' => 15002,
                'productID' => 9002,
                'productCode' => 'RAW-FLOUR',
                'productName' => 'Tepung Terigu Protein Tinggi',
                'categoryName' => 'Bahan Baku Makanan',
                'qty' => 250.0,
                'uomName' => 'GR',
                'tolerancePercent' => 2.0,
            ],
        ],
    ], $overrides);
}

/** BOM detail response for a Menu's own BOM (bomTypeID 3 per EsbBillOfMaterialService::createAssembly/CreateBomRecipePage). */
function internalMemoMenuBomDetailFixture(array $overrides = []): array
{
    return internalMemoBomDetailFixture('Menu', array_replace_recursive([
        'bomID' => 501,
        'bomCode' => 'BOM-000501',
        'bomName' => 'Croissant Butter - Menu',
        'productID' => 9101,
        'productDetailID' => 15101,
        'productCode' => 'MENU-CROBUT',
        'bomDetails' => [
            [
                'productDetailID' => 15002,
                'productID' => 9002,
                'productCode' => 'RAW-FLOUR',
                'productName' => 'Tepung Terigu Protein Tinggi',
                'categoryName' => 'Bahan Baku Makanan',
                'qty' => 250.0,
                'uomName' => 'GR',
                'tolerancePercent' => 2.0,
            ],
            [
                'productDetailID' => 15003,
                'productID' => 9003,
                'productCode' => 'BW1356',
                'productName' => 'Croissant Dough WIP',
                'categoryName' => 'Barang WIP',
                'qty' => 1.0,
                'uomName' => 'PCS',
                'tolerancePercent' => 0.0,
            ],
        ],
    ], $overrides));
}

/** BOM detail response for a WIP/Assembly's own BOM (bomTypeID 1, the "main" type used elsewhere in the app). */
function internalMemoAssemblyBomDetailFixture(array $overrides = []): array
{
    return internalMemoBomDetailFixture('Main', array_replace_recursive([
        'bomID' => 7301,
        'bomCode' => 'BOM-007301',
        'bomName' => 'Croissant Dough WIP',
        'productID' => 9003,
        'productDetailID' => 15003,
        'productCode' => 'BW1356',
    ], $overrides));
}
