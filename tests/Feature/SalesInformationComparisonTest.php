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

    $branch = Branch::factory()->create([
        'name' => 'Bloomery Test',
        'esb_branch_code' => 'BTST',
        'esb_comcode' => 'TESTCO',
    ]);

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

    $branch = Branch::factory()->create([
        'name' => 'Bloomery Test',
        'esb_branch_code' => 'BTST',
        'esb_comcode' => 'TESTCO',
    ]);

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
