<?php

use App\Filament\Helpdesk\Resources\Branches\Pages\CreateBranch;
use App\Filament\Helpdesk\Resources\Branches\Pages\EditBranch;
use App\Models\Branch;
use App\Models\User;
use App\Services\EsbService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('reports hasEsbIntegration only when at least one active code pair exists', function () {
    $branch = Branch::factory()->create();

    expect($branch->hasEsbIntegration())->toBeFalse();

    $activePair = $branch->esbCodes()->create([
        'esb_branch_code' => 'BPL',
        'esb_comcode' => 'BLO16',
        'is_active' => true,
    ]);
    $branch->esbCodes()->create([
        'esb_branch_code' => 'BPL2',
        'esb_comcode' => 'BLO3',
        'is_active' => false,
    ]);

    $branch->refresh()->load('esbCodes');

    expect($branch->hasEsbIntegration())->toBeTrue()
        ->and($branch->activeEsbCodes())->toHaveCount(1)
        ->and($branch->activeEsbCodes()->first()->id)->toBe($activePair->id);
});

it('sums payment totals across every active ESB code pair for a branch', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.BLO16' => 'token-16',
        'esb.tokens.BLO3' => 'token-3',
    ]);

    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO3']);
    $branch->load('esbCodes');

    Http::fake(function (Request $request) {
        $isFirstComcode = str_contains((string) $request->header('Authorization')[0], 'token-16');

        return Http::response([[
            'salesNum' => $isFirstComcode ? 'A1' : 'B1',
            'salesDateOut' => '2026-08-01 10:00:00',
            'grandTotal' => 50000,
            'salesPayments' => [[
                'paymentMethodName' => 'CASH',
                'paymentMethodTypeName' => 'Cash',
                'paymentAmount' => 50000,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    $result = (new EsbService)->getPaymentSummaryForBranch($branch, '2026-08-01');

    expect($result['ok'])->toBeTrue()
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('CASH')
        ->and($result['rows'][0]['total'])->toBe(100000.0);
});

it('marks ok as false when one ESB code pair fails to fetch, but keeps the rows that did succeed', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.BLO16' => 'token-16',
        'esb.tokens.BLO3' => 'token-3',
    ]);

    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16']);
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO3']);
    $branch->load('esbCodes');

    Http::fake(function (Request $request) {
        $usesGoodToken = str_contains((string) $request->header('Authorization')[0], 'token-16');

        if (! $usesGoodToken) {
            return Http::response('server error', 500);
        }

        return Http::response([[
            'salesNum' => 'A1',
            'salesDateOut' => '2026-08-01 10:00:00',
            'grandTotal' => 50000,
            'salesPayments' => [[
                'paymentMethodName' => 'CASH',
                'paymentMethodTypeName' => 'Cash',
                'paymentAmount' => 50000,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    $result = (new EsbService)->getPaymentSummaryForBranch($branch, '2026-08-01');

    expect($result['ok'])->toBeFalse()
        ->and($result['rows'][0]['total'])->toBe(50000.0);
});

it('excludes an inactive ESB code pair from the merged fetch', function () {
    config()->set([
        'esb.base_url' => 'https://sales-esb.test',
        'esb.tokens.BLO16' => 'token-16',
    ]);

    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'is_active' => true]);
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL2', 'esb_comcode' => 'BLO3', 'is_active' => false]);
    $branch->load('esbCodes');

    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;

        return Http::response([[
            'salesNum' => 'A1',
            'salesDateOut' => '2026-08-01 10:00:00',
            'grandTotal' => 50000,
            'salesPayments' => [[
                'paymentMethodName' => 'CASH',
                'paymentMethodTypeName' => 'Cash',
                'paymentAmount' => 50000,
            ]],
        ]], 200, ['X-Pagination-Page-Count' => '1']);
    });

    $result = (new EsbService)->getPaymentSummaryForBranch($branch, '2026-08-01');

    expect($calls)->toBe(1)
        ->and($result['ok'])->toBeTrue()
        ->and($result['rows'][0]['total'])->toBe(50000.0);
});

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('SUPERADMIN');
});

it('allows adding multiple ESB code pairs to a branch through the admin form', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateBranch::class)
        ->fillForm([
            'name' => 'Branch Multi ESB',
            'sales_shift_count' => 2,
            'esbCodes' => [
                ['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'label' => 'NO LABEL', 'is_active' => true],
                ['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO3', 'label' => 'DINE IN', 'is_active' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $branch = Branch::where('name', 'Branch Multi ESB')->firstOrFail();

    expect($branch->esbCodes)->toHaveCount(2)
        ->and($branch->esbCodes->pluck('esb_comcode')->sort()->values()->all())->toBe(['BLO16', 'BLO3']);
});

it('lets an existing branch have an ESB code pair added via edit', function () {
    $this->actingAs($this->admin);
    $branch = Branch::factory()->create();
    $branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'label' => 'NO LABEL']);

    Livewire::test(EditBranch::class, ['record' => $branch->id])
        ->fillForm([
            'esbCodes' => [
                ['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'label' => 'NO LABEL', 'is_active' => true],
                ['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO3', 'label' => 'TAKEAWAY', 'is_active' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($branch->esbCodes()->count())->toBe(2);
});
