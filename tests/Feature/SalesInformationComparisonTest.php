<?php

use App\Filament\Helpdesk\Pages\SalesInformationPage;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('calculates automatic YoY and previous-period date ranges', function () {
    Livewire::test(SalesInformationPage::class)
        ->set('dateFrom', '2026-06-01')
        ->set('dateTo', '2026-06-30')
        ->set('comparisonType', 'yoy')
        ->assertSet('comparisonDateFrom', '2025-06-01')
        ->assertSet('comparisonDateTo', '2025-06-30')
        ->set('comparisonType', 'previous_period')
        ->assertSet('comparisonDateFrom', '2026-05-02')
        ->assertSet('comparisonDateTo', '2026-05-31');
});

it('fetches both periods and calculates KPI and branch growth', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.TESTCO' => 'branch-token',
    ]);

    $branch = Branch::factory()->create(['name' => 'Bloomery Test']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BTST', 'esb_comcode' => 'TESTCO']);

    Http::fake(function (Request $request) {
        $from = $request->data()['salesDateFrom'] ?? null;
        $isPrimary = $from === '2026-06-01';

        return Http::response([[
            'salesDate' => $isPrimary ? '2026-06-01' : '2025-06-01',
            'salesDateIn' => ($isPrimary ? '2026-06-01' : '2025-06-01').'T10:00:00+07:00',
            'grandTotal' => $isPrimary ? 150000 : 100000,
            'paxTotal' => $isPrimary ? 3 : 2,
            'discountTotal' => 0,
            'menuDiscountTotal' => 0,
            'voucherDiscountTotal' => 0,
            'visitPurposeName' => 'Dine In',
            'branchCode' => 'BTST',
            'branchName' => 'Bloomery Test',
            'salesMenus' => [[
                'qty' => $isPrimary ? 3 : 2,
                'menuName' => 'Croissant',
                'menuCategoryName' => 'Pastry',
                'menuCategoryDetailName' => 'Viennoiserie',
                'total' => $isPrimary ? 150000 : 100000,
            ]],
            'salesPayments' => [[
                'paymentMethodName' => 'Cash',
                'paymentMethodTypeName' => 'Cash',
                'paymentAmount' => $isPrimary ? 150000 : 100000,
                'paymentMethodID' => 1,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    Livewire::test(SalesInformationPage::class)
        ->set('selectedBranchIds', [$branch->id])
        ->set('dateFrom', '2026-06-01')
        ->set('dateTo', '2026-06-30')
        ->set('comparisonType', 'yoy')
        ->call('fetch')
        ->assertSet('fetchPeriodIndex', 1)
        ->call('fetchNextPage')
        ->assertSet('fetched', true)
        ->assertSet('totalRevenue', 150000.0)
        ->assertSet('comparisonSummary.kpis.totalRevenue.change', 50.0)
        ->assertSet('comparisonSummary.branches.0.currentRevenue', 150000.0)
        ->assertSet('comparisonSummary.branches.0.comparisonRevenue', 100000.0);
});

it('excludes CASH change given back from total revenue and the payment method breakdown', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.TESTCO' => 'branch-token',
    ]);

    $branch = Branch::factory()->create(['name' => 'Bloomery Test']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BTST', 'esb_comcode' => 'TESTCO']);

    Http::fake(fn () => Http::response([[
        'salesDate' => '2026-06-01',
        'salesDateIn' => '2026-06-01T10:00:00+07:00',
        'grandTotal' => 35000,
        'paxTotal' => 1,
        'discountTotal' => 0,
        'menuDiscountTotal' => 0,
        'voucherDiscountTotal' => 0,
        'visitPurposeName' => 'Dine In',
        'branchCode' => 'BTST',
        'branchName' => 'Bloomery Test',
        'salesMenus' => [],
        'salesPayments' => [[
            'paymentMethodName' => 'CASH',
            'paymentMethodTypeName' => 'Cash',
            'paymentAmount' => 50000,
            'paymentMethodID' => 1,
        ]],
    ]], 200, ['X-Pagination-Page-Count' => '1']));

    $page = Livewire::test(SalesInformationPage::class)
        ->set('selectedBranchIds', [$branch->id])
        ->set('dateFrom', '2026-06-01')
        ->set('dateTo', '2026-06-30')
        ->call('fetch')
        ->call('fetchNextPage')
        ->assertSet('fetched', true)
        ->assertSet('totalRevenue', 35000.0);

    $cashRow = collect($page->get('paymentTable'))->firstWhere('name', 'CASH');
    expect($cashRow)->not->toBeNull()
        ->and((float) $cashRow['total'])->toBe(35000.0);
});

it('accumulates revenue per visit purpose alongside the transaction count', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.TESTCO' => 'branch-token',
    ]);

    $branch = Branch::factory()->create(['name' => 'Bloomery Test']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BTST', 'esb_comcode' => 'TESTCO']);

    Http::fake(fn () => Http::response([
        [
            'salesDate' => '2026-06-01',
            'salesDateIn' => '2026-06-01T10:00:00+07:00',
            'grandTotal' => 100000,
            'paxTotal' => 2,
            'discountTotal' => 0,
            'menuDiscountTotal' => 0,
            'voucherDiscountTotal' => 0,
            'visitPurposeName' => 'Dine In',
            'branchCode' => 'BTST',
            'branchName' => 'Bloomery Test',
            'salesMenus' => [],
            'salesPayments' => [[
                'paymentMethodName' => 'QRIS',
                'paymentMethodTypeName' => 'QRIS',
                'paymentAmount' => 100000,
                'paymentMethodID' => 2,
            ]],
        ],
        [
            'salesDate' => '2026-06-01',
            'salesDateIn' => '2026-06-01T11:00:00+07:00',
            'grandTotal' => 60000,
            'paxTotal' => 1,
            'discountTotal' => 0,
            'menuDiscountTotal' => 0,
            'voucherDiscountTotal' => 0,
            'visitPurposeName' => 'Takeaway',
            'branchCode' => 'BTST',
            'branchName' => 'Bloomery Test',
            'salesMenus' => [],
            'salesPayments' => [[
                'paymentMethodName' => 'QRIS',
                'paymentMethodTypeName' => 'QRIS',
                'paymentAmount' => 60000,
                'paymentMethodID' => 2,
            ]],
        ],
    ], 200, ['X-Pagination-Page-Count' => '1']));

    $page = Livewire::test(SalesInformationPage::class)
        ->set('selectedBranchIds', [$branch->id])
        ->set('dateFrom', '2026-06-01')
        ->set('dateTo', '2026-06-30')
        ->call('fetch')
        ->call('fetchNextPage')
        ->assertSet('fetched', true)
        ->assertSee('Seluruh Transaksi')
        ->assertSee('Detail Menu Transaksi');

    $chart = $page->get('chartVisitPurpose');

    expect($chart['labels'])->toBe(['Dine In', 'Takeaway'])
        ->and($chart['data'])->toBe([1, 1])
        ->and($chart['revenue'])->toEqual([100000.0, 60000.0]);
});

it('breaks visit purpose down per date across the selected period', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.TESTCO' => 'branch-token',
    ]);

    $branch = Branch::factory()->create(['name' => 'Bloomery Test']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BTST', 'esb_comcode' => 'TESTCO']);

    $sale = fn (string $date, string $purpose, float $amount) => [
        'salesDate' => $date,
        'salesDateIn' => "{$date}T10:00:00+07:00",
        'grandTotal' => $amount,
        'paxTotal' => 1,
        'discountTotal' => 0,
        'menuDiscountTotal' => 0,
        'voucherDiscountTotal' => 0,
        'visitPurposeName' => $purpose,
        'branchCode' => 'BTST',
        'branchName' => 'Bloomery Test',
        'salesMenus' => [],
        'salesPayments' => [[
            'paymentMethodName' => 'QRIS',
            'paymentMethodTypeName' => 'QRIS',
            'paymentAmount' => $amount,
            'paymentMethodID' => 2,
        ]],
    ];

    Http::fake(fn () => Http::response([
        $sale('2026-06-01', 'Dine In', 100000),
        $sale('2026-06-01', 'Takeaway', 40000),
        $sale('2026-06-02', 'Dine In', 70000),
    ], 200, ['X-Pagination-Page-Count' => '1']));

    $page = Livewire::test(SalesInformationPage::class)
        ->set('selectedBranchIds', [$branch->id])
        ->set('dateFrom', '2026-06-01')
        ->set('dateTo', '2026-06-30')
        ->call('fetch')
        ->assertSet('fetched', true);

    $rows = $page->get('visitPurposeByDate');

    expect($rows)->toEqual([
        ['date' => '2026-06-01', 'purpose' => 'Dine In', 'count' => 1, 'revenue' => 100000.0],
        ['date' => '2026-06-01', 'purpose' => 'Takeaway', 'count' => 1, 'revenue' => 40000.0],
        ['date' => '2026-06-02', 'purpose' => 'Dine In', 'count' => 1, 'revenue' => 70000.0],
    ]);
});

