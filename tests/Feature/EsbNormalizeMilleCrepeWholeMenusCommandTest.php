<?php

use App\Services\EsbItemJournalService;
use Illuminate\Support\Facades\Http;

function wholeMenu(int $id, string $code, string $short, array $overrides = []): array
{
    return $overrides + [
        'menuID' => $id, 'categoryDetail' => 'MILLE CREPE - WHOLE', 'bomID' => 5, 'menuCode' => $code, 'menuName' => "Menu {$id}",
        'flagActive' => 1, 'menuShortName' => $short, 'alternativeMenuName' => '', 'flagTax' => 'Yes, Tax', 'flagOtherTax' => 'Yes',
        'zeroValueText' => '0', 'salesAccount' => 'Sales - Food', 'cogsAccount' => 'COGS - Food', 'discountAccount' => 'Food Discount',
        'description' => '', 'menuImage' => 'No Image', 'flagOpenPrice' => 'No', 'printZeroValue' => 'Yes', 'themeMenuOnPos' => '', 'notes' => '',
        'menuTemplates' => [
            ['menuTemplateID' => 1, 'menuTemplateName' => 'A', 'flagActive' => 1, 'price' => '100000.0000'],
            ['menuTemplateID' => 2, 'menuTemplateName' => 'B', 'flagActive' => 0, 'price' => '0.0000'],
        ],
        'menuPackages' => ['flagSeparatePrintPackage' => 'No', 'flagSeparateTaxCalculation' => 'No', 'menuGroup' => []],
        'menuExtras' => [], 'menuIcons' => [], 'menuTags' => [], 'relatedMenus' => [],
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $menus
 * @param  callable|null  $afterUpdate  receives the posted payload and returns the menu ESB reports afterwards
 */
function fakeMenuEsb(array $menus, ?callable $afterUpdate = null): void
{
    config()->set(['esb.base_url' => 'https://esb.test', 'esb.tokens.BLSS' => 'tok']);
    $branches = Mockery::mock(EsbItemJournalService::class);
    $branches->shouldReceive('branches')->andReturn([['branchCode' => 'BLEV']]);
    app()->instance(EsbItemJournalService::class, $branches);

    $current = collect($menus)->keyBy('menuID')->all();

    Http::fake([
        '*/get-menu-category*' => fn ($request) => Http::response(['status' => 'ok', 'result' => ['data' => ($request['page'] ?? 1) == 1
            ? [['menuCategoryName' => 'MILLE CREPE', 'menuCategoryDetails' => [['menuCategoryDetailID' => 153, 'menuCategoryDetailName' => 'WHOLE']]]] : []]]),
        '*/update-menu' => function ($request) use (&$current, $afterUpdate) {
            $payload = $request->data()[0];
            $current[$payload['menuID']] = $afterUpdate
                ? $afterUpdate($payload, $current[$payload['menuID']])
                : array_replace($current[$payload['menuID']], ['menuCode' => $payload['menuCode'], 'menuShortName' => $payload['menuShortName']]);

            return Http::response(['status' => 'ok', 'result' => []]);
        },
        '*/get-menu*' => function ($request) use (&$current) {
            $code = $request['menuCode'] ?? null;
            if ($code !== null) {
                return Http::response(['status' => 'ok', 'result' => ['data' => array_values(array_filter($current, fn ($m) => $m['menuCode'] === $code))]]);
            }

            return Http::response(['status' => 'ok', 'result' => ['data' => ($request['page'] ?? 1) == 1 ? array_values($current) : []]]);
        },
    ]);
}

it('renames short names and codes, keeping the original data and only active template prices', function () {
    fakeMenuEsb([
        wholeMenu(1, 'MC-WH0001', 'Belgian Chocolate Whole Cake'),
        wholeMenu(2, 'MC-WH0001-SBY', 'Belgian Chocolate Whole SBY'),
        wholeMenu(3, '', 'Matcha Whole'),
        wholeMenu(4, 'MC-WH0012', 'Whole Earl Grey Lychee'),
    ]);

    $this->artisan('esb:normalize-mille-crepe-whole-menus', ['--execute' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('updated: 3')
        ->expectsOutputToContain('already_done: 1')
        ->assertSuccessful();

    $posts = Http::recorded(fn ($request) => str_ends_with($request->url(), '/update-menu'))->map(fn ($pair) => $pair[0]->data()[0])->values();

    expect($posts)->toHaveCount(3)
        ->and($posts[0])->toMatchArray([
            'menuID' => 1, 'menuCategoryDetailID' => 153, 'menuCode' => 'MC-WH0001', 'menuShortName' => 'Whole Belgian Chocolate',
            'flagTax' => 1, 'flagOtherTax' => true, 'flagOpenPrice' => false, 'printZeroValue' => true, 'updateCheckerAndStation' => false,
            'menuTemplates' => [['menuTemplateID' => 1, 'price' => 100000.0]],
        ])
        ->and($posts[1])->toMatchArray(['menuCode' => 'MC-WH0001-SBY', 'menuShortName' => 'Whole Cake Belgian Chocolate SBY'])
        ->and($posts[2])->toMatchArray(['menuCode' => 'MC-WH0005', 'menuShortName' => 'Whole Matcha']);
});

it('does not write on a dry run and skips conflicts, unmapped flavors and complex menus', function () {
    fakeMenuEsb([
        wholeMenu(1, 'MC-WH0001', 'Belgian Chocolate Whole Cake'),
        wholeMenu(2, 'MC-WH0009', 'Whole Mystery Flavor'),
        wholeMenu(3, 'Y3', 'Tiramisu Whole Cake'),
        wholeMenu(4, 'X', 'Blueberry Whole', ['menuExtras' => [['menuExtraID' => 9, 'menuID' => 4, 'menuExtraName' => 'Candle', 'price' => '5000', 'minExtraQty' => '0', 'maxExtraQty' => '10']]]),
    ]);

    $this->artisan('esb:normalize-mille-crepe-whole-menus', ['--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('would_update: 1')
        ->expectsOutputToContain('flavor_unmapped: 1')
        ->expectsOutputToContain('code_conflict: 1')
        ->expectsOutputToContain('skipped_complex: 1')
        ->assertSuccessful();

    expect(Http::recorded(fn ($request) => str_ends_with($request->url(), '/update-menu')))->toHaveCount(0);
});

it('rolls back and stops when the menu data differs after the update', function () {
    fakeMenuEsb([
        wholeMenu(1, 'MC-WH0001', 'Belgian Chocolate Whole Cake'),
        wholeMenu(2, 'MC-WH0002', 'Blueberry Whole'),
    ], function (array $payload, array $current): array {
        $updated = array_replace($current, ['menuCode' => $payload['menuCode'], 'menuShortName' => $payload['menuShortName']]);

        return $payload['menuShortName'] === 'Whole Belgian Chocolate' ? array_replace($updated, ['themeMenuOnPos' => '#fff']) : $updated;
    });

    $this->artisan('esb:normalize-mille-crepe-whole-menus', ['--execute' => true, '--report' => tempnam(sys_get_temp_dir(), 'rep')])
        ->expectsOutputToContain('mismatch: 1')
        ->assertFailed();

    $posts = Http::recorded(fn ($request) => str_ends_with($request->url(), '/update-menu'))->map(fn ($pair) => $pair[0]->data()[0])->values();

    expect($posts)->toHaveCount(2)
        ->and($posts[1])->toMatchArray(['menuID' => 1, 'menuCode' => 'MC-WH0001', 'menuShortName' => 'Belgian Chocolate Whole Cake']);
});
