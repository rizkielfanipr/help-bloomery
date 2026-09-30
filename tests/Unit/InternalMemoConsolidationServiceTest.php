<?php

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

it('splits the Ringkasan Item Akhir into Bahan and WIP groups, unlike consolidate()', function () {
    [$memo, $menu] = memoWithMenu();
    $menu->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => ['Menu'], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menu->materials()->create(['esb_product_detail_id' => 1, 'product_code' => 'BW1356', 'product_name' => 'Croissant Dough WIP', 'uom_name' => 'PCS', 'quantity_per_menu' => 2, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => ['Menu'], 'depth' => 0, 'is_wip' => true, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidateForSummary($memo);

    expect($result['bahan'])->toHaveCount(1)
        ->and($result['bahan'][0]['product_code'])->toBe('RAW-FLOUR')
        ->and($result['wip'])->toHaveCount(1)
        ->and($result['wip'][0]['product_code'])->toBe('BW1356')
        ->and($result['wip'][0]['is_wip'])->toBeTrue();
});

it('merges the same item reached through two Menus into one summary row listing both sources', function () {
    $memo = RndInternalMemo::factory()->create();
    $menuA = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'menu_name' => 'Menu A']);
    $menuB = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id, 'menu_name' => 'Menu B']);
    $menuA->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => ['Menu A'], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menuB->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 100, 'net_quantity' => 0, 'source_bom_id' => 2, 'source_path' => ['Menu B'], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidateForSummary($memo);

    expect($result['bahan'])->toHaveCount(1)
        ->and($result['bahan'][0]['sources'])->toHaveCount(2);
});

it('shows Minimum Order in the summary only when every contributing row agrees', function () {
    $memo = RndInternalMemo::factory()->create();
    $menuA = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $menuB = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $menuA->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'minimum_order' => 500, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menuB->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 100, 'net_quantity' => 0, 'minimum_order' => 500, 'source_bom_id' => 2, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoConsolidationService::class)->consolidateForSummary($memo);

    expect((float) $result['bahan'][0]['minimum_order'])->toBe(500.0);
});