it('prepares a complete menu ranking and transaction detail for xlsx export', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.TESTCO' => 'branch-token',
    ]);

    $branch = Branch::factory()->create(['name' => 'Bloomery Test']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BTST', 'esb_comcode' => 'TESTCO']);

    $menus = collect(range(1, 12))->map(fn (int $number): array => [
        'qty' => 13 - $number,
        'menuName' => 'Menu '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        'menuCategoryName' => 'Food',
        'menuCategoryDetailName' => 'Main Course',
        'total' => (13 - $number) * 10000,
    ])->all();

    Http::fake(fn () => Http::response([
        [
            'salesNum' => 'SALE-001',
            'salesDate' => '2026-06-01',
            'salesDateOut' => '2026-06-01 10:15:00',
            'grandTotal' => 780000,
            'paxTotal' => 3,
            'discountTotal' => 20000,
            'menuDiscountTotal' => 20000,
            'voucherDiscountTotal' => 0,
            'visitPurposeName' => 'Dine In',
            'branchCode' => 'BTST',
            'branchName' => 'Bloomery Test',
            'salesMenus' => $menus,
            'salesPayments' => [
                [
                    'paymentMethodName' => 'CASH',
                    'paymentMethodTypeName' => 'Cash',
                    'paymentAmount' => 400000,
                    'paymentMethodID' => 1,
                ],
                [
                    'paymentMethodName' => 'QRIS',
                    'paymentMethodTypeName' => 'QRIS',
                    'paymentAmount' => 380000,
                    'paymentMethodID' => 2,
                ],
            ],
        ],
        [
            'salesNum' => 'SALE-002',
            'salesDate' => '2026-06-02',
            'salesDateOut' => '2026-06-02 11:30:00',
            'grandTotal' => 20000,
            'paxTotal' => 1,
            'discountTotal' => 0,
            'menuDiscountTotal' => 0,
            'voucherDiscountTotal' => 0,
            'visitPurposeName' => 'Takeaway',
            'branchCode' => 'BTST',
            'branchName' => 'Bloomery Test',
            'salesMenus' => [[
                'qty' => 2,
                'menuName' => 'Menu 12',
                'menuCategoryName' => 'Food',
                'menuCategoryDetailName' => 'Main Course',
                'total' => 20000,
            ]],
            'salesPayments' => [[
                'paymentMethodName' => 'QRIS',
                'paymentMethodTypeName' => 'QRIS',
                'paymentAmount' => 20000,
                'paymentMethodID' => 2,
            ]],
        ],
    ], 200, ['X-Pagination-Page-Count' => '1']));

    $page = Livewire::test(SalesInformationPage::class)
        ->set('selectedBranchIds', [$branch->id])
        ->set('dateFrom', '2026-06-01')
        ->set('dateTo', '2026-06-30')
        ->call('fetch')
        ->assertSet('fetched', true);

    $exportData = [];
    $page->assertDispatched('sales-loaded', function (string $event, array $params) use (&$exportData): bool {
        $exportData = $params;

        return true;
    });

    $ranking = $page->get('menuRanking');
    $transactions = $exportData['salesTransactions'];
    $transactionMenus = $exportData['salesTransactionMenus'];

    expect($ranking)->toHaveCount(12)
        ->and($ranking[0])->toMatchArray(['rank' => 1, 'name' => 'Menu 01', 'quantity' => 12])
        ->and($ranking[10])->toMatchArray(['rank' => 11, 'name' => 'Menu 12', 'quantity' => 3])
        ->and($ranking[11])->toMatchArray(['rank' => 12, 'name' => 'Menu 11', 'quantity' => 2])
        ->and($page->get('chartTopMenus.labels'))->toHaveCount(10)
        ->and($transactions)->toHaveCount(2)
        ->and($transactions[0])->toMatchArray([
            'salesNumber' => 'SALE-001',
            'salesDate' => '2026-06-01 10:15:00',
            'totalItems' => 78,
            'paymentTotal' => 780000.0,
            'paymentMethods' => 'CASH, QRIS',
        ])
        ->and($transactionMenus)->toHaveCount(13)
        ->and($transactionMenus[12])->toMatchArray([
            'salesNumber' => 'SALE-002',
            'menu' => 'Menu 12',
            'quantity' => 2,
        ]);
});

