<?php

use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMinimumOrdersAction;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoConsolidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function memoWithMenu(array $menuOverrides = []): array
{
    $memo = RndInternalMemo::factory()->create();
    $menu = RndInternalMemoMenu::factory()->create(array_merge(['rnd_internal_memo_id' => $memo->id], $menuOverrides));

    return [$memo, $menu];
}

it('sums net_quantity across Menus for the same productDetailID and UOM', function () {
    [$memo, $menuA] = memoWithMenu();
    $menuB = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);

    $menuA->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 500, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menuB->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'gr', 'quantity_per_menu' => 1, 'net_quantity' => 300, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidate($memo);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['net_quantity'])->toBe(800.0)
        ->and($result['rows'][0]['menu_count'])->toBe(2)
        ->and($result['warnings'])->toBe([]);
});

it('keeps a different UOM as a separate row instead of summing it in', function () {
    [$memo, $menu] = memoWithMenu();
    $menu->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 500, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menu->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'KG', 'quantity_per_menu' => 1, 'net_quantity' => 5, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidate($memo);

    expect($result['rows'])->toHaveCount(2);
});

it('excludes WIP/Assembly rows from consolidation', function () {
    [$memo, $menu] = memoWithMenu();
    $menu->materials()->create(['esb_product_detail_id' => 1, 'product_code' => 'BW1356', 'product_name' => 'WIP', 'uom_name' => 'PCS', 'quantity_per_menu' => 1, 'net_quantity' => 999, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => true, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidate($memo);

    expect($result['rows'])->toBe([]);
});

it('groups a missing productDetailID by Product Code and raises a warning', function () {
    [$memo, $menu] = memoWithMenu();
    $menu->materials()->create(['esb_product_detail_id' => null, 'product_code' => 'RAW-UNKNOWN', 'product_name' => 'Bahan Tanpa ID', 'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 100, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidate($memo);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['has_fallback_identity'])->toBeTrue()
        ->and($result['warnings'])->not->toBeEmpty();
});

it('puts Menu BOM rows under Store, split into Bahan and WIP, unlike consolidate()', function () {
    [$memo, $menu] = memoWithMenu();
    $menu->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => ['Menu'], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menu->materials()->create(['esb_product_detail_id' => 1, 'product_code' => 'BW1356', 'product_name' => 'Croissant Dough WIP', 'uom_name' => 'PCS', 'quantity_per_menu' => 2, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => ['Menu'], 'depth' => 0, 'is_wip' => true, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidateForSummary($memo);

    expect($result['store']['bahan'])->toHaveCount(1)
        ->and($result['store']['bahan'][0]['product_code'])->toBe('RAW-FLOUR')
        ->and($result['store']['wip'])->toHaveCount(1)
        ->and($result['store']['wip'][0]['product_code'])->toBe('BW1356')
        ->and($result['store']['wip'][0]['is_wip'])->toBeTrue()
        ->and($result['kitchen'])->toBe(['wip' => [], 'bahan' => []]);
});

it('merges the same item reached through two Menus into one summary row listing both sources', function () {
    $memo = RndInternalMemo::factory()->create();
    $menuA = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'menu_name' => 'Menu A']);
    $menuB = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'menu_name' => 'Menu B']);
    $menuA->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => ['Menu A'], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menuB->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 100, 'net_quantity' => 0, 'source_bom_id' => 2, 'source_path' => ['Menu B'], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidateForSummary($memo);

    expect($result['store']['bahan'])->toHaveCount(1)
        ->and($result['store']['bahan'][0]['sources'])->toHaveCount(2);
});

it('shows Minimum Order in the summary only when every contributing row agrees', function () {
    $memo = RndInternalMemo::factory()->create();
    $menuA = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $menuB = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $menuA->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'minimum_order' => 500, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menuB->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 100, 'net_quantity' => 0, 'minimum_order' => 500, 'source_bom_id' => 2, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidateForSummary($memo);

    expect((float) $result['store']['bahan'][0]['minimum_order'])->toBe(500.0);
});

it('puts traced WIP BOM rows under Kitchen and keeps the same product separate per Store and Kitchen', function () {
    [$memo, $menu] = memoWithMenu();
    $row = fn (array $attributes) => $menu->materials()->create([
        'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => ['Menu'],
        'depth' => 0, 'is_wip' => false, 'is_packaging' => false, ...$attributes,
    ]);
    $row(['esb_product_detail_id' => 1, 'product_code' => 'RAW-SUGAR', 'product_name' => 'Gula']);
    $wip = $row(['esb_product_detail_id' => 2, 'product_code' => 'BW100', 'product_name' => 'Crepe Sheet', 'is_wip' => true]);
    $row(['esb_product_detail_id' => 1, 'product_code' => 'RAW-SUGAR', 'product_name' => 'Gula', 'depth' => 1, 'parent_material_id' => $wip->id, 'minimum_order' => 25]);
    $row(['esb_product_detail_id' => 3, 'product_code' => 'BW212', 'product_name' => 'PRX | CRP02', 'depth' => 1, 'parent_material_id' => $wip->id, 'is_wip' => true]);

    $result = app(InternalMemoConsolidationService::class)->consolidateForSummary($memo);

    expect(collect($result['store']['bahan'])->pluck('product_code')->all())->toBe(['RAW-SUGAR'])
        ->and(collect($result['store']['wip'])->pluck('product_code')->all())->toBe(['BW100'])
        ->and(collect($result['kitchen']['bahan'])->pluck('product_code')->all())->toBe(['RAW-SUGAR'])
        ->and(collect($result['kitchen']['wip'])->pluck('product_code')->all())->toBe(['BW212'])
        ->and($result['store']['bahan'][0]['minimum_order'])->toBeNull()
        ->and((float) $result['kitchen']['bahan'][0]['minimum_order'])->toBe(25.0)
        ->and($result['store']['bahan'][0]['key'])->not->toBe($result['kitchen']['bahan'][0]['key']);

    app(UpdateInternalMemoMinimumOrdersAction::class)->execute($memo, $result['store']['bahan'][0]['key'], 10);

    expect($menu->materials()->where('product_code', 'RAW-SUGAR')->orderBy('depth')->pluck('minimum_order')->map(fn ($value) => (float) $value)->all())->toBe([10.0, 25.0]);
});
