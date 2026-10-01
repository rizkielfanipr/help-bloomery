<?php

use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ListRndInternalMemos;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * docs/rnd-internal-memo-simplification-prd.md §14.2: "Query count tidak bertambah linear karena
 * N+1 pada daftar Menu/item." These compare a small and a large Memo's query count on render;
 * a real N+1 would scale with the number of Menus/Materials, a batched eager load would not.
 * Query logging is disabled while the fixture data itself is being created, so only the page's
 * own render queries are counted.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $operator = User::factory()->create(['is_active' => true]);
    $operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'update rnd internal memo']);
    $this->actingAs($operator);
});

/**
 * Each call must land on a distinct period_month: RndInternalMemoFactory's own docblock
 * documents that its random period_month is not unique-safe across several memos in one test.
 */
function internalMemoWithMenus(int $menuCount, int $materialsPerMenu): RndInternalMemo
{
    static $sequence = 0;
    $memo = RndInternalMemo::factory()->create(['period_month' => now()->addMonths($sequence++)->startOfMonth()]);

    for ($i = 0; $i < $menuCount; $i++) {
        $menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
        for ($j = 0; $j < $materialsPerMenu; $j++) {
            $menu->materials()->create([
                'esb_product_detail_id' => ($i * 100) + $j,
                'product_code' => "RAW-{$i}-{$j}", 'product_name' => "Bahan {$i}-{$j}", 'uom_name' => 'GR',
                'quantity_per_menu' => 10, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0,
                'is_wip' => false, 'is_packaging' => false,
            ]);
        }
    }

    return $memo;
}

/** @return int Query count for rendering, excluding fixture setup. */
function countQueriesFor(Closure $render): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $render();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('does not scale index query count with the number of Memos listed', function () {
    internalMemoWithMenus(1, 1);
    internalMemoWithMenus(1, 1);
    $fewQueries = countQueriesFor(fn () => Livewire::test(ListRndInternalMemos::class));

    for ($i = 0; $i < 10; $i++) {
        internalMemoWithMenus(1, 1);
    }
    $manyQueries = countQueriesFor(fn () => Livewire::test(ListRndInternalMemos::class));

    expect($manyQueries)->toBeLessThanOrEqual($fewQueries + 2);
});

it('does not scale the workspace query count with the number of Menus/Materials', function () {
    $smallMemo = internalMemoWithMenus(2, 2);
    $fewQueries = countQueriesFor(fn () => Livewire::test(ViewRndInternalMemo::class, ['record' => $smallMemo->id]));

    $largeMemo = internalMemoWithMenus(8, 6);
    $manyQueries = countQueriesFor(fn () => Livewire::test(ViewRndInternalMemo::class, ['record' => $largeMemo->id]));

    expect($manyQueries)->toBeLessThanOrEqual($fewQueries + 2);
});