it('groups sales from three comcodes under their configured branch', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.COM_A' => 'token-a',
        'esb.tokens.COM_B' => 'token-b',
        'esb.tokens.COM_C' => 'token-c',
    ]);

    $branch = Branch::factory()->create(['name' => 'Bloomery Patisserie Tamansiswa']);
    $branch->esbCodes()->createMany([
        ['esb_branch_code' => 'ESB-A', 'esb_comcode' => 'COM_A'],
        ['esb_branch_code' => 'ESB-B', 'esb_comcode' => 'COM_B'],
        ['esb_branch_code' => 'ESB-C', 'esb_comcode' => 'COM_C'],
    ]);

    Http::fake(function (Request $request) {
        $branchCode = $request->data()['branchCode'];
        $amounts = ['ESB-A' => 100000, 'ESB-B' => 200000, 'ESB-C' => 300000];
        $pax = ['ESB-A' => 1, 'ESB-B' => 2, 'ESB-C' => 3];

        return Http::response([[
            'salesNum' => 'SALE-'.$branchCode,
            'salesDate' => '2026-06-01',
            'salesDateOut' => '2026-06-01 10:00:00',
            'grandTotal' => $amounts[$branchCode],
            'paxTotal' => $pax[$branchCode],
            'discountTotal' => 1000,
            'menuDiscountTotal' => 1000,
            'voucherDiscountTotal' => 0,
            'visitPurposeName' => 'Dine In',
            'branchCode' => $branchCode,
            'branchName' => 'Different ESB Name '.$branchCode,
            'salesMenus' => [],
            'salesPayments' => [[
                'paymentMethodName' => 'QRIS',
                'paymentMethodTypeName' => 'QRIS',
                'paymentAmount' => $amounts[$branchCode],
                'paymentMethodID' => 2,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    $page = Livewire::test(SalesInformationPage::class)
        ->set('selectedBranchIds', [$branch->id])
        ->set('dateFrom', '2026-06-01')
        ->set('dateTo', '2026-06-30')
        ->call('fetch')
        ->call('fetchNextPage')
        ->call('fetchNextPage')
        ->assertSet('fetched', true);

    $exportData = [];
    $page->assertDispatched('sales-loaded', function (string $event, array $params) use (&$exportData): bool {
        $exportData = $params;

        return true;
    });

    $branches = $page->get('branchTable');
    $transactions = $exportData['salesTransactions'];

    expect($branches)->toHaveCount(1)
        ->and($branches[0])->toMatchArray([
            'code' => 'ESB-A, ESB-B, ESB-C',
            'name' => 'Bloomery Patisserie Tamansiswa',
            'revenue' => 600000.0,
            'transactions' => 3,
            'pax' => 6,
            'avgPerTransaction' => 200000.0,
            'avgPerPax' => 100000.0,
            'discountTotal' => 3000.0,
        ])
        ->and(collect($transactions)->pluck('branch')->unique()->values()->all())
        ->toBe(['Bloomery Patisserie Tamansiswa']);
});
