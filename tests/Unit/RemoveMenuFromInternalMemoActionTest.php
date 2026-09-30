<?php

use App\Actions\Rnd\InternalMemo\RemoveMenuFromInternalMemoAction;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('removes only the targeted Menu and its own Materials, leaving other Menus untouched', function () {
    $memo = RndInternalMemo::factory()->create();
    $menuToRemove = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);
    $menuToRemove->materials()->create([
        'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR',
        'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
    ]);
    $otherMenu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $memo->id]);

    app(RemoveMenuFromInternalMemoAction::class)->execute($memo, $menuToRemove->id);

    expect(RndInternalMemoMenu::query()->find($menuToRemove->id))->toBeNull()
        ->and($memo->menus()->count())->toBe(1)
        ->and($memo->menus()->first()->id)->toBe($otherMenu->id);
});

it('refuses to remove a Menu that does not belong to the given Memo', function () {
    $memo = RndInternalMemo::factory()->create();
    $otherMemo = RndInternalMemo::factory()->create(['period_month' => now()->addMonth()->startOfMonth()]);
    $menu = RndInternalMemoMenu::factory()->create(['rnd_internal_memo_id' => $otherMemo->id]);

    expect(fn () => app(RemoveMenuFromInternalMemoAction::class)->execute($memo, $menu->id))
        ->toThrow(ModelNotFoundException::class);

    expect(RndInternalMemoMenu::query()->find($menu->id))->not->toBeNull();
});
