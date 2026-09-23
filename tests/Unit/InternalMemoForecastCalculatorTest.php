<?php

use App\Models\RndInternalMemoMenu;
use App\Services\Rnd\InternalMemo\InternalMemoForecastCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('multiplies every material row by the Menu Forecast Quantity', function () {
    $menu = RndInternalMemoMenu::factory()->create(['forecast_quantity' => 3]);
    $flour = $menu->materials()->create(['product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);
    $sugar = $menu->materials()->create(['product_code' => 'RAW-SUGAR', 'product_name' => 'Gula', 'uom_name' => 'GR', 'quantity_per_menu' => 10, 'net_quantity' => 0, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    app(InternalMemoForecastCalculator::class)->recalculateMenu($menu);

    expect((float) $flour->fresh()->net_quantity)->toBe(750.0)
        ->and((float) $sugar->fresh()->net_quantity)->toBe(30.0);
});

it('zeroes net_quantity when Forecast Quantity is zero', function () {
    $menu = RndInternalMemoMenu::factory()->create(['forecast_quantity' => 0]);
    $material = $menu->materials()->create(['product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR', 'quantity_per_menu' => 250, 'net_quantity' => 999, 'source_bom_id' => 1, 'source_path' => [], 'depth' => 0, 'is_wip' => false, 'is_packaging' => false]);

    app(InternalMemoForecastCalculator::class)->recalculateMenu($menu);

    expect((float) $material->fresh()->net_quantity)->toBe(0.0);
});
