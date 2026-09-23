<?php

use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function readyMenu(RndInternalMemo $memo, array $overrides = []): RndInternalMemoMenu
{
    return RndInternalMemoMenu::factory()->create(array_merge([
        'rnd_internal_memo_id' => $memo->id,
        'menu_name' => 'Croissant Butter',
        'forecast_quantity' => 10,
        'shelf_life_value' => 3,
        'shelf_life_unit' => 'hari',
        'sync_status' => RndInternalMemoMenuSyncStatus::Synced,
        'sync_error' => null,
        'sync_warnings' => null,
    ], $overrides));
}

it('blocks a memo that has no Menu at all', function () {
    $memo = RndInternalMemo::factory()->create();

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toBe(['Memo belum memiliki Menu.']);
});

it('reports no blockers once every readiness requirement is satisfied', function () {
    $memo = RndInternalMemo::factory()->create();
    readyMenu($memo);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toBe([]);
});

it('blocks on a missing Forecast Quantity', function () {
    $memo = RndInternalMemo::factory()->create();
    readyMenu($memo, ['forecast_quantity' => 0]);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toContain('Forecast Quantity Menu "Croissant Butter" belum diisi atau tidak valid.');
});

it('blocks on a missing Shelf Life', function () {
    $memo = RndInternalMemo::factory()->create();
    readyMenu($memo, ['shelf_life_value' => null, 'shelf_life_unit' => null]);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toContain('Shelf Life Menu "Croissant Butter" belum diisi.');
});

it('blocks a Menu that has never been synced', function () {
    $memo = RndInternalMemo::factory()->create();
    readyMenu($memo, ['sync_status' => RndInternalMemoMenuSyncStatus::Pending]);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toContain('Menu "Croissant Butter" belum pernah disinkronkan.');
});

it('blocks a Menu whose last sync failed, quoting the sync error', function () {
    $memo = RndInternalMemo::factory()->create();
    readyMenu($memo, ['sync_status' => RndInternalMemoMenuSyncStatus::Failed, 'sync_error' => 'Koneksi ESB gagal']);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toContain('Sinkronisasi BOM Menu "Croissant Butter" gagal: Koneksi ESB gagal');
});

it('blocks a synced Menu that still carries a resolver blocker in sync_error', function () {
    $memo = RndInternalMemo::factory()->create();
    readyMenu($memo, ['sync_error' => 'Circular BOM terdeteksi pada jalur X.']);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toContain('Menu "Croissant Butter": Circular BOM terdeteksi pada jalur X.');
});

it('surfaces sync_warnings as warnings, never as blockers', function () {
    $memo = RndInternalMemo::factory()->create();
    readyMenu($memo, ['sync_warnings' => ['Bahan "X" tidak mempunyai Product Code.']]);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toBe([])
        ->and($result['warnings'])->toContain('Bahan "X" tidak mempunyai Product Code.');
});

it('warns when the same productDetailID appears with different UOM across Menus', function () {
    $memo = RndInternalMemo::factory()->create();
    $menuA = readyMenu($memo);
    $menuB = readyMenu($memo, ['esb_menu_id' => $menuA->esb_menu_id + 1]);
    $menuA->materials()->create(['esb_product_detail_id' => 999, 'product_code' => 'X', 'product_name' => 'X', 'uom_name' => 'GR', 'quantity_per_menu' => 1, 'net_quantity' => 1, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $menuB->materials()->create(['esb_product_detail_id' => 999, 'product_code' => 'X', 'product_name' => 'X', 'uom_name' => 'KG', 'quantity_per_menu' => 1, 'net_quantity' => 1, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    $result = app(InternalMemoValidationService::class)->validate($memo);

    expect($result['blockers'])->toBe([])
        ->and(collect($result['warnings'])->implode(' '))->toContain('UOM berbeda');
});
