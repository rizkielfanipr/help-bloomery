<?php

use App\Actions\Rnd\InternalMemo\UpdateInternalMemoMinimumOrdersAction;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoItemIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates Minimum Order on every Material row sharing the same identity across two Menus', function () {
    $memo = RndInternalMemo::factory()->create();
    $menuA = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $menuB = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $materialA = $menuA->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $materialB = $menuB->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 100, 'net_quantity' => 0, 'source_bom_id' => 2, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $key = InternalMemoItemIdentity::key(15002, null, 'RAW-FLOUR', 'Tepung', 'GR');
    $updated = app(UpdateInternalMemoMinimumOrdersAction::class)->execute($memo, $key, 500.0);

    expect($updated)->toBe(2)
        ->and((float) $materialA->fresh()->minimum_order)->toBe(500.0)
        ->and((float) $materialB->fresh()->minimum_order)->toBe(500.0);
});

it('does not touch a Material with a different identity', function () {
    $memo = RndInternalMemo::factory()->create();
    $menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $flour = $menu->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $sugar = $menu->materials()->create(['esb_product_detail_id' => 15003, 'product_code' => 'RAW-SUGAR', 'product_name' => 'Gula', 'uom_name' => 'GR', 'quantity_per_menu' => 10, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $key = InternalMemoItemIdentity::key(15002, null, 'RAW-FLOUR', 'Tepung', 'GR');
    app(UpdateInternalMemoMinimumOrdersAction::class)->execute($memo, $key, 500.0);

    expect($sugar->fresh()->minimum_order)->toBeNull()
        ->and((float) $flour->fresh()->minimum_order)->toBe(500.0);
});

it('clears Minimum Order when given null', function () {
    $memo = RndInternalMemo::factory()->create();
    $menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $material = $menu->materials()->create(['esb_product_detail_id' => 15002, 'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'minimum_order' => 500, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $key = InternalMemoItemIdentity::key(15002, null, 'RAW-FLOUR', 'Tepung', 'GR');
    app(UpdateInternalMemoMinimumOrdersAction::class)->execute($memo, $key, null);

    expect($material->fresh()->minimum_order)->toBeNull();
});
